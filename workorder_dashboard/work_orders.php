<?php
// ============================================================
// /api/work_orders.php
// GET    ?archived=0&pm=Dan+S&type=SF&q=search   — list
// GET    ?id=42                                   — single
// POST                                            — create
// PUT    ?id=42                                   — full update
// PATCH  ?id=42                                   — partial update
// DELETE ?id=42                                   — archive (soft)
// ============================================================
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

match ($_SERVER['REQUEST_METHOD']) {
    'GET'    => $id ? get_one($id) : get_list(),
    'POST'   => create_wo(),
    'PUT'    => update_wo($id, full: true),
    'PATCH'  => update_wo($id, full: false),
    'DELETE' => archive_wo($id),
    default  => json_err('Method not allowed', 405),
};

// ── LIST ──────────────────────────────────────────────────────
function get_list(): void {
    $archived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
    $pm       = $_GET['pm']   ?? null;
    $type     = $_GET['type'] ?? null;
    $q        = $_GET['q']    ?? null;

    $where = ['wo.archived = :archived'];
    $bind  = [':archived' => $archived];

    if ($pm)   { $where[] = 'wo.project_manager = :pm';   $bind[':pm']   = $pm; }
    if ($type) { $where[] = 'wo.job_type = :type';        $bind[':type'] = $type; }
    if ($q)    {
        $where[] = "(wo.project_name ILIKE :q OR wo.job_number ILIKE :q OR wo.wo_number ILIKE :q)";
        $bind[':q'] = "%$q%";
    }

    $sql = "
        SELECT
            wo.*,
            COALESCE(wo.estimated_hours_override, wo.estimated_hours_calc) AS effective_hours,
            u.name  AS assigned_name,
            u.role  AS assigned_role,
            COUNT(s.id)                                              AS stage_count,
            COUNT(s.id) FILTER (WHERE s.status = 'complete')        AS stages_done,
            COUNT(s.id) FILTER (WHERE s.status = 'in_progress')     AS stages_active,
            COUNT(s.id) FILTER (WHERE s.status = 'blocked')         AS stages_blocked
        FROM fd_work_orders wo
        LEFT JOIN fd_users u ON u.id = wo.assigned_to_id
        LEFT JOIN fd_wo_stages s ON s.work_order_id = wo.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY wo.id, u.name, u.role
        ORDER BY wo.planned_complete_date ASC NULLS LAST
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($bind);
    json_ok($stmt->fetchAll());
}

