<?php
/**
 * Ejecuta un script ajax/*.php REAL del sistema Control en un proceso PHP aislado,
 * simulando $_POST/$_GET/$_SESSION, tal como ocurriría en una petición HTTP real.
 * Uso: php ajax_runner.php <ruta_relativa_en_ajax/> <json_post> <json_get>
 */
$relativePath = $argv[1];
$post = json_decode($argv[2] ?? '{}', true) ?: [];
$get = json_decode($argv[3] ?? '{}', true) ?: [];

$_POST = $post;
$_GET = $get;
$_REQUEST = array_merge($_GET, $_POST);
session_start();
$_SESSION['user_login_status'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['firstname'] = 'Admin';

chdir(__DIR__ . '/ajax');
ob_start();
include $relativePath;
echo ob_get_clean();
