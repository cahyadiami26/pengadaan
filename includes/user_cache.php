<?php
/**
 * Sinkronisasi & utilitas data pegawai (tabel users di DB SSO -> user_cache di DB aplikasi).
 */

function sync_user_cache(): int
{
    $count = 0;
    try {
        // users berada di database berbeda dalam satu mesin yang sama.
        $rows = ssoDb()->query("SELECT id, nik, name, email, position, division, department, avatar, is_active FROM users")->fetchAll();
        $upsert = db()->prepare(
            "INSERT INTO user_cache (user_id, nik, name, email, phone, position, nama_bidang, nama_divisi, avatar, is_active)
             VALUES (:id,:nik,:name,:email,'',:position,:division,:department,:avatar,:active)
             ON DUPLICATE KEY UPDATE nik=:nik, name=:name, email=:email, position=:position,
               nama_bidang=:division, nama_divisi=:department, avatar=:avatar, is_active=:active"
        );
        foreach ($rows as $r) {
            $upsert->execute([
                ':id' => $r['id'], ':nik' => $r['nik'], ':name' => $r['name'], ':email' => $r['email'],
                ':position' => $r['position'], ':division' => $r['division'], ':department' => $r['department'],
                ':avatar' => $r['avatar'], ':active' => $r['is_active'] ?? 1,
            ]);
            $count++;
        }
        // hapus cache user yang sudah tidak ada di users
        try {
            $ids = array_map(fn($r) => (int)$r['id'], $rows);
            if ($ids) {
                $in = implode(',', $ids);
                db()->exec("DELETE FROM user_cache WHERE user_id NOT IN ($in)");
            }
        } catch (Throwable $e) {}
    } catch (Throwable $e) {
        // Jika akses lintas-DB gagal dan kedua tabel berada di schema yang sama, coba query langsung.
        try {
            $exists = db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name='users' AND table_schema=DATABASE()")->fetchColumn();
            if ($exists) {
                db()->query("REPLACE INTO user_cache (user_id, nik, name, email, position, nama_bidang, nama_divisi, avatar, is_active)
                             SELECT id, nik, name, email, position, division, department, avatar, is_active FROM users");
                $count = (int)db()->query("SELECT COUNT(*) FROM user_cache")->fetchColumn();
            }
        } catch (Throwable $e2) {}
    }
    return $count;
}

/** Ambil nama user dari cache (fallback ke session/ID) */
function user_name_of(?int $userId): string
{
    if (!$userId) return '-';
    if ((int)($_SESSION['user_id'] ?? 0) === $userId) return $_SESSION['user_name'] ?? '-';
    try {
        $n = q("SELECT name FROM user_cache WHERE user_id=?", [$userId])->fetchColumn();
        if ($n) return $n;
    } catch (Throwable $e) {}
    try {
        $st = ssoDb()->prepare("SELECT name FROM users WHERE id=?");
        $st->execute([$userId]);
        $n = $st->fetchColumn();
        if ($n) return $n;
    } catch (Throwable $e) {}
    return 'User #' . $userId;
}

/** Daftar user aktif untuk dropdown pilihan penerima serah terima dsb. */
function all_users_for_select(): array
{
    try {
        $rows = q("SELECT user_id, name, nik FROM user_cache WHERE is_active=1 ORDER BY name")->fetchAll();
        if ($rows) return array_column($rows, 'name', 'user_id');
    } catch (Throwable $e) {}
    try {
        $rows = ssoDb()->query("SELECT id, name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
        return array_column($rows, 'name', 'id');
    } catch (Throwable $e) {}
    return [];
}
