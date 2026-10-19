<?php
/**
 * Bootstrap SSO & Session - Sistem Pengadaan PT. Lampung Jasa Utama
 * Login & session ditangani oleh SSO Portal (cookie domain .lju.co.id).
 */

require_once dirname(__DIR__) . '/config/config.php';

// 1. Session dengan cookie domain bersama SSO
ini_set('session.cookie_domain', '.lju.co.id');
session_name('LJU_APPS');

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '.lju.co.id',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly'  => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Proteksi Halaman: Jika belum login, redirect ke Portal SSO dengan parameter target
if (empty($_SESSION['user_id'])) {
    $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // Jika akses berasal dari host lokal/dev, sediakan halaman login bantu;
    // pada domain produksi (.lju.co.id) redirect penuh ke SSO Portal.
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $isLocal = !$host || strpos($host, 'lju.co.id') === false;
    if ($isLocal) {
        header('Location: login.php');
        exit;
    }
    // Redirect ke SSO Portal
    header('Location: https://lju.co.id/dashboard/?target_system=' . urlencode('https://pengadaan.lju.co.id') . '&redirect_after_login=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 3. Sinkronkan ulang session dari tabel users (database SSO) setiap load halaman.
//    Nomenklatur aplikasi: users.division => nama_bidang, users.department => nama_divisi.
function sync_session_from_users(): void
{
    try {
        $stmt = ssoDb()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $userRow = $stmt->fetch();

        if (!$userRow) {
            // user tidak ditemukan di SSO -> logout paksa
            foreach (array_keys($_SESSION) as $k) unset($_SESSION[$k]);
            header('Location: https://lju.co.id/dashboard/?target_system=' . urlencode('https://pengadaan.lju.co.id'));
            exit;
        }

        $_SESSION['user_id']      = $userRow['id'];
        $_SESSION['user_nik']     = $userRow['nik'];
        $_SESSION['username']     = $userRow['username'];
        $_SESSION['user_name']    = $userRow['name'];
        $_SESSION['user_email']   = $userRow['email'];
        $_SESSION['role']         = $userRow['role'];
        $_SESSION['position']     = $userRow['position'];
        $_SESSION['division']     = $userRow['division'];     // nama_bidang (aplikasi)
        $_SESSION['department']   = $userRow['department'];   // nama_divisi (aplikasi)
        $_SESSION['avatar']       = $userRow['avatar'];
        $_SESSION['photo']        = $userRow['photo'];
        $_SESSION['signature']    = $userRow['signature'];
        $_SESSION['rfid']         = $userRow['rfid'];
        $_SESSION['webmail_user'] = $userRow['webmail_user'];
        $_SESSION['webmail_pass'] = $userRow['webmail_pass'];

        // ---- Role aplikasi (hasil pemetaan role_mapping) ----
        // Default mapping berdasarkan jabatan/role users, dapat dioverride lewat halaman Pemetaan Role.
        $appRole = 'pegawai';
        try {
            $rm = db()->prepare("SELECT app_role FROM role_mapping WHERE user_id = ? LIMIT 1");
            $rm->execute([$userRow['id']]);
            $mapped = $rm->fetchColumn();
            if ($mapped) {
                $appRole = $mapped;
            } else {
                // auto-map dari role/jabatan SSO
                if ($userRow['role'] === 'admin' || in_array($userRow['position'], ['System Administrator'])) {
                    $appRole = 'admin';
                } elseif ($userRow['position'] === 'Direktur Operasional') {
                    $appRole = 'pimpinan';
                } elseif ($userRow['position'] === 'Kepala Divisi') {
                    $appRole = 'kepala_divisi';
                } elseif ($userRow['position'] === 'Kepala Bidang') {
                    $appRole = 'kepala_bidang';
                } elseif ($userRow['division'] === 'Pengadaan' || $userRow['role'] === 'op_arsip') {
                    $appRole = 'staff_pengadaan';
                } else {
                    $appRole = 'pegawai';
                }
            }
        } catch (Throwable $ex) { /* table belum ada */ }
        $_SESSION['app_role']   = $appRole;
        $_SESSION['nama_bidang'] = $userRow['division'];   // nomenklatur baru: division => bidang
        $_SESSION['nama_divisi'] = $userRow['department']; // nomenklatur baru: department => divisi
    } catch (Throwable $e) {
        // Jika DB SSO tidak dapat diakses, tetap gunakan session yang sudah ada dari SSO.
    }
}
sync_session_from_users();

/** Cek apakah user boleh mengakses modul tertentu */
function can($module): bool
{
    $r = $_SESSION['app_role'] ?? 'pegawai';
    $matrix = [
        'dashboard'          => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan'],
        'pegawai'            => ['admin','staff_pengadaan'],
        'lokasi'             => ['admin','staff_pengadaan'],
        'kebutuhan'          => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan'],
        'rencana_pengadaan'  => ['admin','staff_pengadaan','kepala_divisi','pimpinan'],
        'penerimaan'         => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi'],
        'inventaris'         => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan'],
        'kerusakan'          => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan','pegawai'],
        'pemeliharaan'       => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan'],
        'stock_opname'       => ['admin','staff_pengadaan'],
        'mutasi'             => ['admin','staff_pengadaan'],
        'penghapusan'        => ['admin','staff_pengadaan','pimpinan'],
        'perlengkapan'       => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan','pegawai'],
        'fasilitas'          => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan','pegawai'],
        'kendaraan'          => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan','pegawai'],
        'perawatan_kendaraan'=> ['admin','staff_pengadaan','pimpinan'],
        'laporan'            => ['admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan'],
        'role_mapping'       => ['admin'],
        'settings'           => ['admin'],
    ];
    return in_array($r, $matrix[$module] ?? ['admin'], true);
}
