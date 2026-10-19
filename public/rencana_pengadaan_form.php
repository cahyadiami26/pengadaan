<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
$pageTitle = 'Form Rencana Pengadaan';
$active = 'rencana_pengadaan';

$id = (int)($_GET['id'] ?? 0);
$row = $id ? q("SELECT * FROM procurement_plans WHERE id=?", [$id])->fetch() : null;
$items = $row ? q("SELECT * FROM procurement_plan_items WHERE plan_id=?", [$id])->fetchAll() : [];
$cats = ['asset'=>'Asset','inventaris'=>'Inventaris','peralatan_kantor'=>'Peralatan Kantor','sparepart'=>'Sparepart','barang_habis_pakai'=>'Barang Habis Pakai','jasa'=>'Jasa'];

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3><?= $row ? '✏️ Edit Rencana ' . e($row['plan_no']) : '➕ Rencana Pengadaan Baru' ?></h3>
  <form method="post" action="rencana_pengadaan.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group"><label>Tanggal Rencana *</label><input type="date" name="plan_date" required value="<?= e($row['plan_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="form-group"><label>Unit Pemohon</label><input type="text" name="requesting_units" value="<?= e($row['requesting_units'] ?? '') ?>" placeholder="cth: Keuangan, IT, Operasional"></div>
      <div class="form-group"><label>Anggaran / Estimasi Total</label><input type="number" name="budget" min="0" value="<?= (float)($row['budget'] ?? 0) ?>"></div>
      <div class="form-group"><label>Prioritas</label>
        <select name="priority"><?php foreach (['Rendah','Sedang','Tinggi','Sangat Tinggi'] as $p): ?>
          <option <?= ($row['priority'] ?? '')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea name="note" rows="2"><?= e($row['note'] ?? '') ?></textarea></div>

    <label>Item Barang/Jasa</label>
    <div class="table-wrap"><table class="tbl" id="itemTable">
      <tr><th style="width:32%">Nama Barang/Jasa</th><th>Kategori</th><th style="width:80px">Jumlah</th><th style="width:90px">Satuan</th><th style="width:150px">Estimasi Harga</th><th style="width:40px"></th></tr>
      <?php if (!$items): ?><tr>
        <td><input type="text" name="item_name[]"></td>
        <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
        <td><input type="number" name="qty[]" min="1" value="1"></td><td><input type="text" name="unit[]" value="unit"></td>
        <td><input type="number" name="est_price[]" min="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td></tr>
      <?php else: foreach ($items as $it): ?><tr>
        <td><input type="text" name="item_name[]" value="<?= e($it['item_name']) ?>"></td>
        <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>" <?= $it['category']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></td>
        <td><input type="number" name="qty[]" min="1" value="<?= (int)$it['qty'] ?>"></td><td><input type="text" name="unit[]" value="<?= e($it['unit']) ?>"></td>
        <td><input type="number" name="est_price[]" min="0" value="<?= (float)$it['est_price'] ?>"></td>
        <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td></tr>
      <?php endforeach; endif; ?>
    </table></div>
    <button type="button" class="btn btn-sm btn-outline" style="margin-top:8px" id="btnAddItem">➕ Tambah Item</button>
    <template id="itemRowTpl"><tr>
      <td><input type="text" name="item_name[]"></td>
      <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
      <td><input type="number" name="qty[]" min="1" value="1"></td><td><input type="text" name="unit[]" value="unit"></td>
      <td><input type="number" name="est_price[]" min="0"></td>
      <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td></tr></template>
    <script>document.getElementById('btnAddItem').onclick=function(){document.getElementById('itemTable').insertAdjacentHTML('beforeend',document.getElementById('itemRowTpl').innerHTML);}</script>

    <div style="margin-top:16px"><button class="btn btn-primary">💾 Simpan Rencana</button>
    <a class="btn btn-outline" href="rencana_pengadaan.php">Batal</a></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
