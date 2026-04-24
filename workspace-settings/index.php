<?php
declare(strict_types=1);

if (!empty($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = preg_replace('~/workspace-settings/index\.php$~', '/index.php', (string) $_SERVER['SCRIPT_NAME']) ?? (string) $_SERVER['SCRIPT_NAME'];
}
if (!empty($_SERVER['PHP_SELF'])) {
    $_SERVER['PHP_SELF'] = preg_replace('~/workspace-settings/index\.php$~', '/index.php', (string) $_SERVER['PHP_SELF']) ?? (string) $_SERVER['PHP_SELF'];
}

require __DIR__ . '/../workspace-settings.php';
