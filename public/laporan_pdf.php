<?php
/**
 * Mesin Laporan PDF (domPDF). Dipakai oleh laporan.php dan tautan "Cetak PDF"
 * di berbagai modul. Jenis laporan via ?jenis=..., periode via ?from=&to=.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/pdf.php';

$JENIS = ['pengadaan','belum_diterima','inventaris','inventaris_pegawai','mutasi','riwayat_aset',
          'atk_stok','atk_transaksi','atk_distribusi','kerusakan','kendaraan','kendaraan_servis','aset_lokasi'];
$j    = $_GET['jenis'] ?? 'pengadaan';
if (!in_array($j, $JENIS, true)) $j = 'pengadaan';
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$locId = (int)($_GET['lokasi'] ?? 0);

function tbl(array $head, array $rows): string
{
    $h = '<table class="data"><tr>';
    foreach ($head as $c) $h .= '<th>' . e($c) . '</th>';
    $h .= '</tr>';
    if (!$rows) $h .= '<tr><td colspan="' . count($head) . '" class="center muted">Tidak ada data pada periode ini.</td></tr>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        foreach ($r as $cell) {
            $cls = is_array($cell) ? ($cell['cls'] ?? '') : '';
            $val = is_array($cell) ? $cell['v'] : $cell;
            $h .= '<td' . ($cls ? ' class="' . $cls . '"' : '') . '>' . $val . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</table>';
}
$money = fn($n) => number_format((float)$n, 0, ',', '.');

$title = ''; $sub = "Periode: " . date('d-m-Y', strtotime($from)) . ' s.d. ' . date('d-m-Y', strtotime($to));
$body = '';

switch ($j) {

case 'pengadaan':
    $title = 'LAPORAN PENGADAAN — REKAP KEBUTUHAN & RENCANA';
    $needs = q("SELECT nr.*, uc.name AS pemohon FROM needs_requests nr LEFT JOIN user_cache uc ON uc.user_id=nr.user_id
                WHERE DATE(nr.created_at) BETWEEN ? AND ? ORDER BY nr.id", [$from, $to])->fetchAll();
    $body .= '<h3>A. Rekap Pengajuan Kebutuhan (' . count($needs) . ') </h3>';
    $rows = [];
    foreach ($needs as $n) {
        $items = q("SELECT COUNT(*) c, IFNULL(SUM(qty*est_price),0) v FROM needs_items WHERE need_id=?", [$n['id']])->fetch();
        $rows[] = [$n['request_no'], date('d-m-Y', strtotime($n['created_at'])), e($n['pemohon'] ?? '-'), e($n['nama_bidang'] ?? ''),
                   (int)$items['c'], ['v' => $money($items['v']), 'cls' => 'right'], e($n['status'])];
    }
    $body .= tbl(['No. Permintaan', 'Tanggal', 'Pemohon', 'Bidang', 'Item', 'Estimasi (Rp)', 'Status'], $rows);

    $plans = q("SELECT p.*, uc.name AS pembuat,
                (SELECT IFNULL(SUM(qty*est_price),0) FROM procurement_plan_items WHERE plan_id=p.id) AS total_est
                FROM procurement_plans p LEFT JOIN user_cache uc ON uc.user_id=p.created_by
                WHERE p.plan_date BETWEEN ? AND ? ORDER BY p.id", [$from, $to])->fetchAll();
    $body .= '<h3 style="margin-top:18px">B. Rencana Pengadaan (' . count($plans) . ')</h3>';
    $rows = []; $sumAll = 0;
    foreach ($plans as $p) {
        $sumAll += (float)$p['total_est'];
        $rows[] = [$p['plan_no'], date('d-m-Y', strtotime($p['plan_date'])), e($p['requesting_units'] ?? '-'), e($p['pembuat'] ?? '-'),
                   e($p['priority']), ['v' => $money($p['budget']), 'cls' => 'right'], ['v' => $money($p['total_est']), 'cls' => 'right'], e($p['status'])];
    }
    $body .= tbl(['No. Rencana', 'Tanggal', 'Unit Pemohon', 'Pembuat', 'Prioritas', 'Anggaran (Rp)', 'Estimasi Total (Rp)', 'Status'], $rows);
    $body .= '<p><b>Total estimasi seluruh rencana: Rp ' . $money($sumAll) . '</b></p>';
    break;

case 'belum_diterima':
    $title = 'LAPORAN BARANG/BELUM DITERIMA';
    $plans = q("SELECT p.*, uc.name AS pembuat,
                (SELECT COUNT(*) FROM receptions r WHERE r.plan_id=p.id) AS acc_count,
                (SELECT IFNULL(SUM(qty*est_price),0) FROM procurement_plan_items WHERE plan_id=p.id) AS total_est
                FROM procurement_plans p LEFT JOIN user_cache uc ON uc.user_id=p.created_by
                WHERE p.status IN ('DALAM_PROSES_PENGADAAN','FINAL_KEUANGAN','DISETUJUI_DIREKTUR') ORDER BY p.plan_date")->fetchAll();
    $rows = [];
    foreach ($plans as $p) {
        $rows[] = [$p['plan_no'], date('d-m-Y', strtotime($p['plan_date'])), e($p['requesting_units'] ?? '-'), e($p['pembuat'] ?? '-'),
                   ['v' => $money($p['total_est']), 'cls' => 'right'],
                   $p['acc_count'] ? '<span class="badge">Sebagian</span>' : '<b>Belum sama sekali</b>', e($p['status'])];
    }
    $body .= tbl(['No. Rencana', 'Tanggal', 'Unit Pemohon', 'Pembuat', 'Nilai (Rp)', 'Penerimaan', 'Status'], $rows);
    break;

case 'inventaris':
    $title = 'LAPORAN INVENTARIS & ASET';
    $where = 'a.created_at BETWEEN ? AND ?'; $params = [$from, $to . ' 23:59:59'];
    if ($locId) { $where .= ' AND a.location_id=?'; $params[] = $locId; }
    $assets = q("SELECT a.*, l.name AS lokasi, uc.name AS pengguna FROM assets a
                 LEFT JOIN locations l ON l.id=a.location_id LEFT JOIN user_cache uc ON uc.user_id=a.user_id
                 WHERE $where ORDER BY a.code", $params)->fetchAll();
    $rows = []; $totVal = 0;
    foreach ($assets as $a) {
        $totVal += (float)$a['value'];
        $rows[] = [e($a['code']), e($a['name']), ucfirst(e($a['category'])), e($a['lokasi'] ?? '-'), e($a['unit'] ?? '-'),
                   e($a['pengguna'] ?? '-'), date('d-m-Y', strtotime($a['purchase_date'] ?: $a['created_at'])),
                   ['v' => $money($a['value']), 'cls' => 'right'], e($a['condition_status']), e($a['status'])];
    }
    $body .= tbl(['Kode', 'Nama', 'Kategori', 'Lokasi', 'Unit', 'Pengguna', 'Tgl Perolehan', 'Nilai (Rp)', 'Kondisi', 'Status'], $rows);
    $body .= '<p><b>Jumlah aset: ' . count($assets) . ' · Total nilai: Rp ' . $money($totVal) . '</b></p>';
    // rekap per unit & kondisi
    $perUnit = q("SELECT a.unit, COUNT(*) c, IFNULL(SUM(a.value),0) v FROM assets a WHERE $where GROUP BY a.unit ORDER BY c DESC", $params)->fetchAll();
    $body .= '<h3 style="margin-top:16px">Rekap per Unit</h3>' . tbl(['Unit', 'Jumlah', 'Nilai (Rp)'],
        array_map(fn($r) => [e($r['unit'] ?? '-'), (int)$r['c'], ['v' => $money($r['v']), 'cls' => 'right']], $perUnit));
    $perCond = q("SELECT a.condition_status, COUNT(*) c FROM assets a WHERE $where GROUP BY a.condition_status", $params)->fetchAll();
    $body .= '<h3>Rekap per Kondisi</h3>' . tbl(['Kondisi', 'Jumlah'], array_map(fn($r) => [e($r['condition_status']), (int)$r['c']], $perCond));
    break;

case 'inventaris_pegawai':
    $title = 'LAPORAN INVENTARIS PER PEGAWAI';
    $rowsData = q("SELECT uc.name, uc.position, uc.nama_bidang, COUNT(a.id) c, IFNULL(SUM(a.value),0) v
                   FROM user_cache uc LEFT JOIN assets a ON a.user_id=uc.user_id AND a.status='AKTIF'
                   WHERE uc.is_active=1 GROUP BY uc.user_id HAVING c > 0 ORDER BY c DESC")->fetchAll();
    $body .= tbl(['Pegawai', 'Jabatan', 'Bidang', 'Jumlah Aset', 'Nilai (Rp)'],
        array_map(fn($r) => [e($r['name']), e($r['position'] ?? '-'), e($r['nama_bidang'] ?? '-'), (int)$r['c'], ['v' => $money($r['v']), 'cls' => 'right']], $rowsData));
    $detail = q("SELECT a.code, a.name, a.condition_status, l.name AS lokasi, uc.name AS pengguna FROM assets a
                 JOIN user_cache uc ON uc.user_id=a.user_id LEFT JOIN locations l ON l.id=a.location_id
                 WHERE a.status='AKTIF' ORDER BY uc.name, a.code")->fetchAll();
    $body .= '<h3 style="margin-top:16px">Rincian</h3>' . tbl(['Kode', 'Nama Aset', 'Pengguna', 'Lokasi', 'Kondisi'],
        array_map(fn($r) => [e($r['code']), e($r['name']), e($r['pengguna']), e($r['lokasi'] ?? '-'), e($r['condition_status'])], $detail));
    break;

case 'mutasi':
    $title = 'LAPORAN MUTASI ASET';
    $muts = q("SELECT m.*, a.code, a.name AS asset_name, uc.name AS pelaksana FROM asset_mutations m
               JOIN assets a ON a.id=m.asset_id LEFT JOIN user_cache uc ON uc.user_id=m.mutated_by
               WHERE DATE(m.mutated_at) BETWEEN ? AND ? ORDER BY m.id", [$from, $to])->fetchAll();
    $body .= tbl(['Tanggal', 'Kode', 'Aset', 'Dari (Unit/Lokasi/Pengguna)', 'Ke (Unit/Lokasi/Pengguna)', 'Alasan', 'Oleh'],
        array_map(fn($m) => [date('d-m-Y', strtotime($m['mutated_at'])), e($m['code']), e($m['asset_name']),
            e(($m['from_unit'] ?? '-') . ' / ' . ($m['from_location'] ?? '-') . ' / ' . ($m['from_user'] ?? '-')),
            e(($m['to_unit'] ?? '-') . ' / ' . ($m['to_location'] ?? '-') . ' / ' . ($m['to_user'] ?? '-')),
            e(mb_strimwidth($m['reason'] ?? '', 0, 40, '…')), e($m['pelaksana'] ?? '-')], $muts));
    break;

case 'riwayat_aset':
    $title = 'LAPORAN RIWAYAT PEMELIHARAAN / PERBAIKAN ASET';
    $ms = q("SELECT mn.*, a.code, a.name AS asset_name, uc.name AS teknisi FROM maintenances mn
             JOIN assets a ON a.id=mn.asset_id LEFT JOIN user_cache uc ON uc.user_id=mn.created_by
             WHERE mn.maint_date BETWEEN ? AND ? ORDER BY mn.maint_date", [$from, $to])->fetchAll();
    $totCost = 0; $rows = [];
    foreach ($ms as $m) {
        $totCost += (float)$m['cost'];
        $rows[] = [date('d-m-Y', strtotime($m['maint_date'])), e($m['maint_no']), e($m['code'] . ' — ' . $m['asset_name']),
                   e(mb_strimwidth($m['damage_desc'] ?? '', 0, 40, '…')), e($m['vendor'] ?? '-'),
                   e(mb_strimwidth($m['action_taken'] ?? '', 0, 40, '…')), ['v' => $money($m['cost']), 'cls' => 'right'], e($m['status'])];
    }
    $body .= tbl(['Tanggal', 'No.', 'Aset', 'Kerusakan', 'Vendor', 'Tindakan', 'Biaya (Rp)', 'Status'], $rows);
    $body .= '<p><b>Total biaya pemeliharaan periode ini: Rp ' . $money($totCost) . '</b></p>';
    break;

case 'atk_stok':
    $title = 'LAPORAN STOK ATK & PERLENGKAPAN';
    $sups = q("SELECT s.*, l.name AS lokasi FROM supplies s LEFT JOIN locations l ON l.id=s.location_id ORDER BY s.category, s.name")->fetchAll();
    $rows = [];
    foreach ($sups as $s) {
        $rows[] = [e($s['code']), e($s['name']), e(str_replace('_', ' ', $s['category'])), (int)$s['stock'] . ' ' . e($s['unit']),
                   (int)$s['min_stock'], $s['stock_minimum_warning'] ? '<b>⚠ MINIMUM</b>' : 'Aman', e($s['lokasi'] ?? 'Gudang Umum'),
                   ['v' => $money($s['last_price']), 'cls' => 'right']];
    }
    $body .= tbl(['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Min.', 'Peringatan', 'Lokasi', 'Harga Terakhir (Rp)'], $rows);
    break;

case 'atk_transaksi':
    $title = 'LAPORAN BARANG MASUK / KELUAR ATK';
    $txs = q("SELECT t.*, s.name, s.unit, uc.name AS pencatat FROM supply_transactions t
              JOIN supplies s ON s.id=t.supply_id LEFT JOIN user_cache uc ON uc.user_id=t.created_by
              WHERE DATE(t.created_at) BETWEEN ? AND ? ORDER BY t.id", [$from, $to])->fetchAll();
    $masuk = 0; $keluar = 0; $rows = [];
    foreach ($txs as $t) {
        if ($t['type'] === 'MASUK') $masuk += (int)$t['qty']; else $keluar += (int)$t['qty'];
        $rows[] = [date('d-m-Y H:i', strtotime($t['created_at'])), e($t['name']), e($t['type']),
                   (int)$t['qty'] . ' ' . e($t['unit']), e($t['from_unit'] ?? '-') . ' → ' . e($t['to_unit'] ?? '-'),
                   e($t['ref_type'] ?? ''), e($t['pencatat'] ?? '-'), e(mb_strimwidth($t['note'] ?? '', 0, 40, '…'))];
    }
    $body .= tbl(['Waktu', 'Barang', 'Jenis', 'Qty', 'Dari → Ke', 'Referensi', 'Oleh', 'Catatan'], $rows);
    $body .= '<p><b>Total masuk: ' . $masuk . ' unit · Total keluar: ' . $keluar . ' unit</b></p>';
    break;

case 'atk_distribusi':
    $title = 'LAPORAN DISTRIBUSI ATK PER UNIT';
    $reqs = q("SELECT r.*, uc.name AS pemohon FROM supply_requests r LEFT JOIN user_cache uc ON uc.user_id=r.user_id
               WHERE DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.nama_bidang, r.id", [$from, $to])->fetchAll();
    $rows = [];
    foreach ($reqs as $r) {
        $items = q("SELECT i.qty_requested, i.qty_approved, s.name FROM supply_request_items i JOIN supplies s ON s.id=i.supply_id WHERE i.request_id=?", [$r['id']])->fetchAll();
        $desc = implode(', ', array_map(fn($i) => $i['name'] . ' (' . (int)$i['qty_requested'] . '/' . (int)$i['qty_approved'] . ')', $items));
        $ho = $r['handover_id'] ? q("SELECT h.handover_no, h.handover_date FROM handovers h WHERE h.id=?", [$r['handover_id']])->fetch() : null;
        $rows[] = [e($r['request_no']), date('d-m-Y', strtotime($r['created_at'])), e($r['nama_bidang'] ?? '-'), e($r['pemohon'] ?? '-'),
                   e(mb_strimwidth($desc, 0, 60, '…')), e($ho ? $ho['handover_no'] . ' (' . date('d-m-Y', strtotime($ho['handover_date'])) . ')' : '-'), e($r['status'])];
    }
    $body .= tbl(['No. Permintaan', 'Tgl', 'Bidang/Unit', 'Pemohon', 'Item (diminta/disetujui)', 'Serah Terima', 'Status'], $rows);
    break;

case 'kerusakan':
    $title = 'LAPORAN KERUSAKAN';
    $drs = q("SELECT d.*, a.code, a.name AS asset_name, l.name AS lokasi, uc.name AS pelapor FROM damage_reports d
              LEFT JOIN assets a ON a.id=d.asset_id LEFT JOIN locations l ON l.id=d.location_id
              LEFT JOIN user_cache uc ON uc.user_id=d.reported_by
              WHERE DATE(d.report_date) BETWEEN ? AND ? ORDER BY d.id", [$from, $to])->fetchAll();
    $selesai = 0; $belum = 0; $rows = [];
    foreach ($drs as $d) {
        if ($d['status'] === 'SELESAI') $selesai++; else $belum++;
        $rows[] = [date('d-m-Y', strtotime($d['report_date'])), e($d['report_no']), e($d['code'] ?? $d['item_name'] ?? '-'),
                   e($d['lokasi'] ?? '-'), e($d['pelapor'] ?? '-'), e(mb_strimwidth($d['damage_desc'], 0, 50, '…')), e($d['status'])];
    }
    $body .= tbl(['Tanggal', 'No. Laporan', 'Aset/Barang', 'Lokasi', 'Pelapor', 'Kerusakan', 'Status'], $rows);
    $body .= '<p><b>Selesai: ' . $selesai . ' · Belum selesai: ' . $belum . ' · Total: ' . count($drs) . '</b></p>';
    $perUnit = q("SELECT COALESCE(a.unit,'(tanpa aset)') u, COUNT(*) c FROM damage_reports d LEFT JOIN assets a ON a.id=d.asset_id
                  WHERE DATE(d.report_date) BETWEEN ? AND ? GROUP BY u ORDER BY c DESC", [$from, $to])->fetchAll();
    $body .= '<h3 style="margin-top:14px">Kerusakan Berdasarkan Unit</h3>' . tbl(['Unit', 'Jumlah'], array_map(fn($r) => [e($r['u']), (int)$r['c']], $perUnit));
    break;

case 'kendaraan':
    $title = 'LAPORAN KENDARAAN & PEMINJAMAN';
    $vs = q("SELECT * FROM vehicles ORDER BY tnkb")->fetchAll();
    $body .= tbl(['TNKB', 'Merk/Type', 'Tahun', 'Kondisi', 'Lokasi', 'PIC', 'KM', 'Status'],
        array_map(fn($v) => [e($v['tnkb']), e($v['brand_model'] ?? '-'), e($v['year'] ?? '-'), e($v['condition_status']),
            e($v['location'] ?? '-'), e($v['pic'] ?? '-'), number_format((int)$v['last_km'], 0, ',', '.'), e($v['status'])], $vs));
    $loans = q("SELECT l.*, v.tnkb, v.brand_model, uc.name AS peminjam FROM vehicle_loans l
                JOIN vehicles v ON v.id=l.vehicle_id LEFT JOIN user_cache uc ON uc.user_id=l.user_id
                WHERE (l.start_date BETWEEN ? AND ? OR l.created_at BETWEEN ? AND ?) ORDER BY l.id",
               [$from, $to, $from, $to . ' 23:59:59'])->fetchAll();
    $body .= '<h3 style="margin-top:16px">Peminjaman / Penggunaan</h3>' . tbl(['No.', 'Tanggal', 'Kendaraan', 'Peminjam', 'Tujuan', 'Berangkat', 'Kembali', 'KM Awal→Akhir', 'BBM (Rp)', 'Status'],
        array_map(function ($l) use ($money) {
            return [e($l['loan_no']), date('d-m-Y', strtotime($l['created_at'])), e($l['tnkb'] . ' — ' . ($l['brand_model'] ?? '')),
                    e($l['peminjam'] ?? '-'), e(mb_strimwidth($l['purpose'] ?? '', 0, 35, '…')),
                    $l['start_date'] ? date('d-m-Y', strtotime($l['start_date'])) : '-',
                    $l['end_date'] ? date('d-m-Y', strtotime($l['end_date'])) : '-',
                    ($l['start_km'] !== null ? number_format((int)$l['start_km'], 0, ',', '.') : '-') . ' → ' . ($l['end_km'] !== null ? number_format((int)$l['end_km'], 0, ',', '.') : '-'),
                    ['v' => $money($l['fuel_cost']), 'cls' => 'right'], e($l['status'])];
        }, $loans));
    break;

case 'kendaraan_servis':
    $title = 'LAPORAN SERVIS & BIAYA PERAWATAN KENDARAAN';
    $svcs = q("SELECT s.*, v.tnkb FROM vehicle_services s JOIN vehicles v ON v.id=s.vehicle_id
               WHERE s.service_date BETWEEN ? AND ? ORDER BY v.tnkb, s.service_date", [$from, $to])->fetchAll();
    $tot = 0; $rows = [];
    foreach ($svcs as $s) {
        $tot += (float)$s['cost'];
        $rows[] = [date('d-m-Y', strtotime($s['service_date'])), e($s['tnkb']), e($s['service_type'] ?? '-'),
                   number_format((int)$s['km_at_service'], 0, ',', '.'), e($s['vendor'] ?? '-'),
                   ['v' => $money($s['cost']), 'cls' => 'right'], e(mb_strimwidth($s['note'] ?? '', 0, 40, '…'))];
    }
    $body .= tbl(['Tanggal', 'TNKB', 'Jenis Servis', 'KM', 'Vendor/Bengkel', 'Biaya (Rp)', 'Catatan'], $rows);
    $body .= '<p><b>Total biaya perawatan periode ini: Rp ' . $money($tot) . '</b></p>';
    $perVeh = q("SELECT v.tnkb, COUNT(s.id) c, IFNULL(SUM(s.cost),0) v_total FROM vehicles v LEFT JOIN vehicle_services s ON s.vehicle_id=v.id AND s.service_date BETWEEN ? AND ?
                 GROUP BY v.id ORDER BY v_total DESC", [$from, $to])->fetchAll();
    $body .= '<h3 style="margin-top:14px">Rekap per Kendaraan</h3>' . tbl(['TNKB', 'Jumlah Servis', 'Total Biaya (Rp)'],
        array_map(fn($r) => [e($r['tnkb']), (int)$r['c'], ['v' => $money($r['v_total']), 'cls' => 'right']], $perVeh));
    break;

case 'aset_lokasi':
    $loc = q("SELECT * FROM locations WHERE id=?", [$locId])->fetch();
    $title = 'DAFTAR ASET / INVENTARIS RUANGAN';
    $sub = ($loc ? e($loc['name']) . ' — ' : '') . 'Sistem Pengadaan LJU';
    $assets = q("SELECT a.*, uc.name AS pengguna FROM assets a LEFT JOIN user_cache uc ON uc.user_id=a.user_id
                 WHERE a.location_id=? AND a.status <> 'DIHAPUS' ORDER BY a.category, a.code", [$locId])->fetchAll();
    $totVal = 0; $rows = [];
    foreach ($assets as $a) {
        $totVal += (float)$a['value'];
        $rows[] = [e($a['code']), e($a['name']), ucfirst(e($a['category'])), e($a['brand'] ?? '') . ' ' . e($a['model'] ?? ''),
                   e($a['pengguna'] ?? '-'), date('d-m-Y', strtotime($a['purchase_date'] ?: 'now')),
                   ['v' => $money($a['value']), 'cls' => 'right'], e($a['condition_status']), e($a['status'])];
    }
    $body .= tbl(['Kode Aset', 'Nama', 'Kategori', 'Merk/Model', 'Pengguna', 'Tgl Perolehan', 'Nilai (Rp)', 'Kondisi', 'Status'], $rows);
    $body .= '<p><b>Jumlah: ' . count($assets) . ' item · Total nilai: Rp ' . $money($totVal) . '</b></p>';
    $body .= '<table width="100%" style="margin-top:36px"><tr><td width="50%"></td><td width="50%" align="center" style="font-size:10px">Lampung, ' . date('d F Y') . '<br>Petugas Pendataan<br><br><br><b>( ................................ )</b></td></tr></table>';
    break;
}

$html = pdf_wrap(pdf_doc_header($title, $sub) . $body .
    '<p class="muted" style="margin-top:24px">Dicetak oleh: ' . e($_SESSION['user_name'] ?? '-') . ' · PT. Lampung Jasa Utama · ' . e(APP_URL) . '</p>');
pdf_output($html, 'Laporan_' . ucfirst($j) . '_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to), true);
