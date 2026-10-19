<?php
/** Header layout (sidebar + topbar). Butuh bootstrap.php sudah dimuat. */
if (session_status() === PHP_SESSION_NONE) require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? APP_NAME;
$active = $active ?? '';
$nav = [
    ['dashboard', 'Dashboard', '📊'],
    ['kebutuhan', 'Pengajuan Kebutuhan', '📝'],
    ['rencana_pengadaan', 'Rencana Pengadaan', '🗂️'],
    ['penerimaan', 'Penerimaan Barang/Jasa', '📦'],
    ['inventaris', 'Inventaris & Aset', '🏷️'],
    ['stock_opname', 'Stock Opname', '🔍'],
    ['mutasi', 'Mutasi Aset', '🔄'],
    ['penghapusan', 'Penghapusan Aset', '🗑️'],
    ['perlengkapan', 'Perlengkapan / ATK', '🖇️'],
    ['kerusakan', 'Laporan Kerusakan', '⚠️'],
    ['pemeliharaan', 'Pemeliharaan & Perbaikan', '🛠️'],
    ['fasilitas', 'Fasilitas Kantor', '🏢'],
    ['kendaraan', 'Kendaraan', '🚗'],
    ['perawatan_kendaraan', 'Perawatan Kendaraan', '🔧'],
    ['laporan', 'Laporan', '📈'],
    ['pegawai', 'Data Pegawai', '👥'],
    ['lokasi', 'Data Lokasi', '📍'],
    ['role_mapping', 'Pemetaan Role', '🎭'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - <?= e(APP_COMPANY) ?></title>
<link rel="icon" href="<?= e(LOGO_BASE_URL) ?>logo.png" type="image/png">
<link rel="stylesheet" href="https://lju.co.id/assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="<?= e(LOGO_BASE_URL) ?>logo.png" alt="Logo LJU" onerror="this.style.display='none'">
      <div>
        <strong>Pengadaan LJU</strong>
        <small>PT. Lampung Jasa Utama</small>
      </div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($nav as [$key, $label, $icon]): if (!can($key)) continue; ?>
        <a href="<?= $key ?>.php" class="nav-item <?= $active === $key ? 'active' : '' ?>">
          <span class="nav-icon"><?= $icon ?></span><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot"><small>&copy; <?= date('Y') ?> <?= e(APP_COMPANY) ?></small></div>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button class="btn-icon menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div class="topbar-title"><?= e($pageTitle) ?></div>
      <div class="topbar-right">
        <a class="btn-icon notif-bell" href="<?= can('dashboard') ? 'dashboard.php' : '#' ?>" title="Notifikasi">🔔<?php
            $notifN = can('dashboard') ? notification_badge_count() : (int)($_SESSION['notif_count'] ?? 0);
            if ($notifN > 0) echo '<span class="dot">' . (int)$notifN . '</span>';
        ?></a>
        <div class="user-chip">
          <?= avatar_html($_SESSION['avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 34) ?>
          <div class="user-meta">
            <strong><?= e($_SESSION['user_name'] ?? '') ?></strong>
            <small><?= e(ucwords(str_replace('_', ' ', $_SESSION['app_role'] ?? 'pegawai'))) ?> · <?= e($_SESSION['position'] ?? '') ?></small>
          </div>
        </div>
        <a class="btn btn-outline btn-sm" href="logout.php">Keluar</a>
      </div>
    </header>

    <main class="content">
    <?= flash() ?>
