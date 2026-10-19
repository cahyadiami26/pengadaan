<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Data Lokasi';
$active = 'lokasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $parent = ($_POST['parent_id'] !== '') ? (int)$_POST['parent_id'] : null;
        $type = in_array($_POST['type'], ['gedung', 'lantai', 'ruang']) ? $_POST['type'] : 'ruang';
        if ($id) {
            q("UPDATE locations SET parent_id=?, type=?, name=?, code=?, description=? WHERE id=?",
              [$parent, $type, trim($_POST['name']), trim($_POST['code'] ?? ''), trim($_POST['description'] ?? ''), $id]);
        } else {
            q("INSERT INTO locations (parent_id,type,name,code,description) VALUES (?,?,?,?,?)",
              [$parent, $type, trim($_POST['name']), trim($_POST['code'] ?? ''), trim($_POST['description'] ?? '')]);
        }
        flash_set('success', 'Lokasi disimpan.');
    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        try {
            q("DELETE FROM locations WHERE id=?", [$id]);
            flash_set('success', 'Lokasi dihapus.');
        } catch (Throwable $e) {
            q("UPDATE locations SET is_active=0 WHERE id=?", [$id]);
            flash_set('warning', 'Lokasi memiliki data terkait, ditandai nonaktif.');
        }
    }
    header('Location: lokasi.php'); exit;
}

$locs = q("SELECT * FROM locations ORDER BY type, name")->fetchAll();
$byParent = [];
foreach ($locs as $l) $byParent[$l['parent_id'] ?? 0][] = $l;

function render_tree(array $byParent, ?int $parent, array $locsById): void
{
    $children = $byParent[$parent ?? 0] ?? [];
    if (!$children) return;
    echo '<ul>';
    foreach ($children as $c) {
        $icon = ['gedung' => '🏢', 'lantai' => '🪜', 'ruang' => '🚪'][$c['type']] ?? '📍';
        $cnt = count_rows("SELECT COUNT(*) FROM assets WHERE location_id=" . (int)$c['id']);
        echo '<li><span class="node">' . $icon . ' <b>' . e($c['name']) . '</b>';
        if ($c['code']) echo '<small style="color:var(--muted)">(' . e($c['code']) . ')</small>';
        if ($cnt) echo '<span class="badge badge-info">' . $cnt . ' aset</span>';
        echo '<a href="?edit=' . (int)$c['id'] . '" class="btn btn-sm btn-outline">✏️</a> ';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Hapus lokasi ini?\')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="btn btn-sm btn-danger">🗑</button></form>';
        if (!$c['is_active']) echo ' <span class="badge badge-secondary">NONAKTIF</span>';
        echo '</span>';
        render_tree($byParent, $c['id'], $locsById);
        echo '</li>';
    }
    echo '</ul>';
}

$locsById = [];
foreach ($locs as $l) $locsById[$l['id']] = $l;
$edit = isset($_GET['edit']) ? ($locsById[(int)$_GET['edit']] ?? null) : null;

require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
  <div class="card">
    <h3>📍 Struktur Lokasi</h3>
    <div class="tree"><?php render_tree($byParent, null, $locsById); ?></div>
    <?php if (!$locs): ?><div class="empty-state"><div class="big">📍</div>Belum ada lokasi.</div><?php endif; ?>
  </div>

  <div class="card">
    <h3><?= $edit ? '✏️ Edit Lokasi' : '➕ Tambah Lokasi' ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-row">
        <div class="form-group">
          <label>Jenis *</label>
          <select name="type" required>
            <?php foreach (['gedung' => 'Gedung', 'lantai' => 'Lantai', 'ruang' => 'Ruang'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($edit['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Induk (parent)</label>
          <select name="parent_id">
            <option value="">— Tidak ada —</option>
            <?php foreach ($locs as $l): if ($l['type'] === 'ruang') continue; if ($edit && $l['id'] == $edit['id']) continue; ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($edit['parent_id'] ?? null) == $l['id'] ? 'selected' : '' ?>>
              <?= e(str_repeat('— ', min(2, [ 'gedung'=>0,'lantai'=>1,'ruang'=>2][$l['type']]))) . e($l['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Nama Lokasi *</label><input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="cth: Ruang IT"></div>
        <div class="form-group"><label>Kode</label><input type="text" name="code" value="<?= e($edit['code'] ?? '') ?>" placeholder="cth: IT (untuk kode aset)"></div>
      </div>
      <div class="form-group"><label>Keterangan</label><textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
      <button class="btn btn-primary">💾 Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline" href="lokasi.php">Batal</a><?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
