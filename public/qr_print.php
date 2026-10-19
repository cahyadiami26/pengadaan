<?php
/** Cetak label QR satu aset (halaman bersih siap print). */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$a = q("SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.id=?", [$id])->fetch();
if (!$a) exit('Aset tidak ditemukan');
$text = APP_URL . '/public/aset_view.php?code=' . $a['code'];
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Label <?= e($a['code']) ?></title>
<style>body{font-family:'Segoe UI',sans-serif;display:flex;justify-content:center;padding:30px}
.label{border:1.5px solid #0f2a43;border-radius:10px;padding:16px;width:300px;text-align:center}
.label img{width:220px;height:220px}.code{font-weight:800;font-size:14px;margin-top:8px;letter-spacing:.5px}
.meta{font-size:12px;color:#475569;margin-top:4px}.co{font-size:10px;color:#9ca3af;margin-top:8px}</style></head>
<body onload="window.print()">
<div class="label">
  <img src="<?= e(qr_img_src($text)) ?>" alt="QR">
  <div class="code"><?= e($a['code']) ?></div>
  <div class="meta"><?= e($a['name']) ?><br><?= e($a['lokasi'] ?? '') ?> · <?= e($a['unit'] ?? '') ?></div>
  <div class="co">PT. Lampung Jasa Utama — Sistem Pengadaan</div>
</div></body></html>
