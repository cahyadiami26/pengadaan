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
});

/* Signature pad pada form serah terima */
function initSignatureCanvases() {
  document.querySelectorAll('canvas.sig-canvas').forEach(initSignature);
}
function initSignature(canvas) {
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
