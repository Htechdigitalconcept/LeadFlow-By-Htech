<?php
$root = getenv('HTECL_WP_PATH');
if (!$root || !is_file($root . '/wp-load.php') || getenv('HTECL_TEST_ALLOW_RESET') !== '1') {
    throw new RuntimeException('Provide HTECL_WP_PATH and HTECL_TEST_ALLOW_RESET=1 for a disposable WordPress installation. Tests erase its LeadFlow data.');
}
require $root . '/wp-load.php';
if (!class_exists(HTECH\LeadFlow\Store::class)) {
    throw new RuntimeException('Activate LeadFlow in the disposable WordPress installation before testing.');
}
