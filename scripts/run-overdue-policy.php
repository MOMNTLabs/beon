<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$pdo = db();
$stmt = $pdo->query(
    'SELECT id
     FROM workspaces
     ORDER BY id ASC'
);
$rows = $stmt ? $stmt->fetchAll() : [];

$workspaceCount = 0;
$updatedTasks = 0;
foreach ($rows as $row) {
    $workspaceId = (int) ($row['id'] ?? 0);
    if ($workspaceId <= 0) {
        continue;
    }

    $workspaceCount++;
    $updatedTasks += applyOverdueTaskPolicy($workspaceId);
}

echo sprintf(
    "[%s] Overdue policy completed. Updated %d task(s) across %d workspace(s).\n",
    nowIso(),
    $updatedTasks,
    $workspaceCount
);

exit(0);
