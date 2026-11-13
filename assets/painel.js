/* Painel do projeto — botões de ajuda (?) e utilidades. Funciona com toque e teclado. */
(function () {
  var pop = null, dono = null;
  function fechar() {
    if (pop) { pop.remove(); pop = null; }
    if (dono) { dono.setAttribute('aria-expanded', 'false'); dono = null; }
  }
  function abrir(btn) {
    fechar();
    pop = document.createElement('div');
    pop.className = 'pop'; pop.setAttribute('role', 'tooltip');
    pop.textContent = btn.getAttribute('data-texto');
    document.body.appendChild(pop);
    var r = btn.getBoundingClientRect(), pw = pop.offsetWidth;
    var left = Math.min(Math.max(12, r.left + window.scrollX - pw / 2 + r.width / 2), window.scrollX + document.documentElement.clientWidth - pw - 12);
    pop.style.left = left + 'px';
    pop.style.top = (r.bottom + window.scrollY + 8) + 'px';
    btn.setAttribute('aria-expanded', 'true'); dono = btn;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.ajuda');
    if (b) { e.preventDefault(); e.stopPropagation(); (dono === b) ? fechar() : abrir(b); return; }
    fechar();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
  window.addEventListener('scroll', fechar, { passive: true });
  window.addEventListener('resize', fechar);

  document.querySelectorAll('[data-copiar]').forEach(function (el) {
    el.addEventListener('click', function () {
      var t = el.getAttribute('data-copiar');
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () {
        var o = el.textContent; el.textContent = 'Copiado!'; setTimeout(function () { el.textContent = o; }, 1500);
      }).catch(function () { window.prompt('Copie o texto:', t); });
    });
  });

  // Todo campo com label: liga <label> sem "for" ao campo que vem logo depois (leitor de tela e toque no texto).
  var n = 0;
  document.querySelectorAll('label:not([for])').forEach(function (l) {
    var c = l.nextElementSibling;
    if (c && /^(INPUT|SELECT|TEXTAREA)$/.test(c.tagName) && c.type !== 'hidden') {
      if (!c.id) c.id = 'campo-auto-' + (++n);
      l.setAttribute('for', c.id);
    }
  });

  // Enviando: o botão mostra um giro (evita toque duplo) e o cartão "Feita" esmaece. Nada disso muda o envio.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f || f.tagName !== 'FORM') return;
    var a = f.querySelector('input[name=acao][value=feito]');
    if (a) { var c = f.closest('.tarefa'); if (c) c.classList.add('saindo'); }
    var b = e.submitter || f.querySelector('button[type=submit],button:not([type])');
    if (b && b.classList.contains('btn')) setTimeout(function () { b.classList.add('carregando'); }, 0);
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) document.querySelectorAll('.carregando').forEach(function (b) { b.classList.remove('carregando'); });
  });
})();
