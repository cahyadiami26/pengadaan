<?php
/**
 * Konfigurasi Utama Sistem Pengadaan - PT. Lampung Jasa Utama
 * https://pengadaan.lju.co.id
 */

// ---- Database (aplikasi) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'ljucoid_pbj');
define('DB_USER', 'ljucoid_pbj');
define('DB_PASS', 'icikiwir2026');

// ---- Database SSO/users (database berbeda, satu mesin) ----
define('SSO_DB_HOST', 'localhost');
define('SSO_DB_NAME', 'ljucoid_dms'); // sesuaikan dengan nama DB users produksi

// ---- URL & Path ----
define('APP_NAME', 'Sistem Pengadaan Barang/Jasa');
define('APP_COMPANY', 'PT. Lampung Jasa Utama');
define('APP_URL', 'https://pengadaan.lju.co.id');
define('SSO_PORTAL_URL', 'https://lju.co.id/dashboard');
define('LOGO_BASE_URL', 'https://lju.co.id/assets/images/logo/');
define('AVATAR_BASE_URL', 'https://dms.lju.co.id/uploads/avatars/');

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('QR_PATH', UPLOAD_PATH . '/qr');
define('FOTO_PATH', UPLOAD_PATH . '/foto');

// ---- Timezone ----
date_default_timezone_set('Asia/Jakarta');

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/user_cache.php';
