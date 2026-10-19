<?php
/**
 * Fungsi bantu global.
 */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl($d, bool $withTime = false): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    return date('d-m-Y' . ($withTime ? ' H:i' : ''), $ts);
}

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][$type] = $msg;
}

function flash(): string
{
    $out = '';
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $msg) {
            $cls = ['success' => 'alert-success', 'danger' => 'alert-danger', 'warning' => 'alert-warning', 'info' => 'alert-info'][$type] ?? 'alert-info';
            $out .= '<div class="alert ' . $cls . '">' . e($msg) . '</div>';
        }
        unset($_SESSION['flash']);
    }
    return $out;
}

/** Inisial nama (maks 2 huruf). */
function initials_of(string $name): string
{
    $initials = '';
    $parts = preg_split('/\s+/', trim($name));
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') $initials .= strtoupper(mb_substr($p, 0, 1));
    }
    return $initials ?: '?';
}

/**
 * Avatar user: pakai file dari https://dms.lju.co.id/uploads/avatars/{avatar},
 * fallback ke inisial nama jika tidak ada file avatar / gambar gagal dimuat.
 * Ukuran responsif via CSS (.avatar + --av): mengecil otomatis di layar sempit,
 * dibatasi maks 40px agar tidak terlihat terlalu besar.
 */
function avatar_html(?string $avatar, string $name, int $size = 36): string
{
    $initials = initials_of($name);
    $size = max(20, min(40, $size));
    $fs = max(10, (int)round($size * 0.38));
    // Gunakan var(--av, Npx) sebagai FALLBACK saja; CSS dapat menimpa --av
    // secara responsif tanpa diblokir oleh inline style.
    $wrapStyle = 'display:inline-flex;position:relative;width:var(--av,' . $size . 'px);height:var(--av,' . $size . 'px);';
    $iniStyle  = 'width:var(--av,' . $size . 'px);height:var(--av,' . $size . 'px);font-size:' . $fs . 'px;';
    $ini = '<span class="avatar-initials" style="' . $iniStyle . '">' . e($initials) . '</span>';
    if ($avatar) {
        $src = e(AVATAR_BASE_URL . rawurlencode($avatar));
        return '<span class="avatar-wrap" style="' . $wrapStyle . '">'
             . $ini
             . '<img class="avatar-photo" src="' . $src . '" alt="' . e($name) . '" title="' . e($name) . '"'
             . ' onload="this.style.display=\'block\';this.previousElementSibling.style.display=\'none\'"'
             . ' onerror="this.remove()"></span>';
    }
    return str_replace('class="avatar-initials"', 'class="avatar-initials avatar"', $ini);
}

/** Badge status berwarna */
function status_badge(string $status): string
{
    $map = [
        'DRAFT' => 'secondary', 'DIAJUKAN' => 'info', 'DIVERIFIKASI' => 'primary',
        'DISETUJUI' => 'success', 'DITOLAK' => 'danger', 'DALAM PROSES PENGADAAN' => 'warning',
        'MASUK RENCANA PENGADAAN' => 'warning', 'DITERIMA' => 'success', 'SEBAGIAN' => 'warning',
        'DITERIMA SEBAGIAN' => 'warning',
        'AKTIF' => 'success', 'RUSAK' => 'danger', 'DIPINDAHKAN' => 'info', 'DIHAPUS' => 'dark', 'HILANG' => 'danger',
        'BAIK' => 'success', 'RUSAK RINGAN' => 'warning', 'RUSAK BERAT' => 'danger',
        'TERSEDIA' => 'success', 'DIPINJAM' => 'info', 'DIGUNAKAN' => 'primary', 'SERVICE' => 'warning',
        'DILAPORKAN' => 'info', 'DIAGNOSIS' => 'primary', 'DALAM PERBAIKAN' => 'warning', 'SELESAI' => 'success',
        'MENUNGGU' => 'secondary', 'DIPROSES' => 'warning', 'DISTRIBUTED' => 'success', 'TERDISTRIBUSI' => 'success',
        'BELUM DITERIMA' => 'danger', 'OPEN' => 'info', 'ASSIGNED' => 'primary', 'VERIFIKASI' => 'primary',
        'PENUGASAN' => 'info', 'PEMERIKSAAN' => 'warning', 'CLOSED' => 'success',
        'JANJI' => 'secondary', 'PENGERJAAN' => 'primary', 'BATAL' => 'dark', 'NON OPERASIONAL' => 'dark',
        'DISETUJUI_KADIV' => 'primary', 'DISETUJUI_DIREKTUR' => 'success', 'FINALISASI_KEUANGAN' => 'warning',
        'FINAL_KEUANGAN' => 'warning', 'DALAM_PROSES_PENGADAAN' => 'warning', 'MASUK_RENCANA_PENGADAAN' => 'warning',
        'BERITA_ACARA' => 'primary', 'TIDAK_TERSEDIA' => 'danger', 'DIPINJAM' => 'info', 'DIKEMBALIKAN' => 'success',
    ];
    $cls = $map[strtoupper($status)] ?? 'secondary';
    return '<span class="badge badge-' . $cls . '">' . e($status) . '</span>';
}

