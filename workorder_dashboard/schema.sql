-- ============================================================
-- ForgeDeskDev — Fab Schedule Module
-- PostgreSQL Schema
-- Designed to slot into jkweks/forgedesk3dev
-- ============================================================

-- ─── USERS (stub — integrate with forgedesk3 auth) ──────────
CREATE TABLE IF NOT EXISTS fd_users (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    initials    VARCHAR(10),
    role        VARCHAR(20) NOT NULL DEFAULT 'worker',   -- 'worker' | 'manager' | 'admin'
    email       VARCHAR(150) UNIQUE,
    active      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ─── RATE CONSTANTS (the 4 values in rows 1–4 of spreadsheet) ─
CREATE TABLE IF NOT EXISTS fd_rate_constants (
    key         VARCHAR(50) PRIMARY KEY,  -- 'cw_prep' | 'fab_joints' | 'fab_doors' | 'fab_frames'
    label       VARCHAR(100) NOT NULL,
    value       NUMERIC(6,3) NOT NULL,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO fd_rate_constants (key, label, value) VALUES
    ('cw_prep',    'CW Prep (hrs/joint)',      0.500),
    ('fab_joints', 'Fab Time Joints (hrs/ea)', 0.250),
    ('fab_doors',  'Fab Time Doors (hrs/ea)',  2.250),
    ('fab_frames', 'Fab Time Frames (hrs/ea)', 1.500)
ON CONFLICT (key) DO NOTHING;

-- ─── JOB TYPES ──────────────────────────────────────────────
CREATE TYPE fd_job_type AS ENUM ('SF', 'CW');

-- ─── WORK ORDERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fd_work_orders (
    id                      SERIAL PRIMARY KEY,

    -- Identity
    project_name            VARCHAR(200) NOT NULL,
    job_number              VARCHAR(50),
    wo_number               VARCHAR(20) NOT NULL,           -- can be '1', '1.1', '14' etc.

    -- Dates
    date_received           DATE,
    planned_start_date      DATE,
    planned_complete_date   DATE,
    requested_finish_date   DATE,

    -- Classification
    job_type                fd_job_type,                    -- SF | CW
    system                  VARCHAR(50),                    -- T14000, 4500, 400T, etc.

    -- Quantities
    joints                  INTEGER DEFAULT 0,
    dr_fr_units             INTEGER DEFAULT 0,              -- Door/Frame units
    doors_units             INTEGER DEFAULT 0,

    -- Estimated work content
    -- Calculated: SF = (joints*0.25) + (dr_fr*1.5) + (doors*2.25)
    --             CW = (joints*0.5) + (dr_fr*1.5) + (doors*2.25)
    estimated_hours_calc    NUMERIC(8,2) GENERATED ALWAYS AS (0) STORED, -- placeholder, updated by trigger
    estimated_hours_override NUMERIC(8,2),                 -- manager override; NULL = use calculated

    -- Status flags
    material_arrived        VARCHAR(50),                    -- 'Yes', 'SOF', date string, NULL
    cut_list_glazer         BOOLEAN DEFAULT FALSE,

    -- Meta
    notes                   TEXT,
    project_manager         VARCHAR(100),                   -- FK to fd_users.name (loose, match on name)
    assigned_to_id          INTEGER REFERENCES fd_users(id),

    -- Lifecycle
    archived                BOOLEAN NOT NULL DEFAULT FALSE,
    archived_at             TIMESTAMPTZ,
    completed_date          DATE,

    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Drop generated column and replace with real one + trigger
ALTER TABLE fd_work_orders DROP COLUMN estimated_hours_calc;
ALTER TABLE fd_work_orders ADD COLUMN estimated_hours_calc NUMERIC(8,2);

-- Trigger: recalculate estimated_hours_calc on insert/update
CREATE OR REPLACE FUNCTION fd_recalc_work_hours()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    r_joints  NUMERIC;
    r_doors   NUMERIC;
    r_frames  NUMERIC;
    r_cw_prep NUMERIC;
BEGIN
    SELECT value INTO r_cw_prep FROM fd_rate_constants WHERE key = 'cw_prep';
    SELECT value INTO r_joints  FROM fd_rate_constants WHERE key = 'fab_joints';
    SELECT value INTO r_doors   FROM fd_rate_constants WHERE key = 'fab_doors';
    SELECT value INTO r_frames  FROM fd_rate_constants WHERE key = 'fab_frames';

    IF NEW.job_type = 'CW' THEN
        NEW.estimated_hours_calc :=
            COALESCE(NEW.joints, 0)    * r_cw_prep +
            COALESCE(NEW.dr_fr_units, 0) * r_frames +
            COALESCE(NEW.doors_units, 0) * r_doors;
    ELSE
        NEW.estimated_hours_calc :=
            COALESCE(NEW.joints, 0)    * r_joints +
            COALESCE(NEW.dr_fr_units, 0) * r_frames +
            COALESCE(NEW.doors_units, 0) * r_doors;
    END IF;

    NEW.updated_at := NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_work_order_hours
    BEFORE INSERT OR UPDATE ON fd_work_orders
    FOR EACH ROW EXECUTE FUNCTION fd_recalc_work_hours();

-- ─── JOB STAGE TEMPLATES (defaults per type) ─────────────────
-- Manager can customise per-WO; these are the seeds.
CREATE TABLE IF NOT EXISTS fd_stage_templates (
    id          SERIAL PRIMARY KEY,
    job_type    fd_job_type,            -- NULL = applies to both
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0
);

INSERT INTO fd_stage_templates (job_type, name, description, sort_order) VALUES
    ('SF', 'Material Check',    'Confirm all material on-site',         1),
    ('SF', 'Cut List Released', 'Cut list sent to glazer',              2),
    ('SF', 'Frame Fab',         'Fabricate frames',                     3),
    ('SF', 'Door Fab',          'Fabricate doors',                      4),
    ('SF', 'Hardware Install',  'Apply hardware and panic devices',     5),
    ('SF', 'QC & Pack',         'Quality check and palletise',          6),
    ('CW', 'Material Check',    'Confirm curtainwall material on-site', 1),
    ('CW', 'CW Prep',           'Prep joints / shear blocks',           2),
    ('CW', 'Member Fab',        'Cut and punch mullions/transoms',      3),
    ('CW', 'Glazing Prep',      'Cap, pressure plate, seal prep',       4),
    ('CW', 'QC & Pack',         'Quality check and palletise',          5)
ON CONFLICT DO NOTHING;

-- ─── PER-WORK-ORDER STAGES ───────────────────────────────────
CREATE TABLE IF NOT EXISTS fd_wo_stages (
    id              SERIAL PRIMARY KEY,
    work_order_id   INTEGER NOT NULL REFERENCES fd_work_orders(id) ON DELETE CASCADE,
    template_id     INTEGER REFERENCES fd_stage_templates(id),
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending'|'in_progress'|'complete'|'blocked'
    assigned_to_id  INTEGER REFERENCES fd_users(id),
    started_at      TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    notes           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ─── STAGE COMMENTS / ACTIVITY LOG ──────────────────────────
CREATE TABLE IF NOT EXISTS fd_stage_log (
    id          SERIAL PRIMARY KEY,
    stage_id    INTEGER NOT NULL REFERENCES fd_wo_stages(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES fd_users(id),
    message     TEXT NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ─── INDEXES ─────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_wo_archived          ON fd_work_orders(archived);
CREATE INDEX IF NOT EXISTS idx_wo_planned_complete  ON fd_work_orders(planned_complete_date);
CREATE INDEX IF NOT EXISTS idx_wo_assigned          ON fd_work_orders(assigned_to_id);
CREATE INDEX IF NOT EXISTS idx_wo_type              ON fd_work_orders(job_type);
CREATE INDEX IF NOT EXISTS idx_stages_wo            ON fd_wo_stages(work_order_id);
CREATE INDEX IF NOT EXISTS idx_stages_status        ON fd_wo_stages(status);

-- ─── HELPER VIEW: active WOs with effective hours ────────────
CREATE OR REPLACE VIEW fd_active_work_orders AS
SELECT
    wo.*,
    COALESCE(wo.estimated_hours_override, wo.estimated_hours_calc) AS effective_hours,
    u.name  AS assigned_name,
    u.role  AS assigned_role,
    (SELECT COUNT(*) FROM fd_wo_stages s WHERE s.work_order_id = wo.id)               AS stage_count,
    (SELECT COUNT(*) FROM fd_wo_stages s WHERE s.work_order_id = wo.id AND s.status = 'complete') AS stages_done
FROM fd_work_orders wo
LEFT JOIN fd_users u ON u.id = wo.assigned_to_id
WHERE wo.archived = FALSE
ORDER BY wo.planned_complete_date ASC NULLS LAST;
