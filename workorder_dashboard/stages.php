<?php
// ============================================================
// /api/stages.php
// GET    ?wo_id=42            — stages for a work order
// POST                        — add a stage to a WO
// PATCH  ?id=5                — update stage (status, notes, assigned)
// DELETE ?id=5                — delete a stage
// ============================================================
require_once __DIR__ . '/config.php';

$id    = isset($_GET['id'])    ? (int)$_GET['id']    : null;
$wo_id = isset($_GET['wo_id']) ? (int)$_GET['wo_id'] : null;

match ($_SERVER['REQUEST_METHOD']) {
    'GET'    => get_stages($wo_id),
    'POST'   => add_stage(),
    'PATCH'  => update_stage($id),
    'DELETE' => delete_stage($id),
    default  => json_err('Method not allowed', 405),
};

function get_stages(?int $wo_id): void {
    if (!$wo_id) json_err('wo_id required');
    $stmt = db()->prepare("
        SELECT s.*, u.name AS assigned_name,
               (SELECT json_agg(l ORDER BY l.created_at DESC)
                FROM fd_stage_log l WHERE l.stage_id = s.id) AS log
        FROM fd_wo_stages s
        LEFT JOIN fd_users u ON u.id = s.assigned_to_id
        WHERE s.work_order_id = :wo_id
        ORDER BY s.sort_order
    ");
    $stmt->execute([':wo_id' => $wo_id]);
    json_ok($stmt->fetchAll());
}

function add_stage(): void {
    method('POST');
    $d = body();
    require_param($d, 'work_order_id', 'name');

    // Get current max sort_order for this WO
    $max = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM fd_wo_stages WHERE work_order_id = :id");
    $max->execute([':id' => (int)$d['work_order_id']]);
    $sort = $max->fetchColumn();

    $stmt = db()->prepare("
        INSERT INTO fd_wo_stages (work_order_id, name, description, sort_order, assigned_to_id)
        VALUES (:wo_id, :name, :desc, :sort, :assigned)
        RETURNING id
    ");
    $stmt->execute([
        ':wo_id'    => (int)$d['work_order_id'],
        ':name'     => $d['name'],
        ':desc'     => $d['description'] ?? null,
        ':sort'     => $d['sort_order']  ?? $sort,
        ':assigned' => isset($d['assigned_to_id']) ? (int)$d['assigned_to_id'] : null,
    ]);
    json_ok(['id' => $stmt->fetch()['id']], 201);
}

function update_stage(?int $id): void {
    if (!$id) json_err('id required');
    method('PATCH');
    $d = body();

    $allowed = ['name','description','status','sort_order','assigned_to_id','notes','started_at','completed_at'];
    $sets = []; $bind = [':id' => $id];

    foreach ($allowed as $f) {
        if (array_key_exists($f, $d)) {
            $sets[]     = "$f = :$f";
            $bind[":$f"] = $d[$f];
        }
    }

    // Auto-set timestamps on status change
    if (($d['status'] ?? null) === 'in_progress' && !isset($d['started_at'])) {
        $sets[]              = "started_at = NOW()";
    }
    if (($d['status'] ?? null) === 'complete' && !isset($d['completed_at'])) {
        $sets[]              = "completed_at = NOW()";
    }

    if (!$sets) json_err('Nothing to update');

    $sets[] = "updated_at = NOW()";
    $sql    = "UPDATE fd_wo_stages SET " . implode(', ', $sets) . " WHERE id = :id";
    db()->prepare($sql)->execute($bind);

    // Append log entry if a message was included
    if (!empty($d['log_message']) && !empty($d['user_id'])) {
        db()->prepare("INSERT INTO fd_stage_log (stage_id, user_id, message) VALUES (:sid, :uid, :msg)")
            ->execute([':sid' => $id, ':uid' => (int)$d['user_id'], ':msg' => $d['log_message']]);
    }

    json_ok(['updated' => $id]);
}

function delete_stage(?int $id): void {
    if (!$id) json_err('id required');
    method('DELETE');
    db()->prepare("DELETE FROM fd_wo_stages WHERE id = :id")->execute([':id' => $id]);
    json_ok(['deleted' => $id]);
}
