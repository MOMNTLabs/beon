<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$diagnostics = productionConfigDiagnostics();

echo sprintf("[%s] Production preflight for environment: %s\n", nowIso(), (string) ($diagnostics['environment'] ?? 'unknown'));

$warnings = $diagnostics['warnings'] ?? [];
foreach ($warnings as $warning) {
    fwrite(STDOUT, "[warn] " . $warning . PHP_EOL);
}

$errors = $diagnostics['errors'] ?? [];
if (!empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[error] " . $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "[ok] Production environment looks consistent." . PHP_EOL);
exit(0);
