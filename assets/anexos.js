/* Painel do projeto — arquivos das tarefas: prévia antes de enviar e foto ampliada. Sem biblioteca, funciona com toque e teclado. */
(function () {
  var KB = 1024, MB = KB * KB;
  function tam(b) { return b >= MB ? (b / MB).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / KB)) + ' KB'; }
  function ext(n) { var m = /\.([^.]+)$/.exec(n); return m ? m[1].toLowerCase() : ''; }
  function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; }
  var podeEditar = (function () { try { new DataTransfer(); return true; } catch (e) { return false; } })();

  /* ---------- Prévia dos arquivos escolhidos ---------- */
  document.querySelectorAll('[data-anexos]').forEach(function (box) {
    var input = box.querySelector('input[type=file]'), lista = box.querySelector('.previa'), msg = box.querySelector('.previa-msg');
    var max = +box.getAttribute('data-max'), qtd = +box.getAttribute('data-qtd');
    var ok = box.getAttribute('data-ext').split(',');
    var envio = input.form && input.form.querySelector('[data-envia-anexos]');
    var escolhidos = [], urls = [];

    function chave(f) { return f.name + '|' + f.size + '|' + f.lastModified; }
    function sincronizar() {
      if (!podeEditar) return;
      var dt = new DataTransfer(); escolhidos.forEach(function (f) { dt.items.add(f); }); input.files = dt.files;
    }
    function desenhar(erros) {
      urls.forEach(function (u) { URL.revokeObjectURL(u); }); urls = [];
      lista.innerHTML = '';
      escolhidos.forEach(function (f, i) {
        var li = el('li', 'pi'), vis = el('div', 'pi-img');
        if (/^image\/(jpeg|png|gif|webp)$/.test(f.type)) {
          var u = URL.createObjectURL(f); urls.push(u);
          var im = el('img'); im.src = u; im.alt = ''; vis.appendChild(im);
        } else { vis.textContent = (ext(f.name) || 'arq').toUpperCase(); }
        li.appendChild(vis); li.appendChild(el('span', 'pi-nome', f.name)).title = f.name; li.appendChild(el('span', 'pi-tam', tam(f.size)));
        if (podeEditar) {
          var x = el('button', 'pi-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Tirar ' + f.name);
          x.addEventListener('click', function () { escolhidos.splice(i, 1); sincronizar(); desenhar([]); });
          li.appendChild(x);
        }
        lista.appendChild(li);
      });
      lista.hidden = !escolhidos.length;
      var total = escolhidos.reduce(function (s, f) { return s + f.size; }, 0);
      msg.innerHTML = '';
      if (escolhidos.length) {
        var t = escolhidos.length + (escolhidos.length === 1 ? ' arquivo escolhido' : ' arquivos escolhidos') + ' (' + tam(total) + ').';
        t += envio ? ' Toque em “Enviar” para guardar.' : ' Eles serão enviados quando você salvar.';
        msg.appendChild(document.createTextNode(t));
      }
      (erros || []).forEach(function (t) { msg.appendChild(el('span', 'erro', t)); });
      msg.hidden = !msg.childNodes.length;
      if (envio) {
        envio.disabled = !escolhidos.length;
        envio.textContent = escolhidos.length > 1 ? 'Enviar ' + escolhidos.length + ' arquivos' : (escolhidos.length ? 'Enviar 1 arquivo' : 'Enviar arquivos');
      }
    }
    function aceitar(arquivos) {
      var erros = [], tem = {}; escolhidos.forEach(function (f) { tem[chave(f)] = 1; });
      Array.prototype.forEach.call(arquivos, function (f) {
        if (tem[chave(f)]) return;
        if (ok.indexOf(ext(f.name)) < 0) { erros.push(f.name + ': tipo de arquivo não aceito.'); return; }
        if (f.size > max) { erros.push(f.name + ': passa de ' + tam(max) + ' (tem ' + tam(f.size) + ').'); return; }
        if (escolhidos.length >= qtd) { erros.push(f.name + ': o limite é ' + qtd + ' arquivos por envio.'); return; }
        escolhidos.push(f); tem[chave(f)] = 1;
      });
      if (!podeEditar) { escolhidos = Array.prototype.slice.call(arquivos); }
      sincronizar(); desenhar(erros);
    }
    input.addEventListener('change', function () { aceitar(input.files); });
    box._colar = function (imagens) { if (podeEditar) aceitar(imagens); };
    ['dragover', 'dragenter'].forEach(function (ev) { box.addEventListener(ev, function () { box.querySelector('.zona').classList.add('sobre'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { box.addEventListener(ev, function () { box.querySelector('.zona').classList.remove('sobre'); }); });
    if (input.form) input.form.addEventListener('reset', function () { escolhidos = []; desenhar([]); });
    desenhar([]);
  });

  /* ---------- Colar print de tela (Ctrl+V / Cmd+V) ---------- */
  document.addEventListener('paste', function (ev) {
    var caixas = Array.prototype.filter.call(document.querySelectorAll('[data-anexos]'), function (b) { return b._colar; });
    if (!caixas.length || !ev.clipboardData) return;
    var imgs = [];
    Array.prototype.forEach.call(ev.clipboardData.items || [], function (it) {
      if (it.kind === 'file' && /^image\/(png|jpeg|gif|webp)$/.test(it.type)) { var f = it.getAsFile(); if (f) imgs.push(f); }
    });
    if (!imgs.length) return; // texto comum: deixa colar normalmente
    var alvo = caixas.filter(function (b) { var f = b.querySelector('input[type=file]').form; return f && f.contains(document.activeElement); })[0] || (caixas.length === 1 ? caixas[0] : null);
    if (!alvo) return;
    ev.preventDefault();
    var d = new Date(), p2 = function (n) { return (n < 10 ? '0' : '') + n; };
    var base = 'print-' + d.getFullYear() + p2(d.getMonth() + 1) + p2(d.getDate()) + '-' + p2(d.getHours()) + p2(d.getMinutes()) + p2(d.getSeconds());
    var novos = imgs.map(function (f, i) {
      var e = f.type === 'image/jpeg' ? 'jpg' : f.type.split('/')[1];
      return new File([f], base + (imgs.length > 1 ? '-' + (i + 1) : '') + '.' + e, { type: f.type, lastModified: Date.now() + i });
    });
    alvo._colar(novos);
  });

  /* ---------- Foto ampliada (toque numa miniatura) ---------- */
  var lb = null, itens = [], pos = 0, volta = null;
  function mostrar() {
    var a = itens[pos];
    lb.querySelector('img').src = a.href; lb.querySelector('img').alt = a.getAttribute('data-nome') || '';
    lb.querySelector('.lb-nome').textContent = a.getAttribute('data-nome') || '';
    lb.querySelector('.lb-nova').href = a.href;
    lb.querySelector('.lb-cont').textContent = itens.length > 1 ? (pos + 1) + ' de ' + itens.length : '';
    lb.querySelector('.lb-ant').hidden = lb.querySelector('.lb-prox').hidden = itens.length < 2;
  }
  function ir(d) { pos = (pos + d + itens.length) % itens.length; mostrar(); }
  function fechar() { if (!lb) return; lb.remove(); lb = null; document.removeEventListener('keydown', teclas); if (volta) volta.focus(); }
  function teclas(e) {
    if (e.key === 'Escape') fechar();
    else if (e.key === 'ArrowLeft' && itens.length > 1) ir(-1);
    else if (e.key === 'ArrowRight' && itens.length > 1) ir(1);
  }
  function abrir(lista, i) {
    volta = document.activeElement; itens = lista; pos = i;
    lb = el('div', 'lb'); lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Foto ampliada');
    var barra = el('div', 'lb-bar'), nova = el('a', 'lb-nova', 'Abrir em outra aba'); nova.target = '_blank'; nova.rel = 'noopener';
    var fe = el('button', 'lb-fechar', 'Fechar ✕'); fe.type = 'button'; fe.addEventListener('click', fechar);
    barra.appendChild(nova); barra.appendChild(fe);
    var ant = el('button', 'lb-ant', '‹'), prox = el('button', 'lb-prox', '›');
    ant.type = prox.type = 'button'; ant.setAttribute('aria-label', 'Foto anterior'); prox.setAttribute('aria-label', 'Próxima foto');
    ant.addEventListener('click', function () { ir(-1); }); prox.addEventListener('click', function () { ir(1); });
    var im = el('img'); lb.appendChild(el('span', 'lb-cont')); lb.appendChild(barra); lb.appendChild(ant); lb.appendChild(im); lb.appendChild(el('div', 'lb-nome')); lb.appendChild(prox);
    lb.addEventListener('click', function (e) { if (e.target === lb) fechar(); });
    var x0 = null;
    lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (x0 === null || itens.length < 2) return;
      var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 60) ir(dx < 0 ? 1 : -1);
    });
    document.body.appendChild(lb); document.addEventListener('keydown', teclas); mostrar(); fe.focus();
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-ampliar]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
    var gal = a.closest('[data-galeria]'); if (!gal) return;
    e.preventDefault();
    var todas = Array.prototype.slice.call(gal.querySelectorAll('a[data-ampliar]'));
    abrir(todas, todas.indexOf(a));
  });
})();