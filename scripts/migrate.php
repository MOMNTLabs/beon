<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$startedAt = microtime(true);
$pdo = db();
ensureDatabaseSchemaReady($pdo, true);
$elapsedMs = (microtime(true) - $startedAt) * 1000;

echo sprintf(
    "[%s] Database migration completed in %.2f ms (%s).\n",
    nowIso(),
    $elapsedMs,
    dbDriverName($pdo)
);

exit(0);

