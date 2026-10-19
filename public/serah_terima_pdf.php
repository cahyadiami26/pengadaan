<?php
/** Cetak Berita Acara Serah Terima (domPDF). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/pdf.php';

$id = (int)($_GET['id'] ?? 0);
$h = q("SELECT h.*, uc1.name AS pemberi, uc1.position AS pemberi_jab,
               uc2.name AS penerima, uc2.position AS penerima_jab, uc2.nama_bidang AS penerima_bidang
        FROM handovers h
        LEFT JOIN user_cache uc1 ON uc1.user_id = h.from_user_id
        LEFT JOIN user_cache uc2 ON uc2.user_id = h.to_user_id WHERE h.id=?", [$id])->fetch();
if (!$h) exit('Data tidak ditemukan');

$absImg = function (?string $rel): string {
    return $rel ? 'file://' . BASE_PATH . '/' . ltrim($rel, '/') : '';
};
$tglIndo = date('d F Y', strtotime($h['handover_date']));

$rows = '<table class="data">
<tr><th width="22%">Pihak Menyerahkan</th><td><b>' . e($h['pemberi'] ?? '-') . '</b><br><span class="muted">' . e($h['pemberi_jab'] ?? '') . ' — Bagian Umum/Pengadaan</span></td></tr>
<tr><th>Pihak Menerima</th><td><b>' . e($h['penerima'] ?? '-') . '</b><br><span class="muted">' . e($h['penerima_jab'] ?? '') . ($h['penerima_bidang'] ? ' — ' . e($h['penerima_bidang']) : '') . '</span></td></tr>
<tr><th>Nomor Berita Acara</th><td>' . e($h['handover_no']) . '</td></tr>
<tr><th>Tanggal</th><td>' . e($tglIndo) . '</td></tr>
<tr><th>Referensi</th><td>' . e(str_replace('_', ' ', ucwords($h['ref_type'] ?? '-'))) . ($h['ref_id'] ? ' #' . (int)$h['ref_id'] : '') . '</td></tr>
<tr><th>Detail Item Diserahterimakan</th><td>' . nl2br(e($h['items_desc'])) . '</td></tr>';
if ($h['note']) $rows .= '<tr><th>Catatan</th><td>' . nl2br(e($h['note'])) . '</td></tr>';
$rows .= '</table>';

$sigBlock = '<table width="100%" style="margin-top:34px"><tr>'
  . '<td width="50%" align="center" style="font-size:10px">Menyerahkan,<br><b>' . e($h['pemberi'] ?? '-') . '</b><br>'
  . ($absImg($h['sig_giver']) && file_exists(BASE_PATH . '/' . $h['sig_giver']) ? '<img src="' . $absImg($h['sig_giver']) . '" height="70">' : '<div style="height:70px"></div>')
  . '</td>'
  . '<td width="50%" align="center" style="font-size:10px">Menerima,<br><b>' . e($h['penerima'] ?? '-') . '</b><br>'
  . ($absImg($h['sig_receiver']) && file_exists(BASE_PATH . '/' . $h['sig_receiver']) ? '<img src="' . $absImg($h['sig_receiver']) . '" height="70">' : '<div style="height:70px"></div>')
  . '</td></tr></table>';

$html = pdf_wrap(
    pdf_doc_header('BERITA ACARA SERAH TERIMA BARANG', 'Nomor: ' . $h['handover_no'])
  . '<p style="text-align:justify;line-height:1.6">Pada hari ini, kami yang bertanda tangan di bawah ini menyatakan bahwa telah terjadi serah terima barang dari Bagian Umum/Pengadaan kepada pihak penerima dengan rincian sebagai berikut:</p>'
  . $rows . $sigBlock
  . '<p class="muted" style="margin-top:26px">Dokumen ini dihasilkan secara elektronik oleh Sistem Pengadaan Barang/Jasa PT. Lampung Jasa Utama (' . e(APP_URL) . ').</p>'
);
pdf_output($html, 'Serah_Terima_' . preg_replace('/\W+/', '_', $h['handover_no']), true);
