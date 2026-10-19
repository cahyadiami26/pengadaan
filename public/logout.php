<?php
/** Logout: hapus session aplikasi, arahkan kembali ke SSO Portal. */
require_once dirname(__DIR__) . '/config/config.php';
ini_set('session.cookie_domain', '.lju.co.id');
session_name('LJU_APPS');
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: https://lju.co.id/dashboard/?target_system=' . urlencode(APP_URL));
exit;