// ── SINGLE ────────────────────────────────────────────────────
function get_one(int $id): void {
    $stmt = db()->prepare("
        SELECT wo.*,
               COALESCE(wo.estimated_hours_override, wo.estimated_hours_calc) AS effective_hours,
               u.name AS assigned_name
        FROM fd_work_orders wo
        LEFT JOIN fd_users u ON u.id = wo.assigned_to_id
        WHERE wo.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $wo = $stmt->fetch();
    if (!$wo) json_err('Work order not found', 404);

    // Attach stages
    $s = db()->prepare("
        SELECT s.*, u.name AS assigned_name
        FROM fd_wo_stages s
        LEFT JOIN fd_users u ON u.id = s.assigned_to_id
        WHERE s.work_order_id = :id
        ORDER BY s.sort_order
    ");
    $s->execute([':id' => $id]);
    $wo['stages'] = $s->fetchAll();

    json_ok($wo);
}

// ── CREATE ────────────────────────────────────────────────────
function create_wo(): void {
    method('POST');
    $d = body();
    require_param($d, 'project_name', 'wo_number');

    $stmt = db()->prepare("
        INSERT INTO fd_work_orders (
            project_name, job_number, wo_number,
            date_received, planned_start_date, planned_complete_date, requested_finish_date,
            job_type, system,
            joints, dr_fr_units, doors_units,
            estimated_hours_override,
            material_arrived, cut_list_glazer,
            notes, project_manager, assigned_to_id
        ) VALUES (
            :project_name, :job_number, :wo_number,
            :date_received, :planned_start_date, :planned_complete_date, :requested_finish_date,
            :job_type, :system,
            :joints, :dr_fr_units, :doors_units,
            :estimated_hours_override,
            :material_arrived, :cut_list_glazer,
            :notes, :project_manager, :assigned_to_id
        ) RETURNING id
    ");
    $stmt->execute(map_fields($d));
    $row = $stmt->fetch();
    $id  = $row['id'];

    // Auto-seed stages from template for this job type
    if (!empty($d['job_type'])) {
        seed_stages($id, $d['job_type']);
    }

    json_ok(['id' => $id], 201);
}

// ── UPDATE ────────────────────────────────────────────────────
function update_wo(?int $id, bool $full): void {
    if (!$id) json_err('id required');
    method('PUT', 'PATCH');
    $d = body();

    $fields = map_fields($d);
    // Remove keys not in payload for PATCH
    if (!$full) {
        $fields = array_filter($fields, fn($v) => $v !== null || array_key_exists(ltrim(array_search($v, $fields), ':'), $d));
    }
    unset($fields[':id']); // will add back

    $sets = implode(', ', array_map(fn($k) => substr($k, 1) . " = $k", array_keys($fields)));
    if (!$sets) json_err('Nothing to update');

    $fields[':id'] = $id;
    $stmt = db()->prepare("UPDATE fd_work_orders SET $sets WHERE id = :id");
    $stmt->execute($fields);

    json_ok(['updated' => $stmt->rowCount()]);
}

// ── ARCHIVE (soft delete) ─────────────────────────────────────
function archive_wo(?int $id): void {
    if (!$id) json_err('id required');
    method('DELETE');
    $stmt = db()->prepare("UPDATE fd_work_orders SET archived = TRUE, archived_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $id]);
    json_ok(['archived' => $id]);
}

// ── HELPERS ───────────────────────────────────────────────────
function map_fields(array $d): array {
    return [
        ':project_name'             => $d['project_name']              ?? null,
        ':job_number'               => $d['job_number']                ?? null,
        ':wo_number'                => $d['wo_number']                 ?? null,
        ':date_received'            => $d['date_received']             ?? null,
        ':planned_start_date'       => $d['planned_start_date']        ?? null,
        ':planned_complete_date'    => $d['planned_complete_date']     ?? null,
        ':requested_finish_date'    => $d['requested_finish_date']     ?? null,
        ':job_type'                 => $d['job_type']                  ?? null,
        ':system'                   => $d['system']                    ?? null,
        ':joints'                   => isset($d['joints'])             ? (int)$d['joints']   : null,
        ':dr_fr_units'              => isset($d['dr_fr_units'])        ? (int)$d['dr_fr_units'] : null,
        ':doors_units'              => isset($d['doors_units'])        ? (int)$d['doors_units'] : null,
        ':estimated_hours_override' => isset($d['estimated_hours_override']) && $d['estimated_hours_override'] !== ''
                                        ? (float)$d['estimated_hours_override'] : null,
        ':material_arrived'         => $d['material_arrived']          ?? null,
        ':cut_list_glazer'          => isset($d['cut_list_glazer'])    ? (bool)$d['cut_list_glazer'] : null,
        ':notes'                    => $d['notes']                     ?? null,
        ':project_manager'          => $d['project_manager']           ?? null,
        ':assigned_to_id'           => isset($d['assigned_to_id'])     ? (int)$d['assigned_to_id'] : null,
    ];
}

function seed_stages(int $wo_id, string $job_type): void {
    $stmt = db()->prepare("
        SELECT * FROM fd_stage_templates
        WHERE job_type = :type OR job_type IS NULL
        ORDER BY sort_order
    ");
    $stmt->execute([':type' => $job_type]);
    $templates = $stmt->fetchAll();

    $ins = db()->prepare("
        INSERT INTO fd_wo_stages (work_order_id, template_id, name, description, sort_order)
        VALUES (:wo_id, :tpl_id, :name, :desc, :sort)
    ");
    foreach ($templates as $t) {
        $ins->execute([
            ':wo_id'  => $wo_id,
            ':tpl_id' => $t['id'],
            ':name'   => $t['name'],
            ':desc'   => $t['description'],
            ':sort'   => $t['sort_order'],
        ]);
    }
}
