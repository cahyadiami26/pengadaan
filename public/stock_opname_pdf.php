<?php
/** Laporan Stock Opname (PDF, domPDF). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/pdf.php';
require_role(['admin', 'staff_pengadaan']);

$id = (int)($_GET['id'] ?? 0);
$op = q("SELECT so.*, l.name AS lokasi, uc.name AS pembuat FROM stock_opnames so
         LEFT JOIN locations l ON l.id=so.location_id LEFT JOIN user_cache uc ON uc.user_id=so.created_by
         WHERE so.id=?", [$id])->fetch();
if (!$op) exit('Data tidak ditemukan');
$items = q("SELECT i.*, a.code, a.name AS asset_name, a.unit, a.value, l.name AS lokasi
            FROM stock_opname_items i LEFT JOIN assets a ON a.id=i.asset_id
            LEFT JOIN locations l ON l.id=a.location_id WHERE i.opname_id=? ORDER BY FIELD(i.match_status,'MISSING','EXTRA','COOK'), i.scanned_code", [$id])->fetchAll();

$sum = ['COOK' => 0, 'MISSING' => 0, 'EXTRA' => 0];
foreach ($items as $i) $sum[$i['match_status']] = ($sum[$i['match_status']] ?? 0) + 1;

$body = '<table class="data">
<tr><th>Nomor Opname</th><td>' . e($op['opname_no']) . '</td><th>Tanggal</th><td>' . date('d-m-Y', strtotime($op['opname_date'])) . '</td></tr>
<tr><th>Cakupan</th><td>' . e($op['lokasi'] ?? ($op['unit'] ?: 'Semua Lokasi')) . '</td><th>Status</th><td>' . e($op['status']) . '</td></tr>
<tr><th>Pelaksana</th><td colspan="3">' . e($op['pembuat'] ?? '-') . '</td></tr>
</table>
<p><b>Ringkasan:</b> Cocok: ' . $sum['COOK'] . ' &nbsp;|&nbsp; Tidak Ditemukan: ' . $sum['MISSING'] . ' &nbsp;|&nbsp; Di Luar Daftar: ' . $sum['EXTRA'] . ' &nbsp;|&nbsp; Total Periksa: ' . count($items) . '</p>';

$rows = '';
$no = 0;
foreach ($items as $i) {
    $no++;
    $rows .= '<tr><td class="center">' . $no . '</td>'
      . '<td>' . e($i['code'] ?: $i['scanned_code']) . '</td>'
      . '<td>' . e($i['asset_name'] ?? '(tidak dikenali)') . '</td>'
      . '<td>' . e($i['unit'] ?? '-') . '</td>'
      . '<td>' . e($i['lokasi'] ?? '-') . '</td>'
      . '<td class="center"><b>' . e($i['match_status']) . '</b></td>'
      . '<td>' . e($i['checked_condition'] ?? '-') . '</td>'
      . '<td>' . e($i['note'] ?? '') . '</td></tr>';
}
$body .= '<table class="data"><tr><th width="4%">No</th><th>Kode Aset</th><th>Nama</th><th>Unit</th><th>Lokasi</th><th>Hasil</th><th>Kondisi</th><th>Catatan</th></tr>' . ($rows ?: '<tr><td colspan="8" class="center muted">Tidak ada item.</td></tr>') . '</table>';

$body .= '<table width="100%" style="margin-top:40px"><tr>
<td width="33%" align="center" style="font-size:10px">Mengetahui,<br>Kepala Bagian Umum/Pengadaan<br><br><br><b>( .................................... )</b></td>
<td width="33%"></td>
<td width="33%" align="center" style="font-size:10px">Lampung, ' . date('d F Y') . '<br>Petugas Opname<br><br><br><b>(' . e($op['pembuat'] ?? '......................') . ')</b></td>
</tr></table>';

$html = pdf_wrap(pdf_doc_header('LAPORAN HASIL STOCK OPNAME', 'PT. Lampung Jasa Utama — ' . e($op['title'] ?: 'Stock Opname')) . $body);
pdf_output($html, 'Laporan_Stock_Opname_' . preg_replace('/\W+/', '_', $op['opname_no']), true);
