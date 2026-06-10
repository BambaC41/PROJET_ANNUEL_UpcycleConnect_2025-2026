function openModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.add('is-open');
  el.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open');
}

function closeModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('is-open');
  el.setAttribute('aria-hidden', 'true');
  if (!document.querySelector('.modal.is-open')) {
    document.body.classList.remove('modal-open');
  }
}

document.addEventListener('click', function (ev) {
  var target = ev.target;
  if (target.classList && target.classList.contains('modal-backdrop')) {
    closeModal(target.id.replace('-backdrop', ''));
  }
  if (target.dataset && target.dataset.closeModal) {
    closeModal(target.dataset.closeModal);
  }
});

document.addEventListener('keydown', function (ev) {
  if (ev.key === 'Escape') {
    document.querySelectorAll('.modal.is-open').forEach(function (m) {
      closeModal(m.id);
    });
  }
});
