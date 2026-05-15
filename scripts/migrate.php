<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

if (appUsesProductionGuards()) {
    $diagnostics = productionConfigDiagnostics();
    foreach ($diagnostics['warnings'] as $warning) {
        fwrite(STDOUT, "[warn] " . $warning . PHP_EOL);
    }
    if (!empty($diagnostics['errors'])) {
        foreach ($diagnostics['errors'] as $error) {
            fwrite(STDERR, "[error] " . $error . PHP_EOL);
        }
        exit(1);
    }
}

try {
    $pdo = db();
    migrate($pdo);
    appMetaSet($pdo, 'app_release_id', generateUuidV4());
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo sprintf("[%s] Migration completed successfully.\n", nowIso());
exit(0);
