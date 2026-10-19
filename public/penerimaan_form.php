<?php
/**
 * Form Pencatatan Penerimaan Barang/Jasa.
 * Menyimpan ke receptions + reception_items via penerimaan.php (action=save).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
$pageTitle = 'Catat Penerimaan'; $active = 'penerimaan';

$prePlan = (int)($_GET['plan_id'] ?? 0);
$preNeed = (int)($_GET['need_id'] ?? 0);

// Rencana pengadaan / kebutuhan yang sudah dalam proses (siap diterima)
$plans = q("SELECT id, plan_no, requesting_units FROM procurement_plans
            WHERE status IN ('DALAM_PROSES_PENGADAAN','FINAL_KEUANGAN','DITERIMA') ORDER BY id DESC")->fetchAll();
$needs = q("SELECT id, request_no, nama_bidang FROM needs_requests
            WHERE status IN ('DALAM_PROSES_PENGADAAN','FINALISASI_KEUANGAN','DITERIMA') ORDER BY id DESC")->fetchAll();

// Item bawaan dari rencana/kebutuhan terpilih
$prefill = [];
if ($prePlan) {
    foreach (q("SELECT item_name, category, qty, unit, est_price FROM procurement_plan_items WHERE plan_id=?", [$prePlan])->fetchAll() as $i) {
        $prefill[] = $i;
    }
} elseif ($preNeed) {
    foreach (q("SELECT item_name, category, qty, unit, est_price FROM needs_items WHERE need_id=?", [$preNeed])->fetchAll() as $i) {
        $prefill[] = $i;
    }
}
$cats = ['asset','inventaris','peralatan_kantor','sparepart','barang_habis_pakai','jasa'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:980px">
<h3>📦 Catat Penerimaan Barang/Jasa</h3>
<form method="post" action="penerimaan.php">
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
  <div class="form-row">
    <div class="form-group"><label>Tanggal Penerimaan *</label><input type="date" name="receive_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="form-group"><label>Dari Rencana Pengadaan</label><select name="plan_id">
      <option value="">- Tidak terkait -</option>
      <?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $prePlan===(int)$p['id']?'selected':'' ?>><?= e($p['plan_no']) ?> — <?= e($p['requesting_units'] ?? '') ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label>Atau Dari Kebutuhan</label><select name="need_id">
      <option value="">- Tidak terkait -</option>
      <?php foreach ($needs as $n): ?><option value="<?= (int)$n['id'] ?>" <?= $preNeed===(int)$n['id']?'selected':'' ?>><?= e($n['request_no']) ?> — <?= e($n['nama_bidang'] ?? '') ?></option><?php endforeach; ?>
    </select></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Supplier / Penyedia</label><input type="text" name="supplier" placeholder="PT/CV ..."></div>
    <div class="form-group"><label>No. Invoice / DO</label><input type="text" name="invoice_no"></div>
    <div class="form-group"><label>Status Penerimaan</label><select name="status">
      <option value="DITERIMA">DITERIMA</option><option value="DITERIMA SEBAGIAN">DITERIMA SEBAGIAN</option><option value="DITOLAK">DITOLAK</option>
    </select></div>
  </div>

  <fieldset style="border:1px solid var(--line);border-radius:10px;padding:12px;margin:10px 0">
    <legend style="font-weight:600;font-size:13px;color:var(--navy)">✅ Checklist Pemeriksaan</legend>
    <div class="form-row">
      <label class="check-line"><input type="checkbox" name="chk_qty" checked> Jumlah sesuai</label>
      <label class="check-line"><input type="checkbox" name="chk_spec" checked> Spesifikasi sesuai</label>
      <label class="check-line"><input type="checkbox" name="chk_condition" checked> Kondisi baik</label>
      <label class="check-line"><input type="checkbox" name="chk_docs" checked> Dokumen lengkap</label>
    </div>
  </fieldset>

  <h4 style="margin:14px 0 8px">Item yang Diterima</h4>
  <div class="table-wrap"><table class="tbl" id="tblItems">
    <tr><th>Nama Barang/Jasa</th><th>Kategori</th><th class="num">Qty Pesan</th><th class="num">Qty Terima</th><th>Satuan</th><th class="num">Harga (Rp)</th><th>Catatan</th><th></th></tr>
    <tbody></tbody>
  </table></div>
  <button type="button" class="btn btn-sm btn-outline" style="margin-top:8px" onclick="addRow()">➕ Tambah Item</button>

  <div class="form-group" style="margin-top:12px"><label>Catatan</label><textarea name="note" rows="2"></textarea></div>
  <div style="display:flex;gap:10px;margin-top:10px"><button class="btn btn-primary">💾 Simpan Penerimaan</button><a class="btn btn-outline" href="penerimaan.php">Batal</a></div>
</form>
</div>
<script>
const CATS = <?= json_encode($cats) ?>;
const PREFILL = <?= json_encode($prefill) ?>;
function esc(s){ return String(s??'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function addRow(v = {}) {
  const tr = document.createElement('tr');
  const catOpts = CATS.map(c => `<option ${v.category===c?'selected':''}>${c}</option>`).join('');
  tr.innerHTML = `
    <td><input type="text" name="item_name[]" value="${esc(v.item_name)}" required style="min-width:180px"></td>
    <td><select name="category[]">${catOpts}</select></td>
    <td><input type="number" name="qty_ordered[]" min="0" value="${v.qty ?? 1}" style="width:80px"></td>
    <td><input type="number" name="qty_received[]" min="0" value="${v.qty ?? 1}" style="width:80px"></td>
    <td><input type="text" name="unit[]" value="${esc(v.unit ?? 'unit')}" style="width:70px"></td>
    <td><input type="number" name="price[]" min="0" step="100" value="${Math.round(v.est_price ?? 0)}" style="width:110px"></td>
    <td><input type="text" name="item_note[]" value="" style="min-width:120px"></td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">✖</button></td>`;
  document.querySelector('#tblItems tbody').appendChild(tr);
}
PREFILL.forEach(addRow);
if (!PREFILL.length) addRow();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