/** Generate kode aset: AST-{RUANG}-{BIDANG/DIVISI}-{TAHUN}-{SEQ} */
function generate_asset_code(string $roomCode, string $unitCode): string
{
    $year = date('Y');
    $prefix = 'AST-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $roomCode)) . '-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $unitCode)) . '-' . $year . '-';
    $stmt = q("SELECT code FROM assets WHERE code LIKE ? ORDER BY code DESC LIMIT 1", [$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr($last, -6)) + 1 : 1;
    return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
}

/** QR Code via library online (api.qrserver.com), disimpan dalam uploads/qr */
function generate_qr(string $text): string
{
    if (!is_dir(QR_PATH)) mkdir(QR_PATH, 0775, true);
    $filename = 'qr_' . md5($text) . '.png';
    $path = QR_PATH . '/' . $filename;
    if (!file_exists($path)) {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($text);
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'LJU-Pengadaan/1.0']]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) return ''; // gagal: render langsung lewat <img src=url>
        file_put_contents($path, $data);
    }
    return 'uploads/qr/' . $filename;
}

/** URL gambar QR: file lokal jika sudah ada, selain itu render langsung dari library online */
function qr_img_src(string $text): string
{
    $local = generate_qr($text);
    return $local ?: ('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($text));
}

/** Generate nomor dokumen: PREFIX-YYYY-0001 */
function gen_no(string $table, string $column, string $prefix): string
{
    $p = $prefix . '-' . date('Y') . '-';
    $last = q("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1", [$p . '%'])->fetchColumn();
    $seq = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $p . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/** Count helper */
function count_rows(string $sql, array $params = []): int
{
    try { return (int)q($sql, $params)->fetchColumn(); } catch (Throwable $e) { return 0; }
}

/** Hitung ulang flag peringatan stok minimum (dipanggil setiap stok berubah) */
function update_stock_warning(?int $supplyId = null): void
{
    if ($supplyId) {
        q("UPDATE supplies SET stock_minimum_warning = (min_stock > 0 AND stock <= min_stock) WHERE id=?", [$supplyId]);
    } else {
        db()->exec("UPDATE supplies SET stock_minimum_warning = (min_stock > 0 AND stock <= min_stock)");
    }
}

/** Simpan jumlah notifikasi pekerjaan ke cache agar badge topbar aman dipakai semua role */
function refresh_notification_cache(): void
{
    try {
        $n = 0;
        $n += count_rows("SELECT COUNT(*) FROM supply_requests WHERE status IN ('MENUNGGU','DIPROSES')");
        $n += count_rows("SELECT COUNT(*) FROM damage_reports WHERE status = 'DILAPORKAN'");
        $n += count_rows("SELECT COUNT(*) FROM procurement_plans WHERE status = 'DALAM_PROSES_PENGADAAN'");
        $n += count_rows("SELECT COUNT(*) FROM vehicles WHERE next_service_km > 0 AND last_km >= next_service_km - 1500 AND status <> 'SERVICE'");
        $_SESSION['notif_count'] = $n;
    } catch (Throwable $e) { /* abaikan */ }
}

/** Jumlah notifikasi pekerjaan untuk badge topbar */
function notification_badge_count()
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $n = 0;
    $n += count_rows("SELECT COUNT(*) FROM supply_requests WHERE status IN ('MENUNGGU','DIPROSES')");
    $n += count_rows("SELECT COUNT(*) FROM damage_reports WHERE status = 'DILAPORKAN'");
    $n += count_rows("SELECT COUNT(*) FROM receptions r WHERE r.status IN ('DITERIMA','DITERIMA SEBAGIAN') AND EXISTS (SELECT 1 FROM procurement_plan_items i WHERE i.plan_id = r.plan_id) AND r.status <> 'DITERIMA'");
    $n += count_rows("SELECT COUNT(*) FROM vehicles WHERE next_service_km > 0 AND last_km >= next_service_km - 1500");
    $month = date('Y-m');
    $n += count_rows("SELECT COUNT(*) FROM stock_opnames WHERE DATE_FORMAT(opname_date,'%Y-%m') = ?", [$month]) === 0 ? 1 : 0;
    return $cached = $n;
}

/** Daftar pesan notifikasi pekerjaan untuk dashboard */
function work_notifications(): array
{
    $out = [];
    $v = count_rows("SELECT COUNT(*) FROM supply_requests WHERE status IN ('MENUNGGU','DIPROSES')");
    if ($v) $out[] = ['⚠', $v . ' Permintaan ATK menunggu distribusi', 'perlengkapan.php'];
    $v = count_rows("SELECT COUNT(*) FROM damage_reports WHERE status = 'DILAPORKAN'");
    if ($v) $out[] = ['⚠', $v . ' Laporan kerusakan belum ditindaklanjuti', 'kerusakan.php'];
    $v = count_rows("SELECT COUNT(*) FROM vehicles WHERE next_service_km > 0 AND last_km >= next_service_km - 1500 AND status <> 'SERVICE'");
    if ($v) $out[] = ['🚗', $v . ' Kendaraan mendekati jadwal servis', 'perawatan_kendaraan.php'];
    $v = count_rows("SELECT COUNT(*) FROM procurement_plans WHERE status = 'DALAM_PROSES_PENGADAAN'");
    if ($v) $out[] = ['📦', $v . ' Pengadaan belum diterima', 'penerimaan.php'];
    $month = date('Y-m');
    $done = count_rows("SELECT COUNT(*) FROM stock_opnames WHERE DATE_FORMAT(opname_date,'%Y-%m') = ?", [$month]);
    if (!$done) $out[] = ['🔍', 'Stock opname bulan ini belum dilakukan', 'stock_opname.php'];
    return $out;
}

/** Upload file aman ke uploads/foto */
function upload_photo(array $file, string $prefix = 'img'): ?string
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if (!is_dir(FOTO_PATH)) mkdir(FOTO_PATH, 0775, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], FOTO_PATH . '/' . $name)) {
        return 'uploads/foto/' . $name;
    }
    return null;
}

/** Simpan tanda tangan canvas (data URL png) ke folder uploads */
function save_signature_png(?string $dataUrl): ?string
{
    if (!$dataUrl || strpos($dataUrl, 'base64,') === false) return null;
    if (!is_dir(FOTO_PATH)) mkdir(FOTO_PATH, 0775, true);
    $raw = base64_decode(substr($dataUrl, strpos($dataUrl, 'base64,') + 7));
    if ($raw === false) return null;
    $name = 'ttd_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents(FOTO_PATH . '/' . $name, $raw);
    return 'uploads/foto/' . $name;
}

function require_role(array $roles): void
{
    if (!in_array($_SESSION['app_role'] ?? 'pegawai', $roles, true)) {
        http_response_code(403);
        echo '<div class="empty-state"><h3>Akses ditolak</h3><p>Anda tidak memiliki hak akses ke halaman ini.</p></div>';
        exit;
    }
}

function is_in_role(array $roles): bool
{
    return in_array($_SESSION['app_role'] ?? 'pegawai', $roles, true);
}

function hcs($v): string { return e($v); }
