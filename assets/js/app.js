/* App JS: avatar fallback, modal, signature canvas */
document.addEventListener('DOMContentLoaded', function () {
  // Avatar: jika gambar gagal load, parent pakai inisial (sudah ada di markup),
  // jika berhasil, sembunyikan inisial.
  document.querySelectorAll('.avatar-img img').forEach(function (img) {
    img.addEventListener('load', function () {
      var chip = img.closest('.avatar-wrap') || img.parentElement;
      // wrap both spans
      var prev = img.parentElement; // .avatar-img span
      var next = prev.nextElementSibling; // .avatar-initials
      if (next && next.classList.contains('avatar-initials')) next.style.display = 'none';
      prev.style.display = 'inline-flex';
    });
    img.addEventListener('error', function () {
      img.parentElement.style.display = 'none';
    });
  });

  // Modal open/close
  window.openModal = function (id) { document.getElementById(id).classList.add('show'); };
  window.closeModal = function (id) { document.getElementById(id).classList.remove('show'); };
  document.querySelectorAll('.modal-backdrop').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('show'); });
  });

  initSignatureCanvases();

  // Enhanced select: pencarian + dropdown rapi untuk select dengan banyak opsi.
  // Implementasi ringan tanpa jQuery/Select2 agar tidak ada error "$ is not defined".
  // - Auto: <select> di dalam .form-group dengan > 8 opsi (mis. pilih pegawai/user).
  // - Manual: tambahkan class .js-select pada <select>, atau .js-select-plain
  //   untuk dropdown rapi tanpa kotak pencarian.
  document.querySelectorAll('select').forEach(function (sel) {
    if (sel.classList.contains('js-select-plain')) { initEnhancedSelect(sel, false); return; }
    var searchable = sel.classList.contains('js-select') ||
      (sel.closest('.form-group') && !sel.multiple && sel.options.length > 8);
    if (searchable) initEnhancedSelect(sel, true);
  });
});

/* Fuzzy match sederhana: karakter query harus muncul berurutan di target */
function fuzzyMatch(text, q) {
  text = (text || '').toLowerCase();
  q = (q || '').toLowerCase();
  var ti = 0;
  for (var qi = 0; qi < q.length; qi++) {
    ti = text.indexOf(q[qi], ti);
    if (ti === -1) return false;
    ti++;
  }
  return true;
}

/* Dropdown dengan kotak pencarian untuk elemen <select>.
   Nilai tetap disinkronkan ke <select> asli sehingga form submission tidak berubah. */
function initEnhancedSelect(sel, withSearch) {
  if (sel.dataset.enhanced === '1') return;
  sel.dataset.enhanced = '1';
  if (typeof withSearch === 'undefined') withSearch = true;
  if (sel.multiple || sel.disabled) return; // biarkan native untuk multi-select

  var wrap = document.createElement('div');
  wrap.className = 'es-wrap';
  sel.parentNode.insertBefore(wrap, sel);
  wrap.appendChild(sel);
  sel.classList.add('es-native');

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'es-btn';
  btn.setAttribute('aria-haspopup', 'listbox');
  btn.innerHTML = '<span class="es-label"></span><span class="es-arrow">&#9662;</span>';
  wrap.appendChild(btn);

  var panel = document.createElement('div');
  panel.className = 'es-panel';
  panel.hidden = true;
  var search = null;
  if (withSearch) {
    search = document.createElement('input');
    search.type = 'text';
    search.className = 'es-search';
    search.placeholder = 'Ketik untuk mencari…';
    panel.appendChild(search);
  }
  var list = document.createElement('div');
  list.className = 'es-list';
  list.setAttribute('role', 'listbox');
  panel.appendChild(list);
  wrap.appendChild(panel);

  var labelEl = btn.querySelector('.es-label');

  function options() {
    return Array.prototype.slice.call(sel.options);
  }
  function syncLabel() {
    var o = sel.options[sel.selectedIndex];
    labelEl.textContent = o ? o.textContent : '';
    labelEl.classList.toggle('es-placeholder', !o || o.value === '');
  }
  function renderList(filter) {
    list.innerHTML = '';
    var count = 0;
    options().forEach(function (o) {
      if (o.value === '' && filter) return; // placeholder tak perlu saat mencari
      if (filter && !fuzzyMatch(o.textContent, filter)) return;
      var it = document.createElement('button');
      it.type = 'button';
      it.className = 'es-item' + (o.selected ? ' es-active' : '') + (o.value === '' ? ' es-muted' : '');
      it.textContent = o.textContent;
      it.setAttribute('role', 'option');
      it.addEventListener('click', function () {
        sel.value = o.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        syncLabel();
        close();
      });
      list.appendChild(it);
      count++;
    });
    if (!count) {
      var empty = document.createElement('div');
      empty.className = 'es-empty';
      empty.textContent = 'Tidak ada yang cocok';
      list.appendChild(empty);
    }
  }
  function open() {
    panel.hidden = false;
    wrap.classList.add('es-open');
    renderList('');
    if (search) { search.value = ''; search.focus(); }
  }
  function close() {
    panel.hidden = true;
    wrap.classList.remove('es-open');
  }
  function isOpen() { return !panel.hidden; }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (isOpen()) close(); else open();
  });
  if (search) {
    search.addEventListener('input', function () { renderList(search.value.trim()); });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); btn.focus(); }
      if (e.key === 'Enter') {
        var first = list.querySelector('.es-item:not(.es-muted)');
        if (first) first.click();
      }
    });
  }
  panel.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', function () { if (isOpen()) close(); });

  // Jika nilai select diubah secara programatik, perbarui label
  new MutationObserver(syncLabel).observe(sel, { subtree: true, childList: true });
  sel.addEventListener('change', syncLabel);
  syncLabel();
}

/* Signature pad pada form serah terima.
   Selector diperluas: semua <canvas> dengan id sig* (sigG/sigR), atau class .sig-canvas,
   agar form peminjaman kendaraan & serah terima perlengkapan sama-sama berfungsi. */
function initSignatureCanvases() {
  var seen = new Set();
  document.querySelectorAll('canvas.sig-canvas, canvas[id^="sig"]').forEach(function (c) {
    if (seen.has(c)) return;
    seen.add(c);
    initSignature(c);
  });
}
function initSignature(canvas) {
  if (canvas.dataset.sigInit === '1') return; // hindari inisialisasi ganda
  canvas.dataset.sigInit = '1';
  var ctx = canvas.getContext('2d');
  var drawing = false, last = null;
  function pos(e) {
    var r = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
  }
  function start(e) { drawing = true; last = pos(e); e.preventDefault(); }
  function move(e) {
    if (!drawing) return;
    var p = pos(e);
    ctx.strokeStyle = '#0f2a43'; ctx.lineWidth = 2.2; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
    last = p; e.preventDefault();
    canvas.dataset.dirty = '1';
  }
  function end() { drawing = false; }
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);
}
function clearSignature(id) {
  var c = document.getElementById(id);
  c.getContext('2d').clearRect(0, 0, c.width, c.height);
  c.dataset.dirty = '';
}
function exportSignature(id, inputId) {
  var c = document.getElementById(id);
  var el = document.getElementById(inputId);
  if (c.dataset.dirty === '1') el.value = c.toDataURL('image/png');
  else el.value = '';
}
