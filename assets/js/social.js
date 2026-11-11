/* OctoArt — curtir, comentar e denunciar SEM recarregar a página */
(function () {
  'use strict';

  function toast(msg, type) {
    var t = document.createElement('div');
    t.className = 'octo-toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 250); }, 3200);
  }

  function send(data) {
    return fetch('acoes.php', { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'Resposta inválida do servidor.' }; }); })
      .then(function (j) {
        if (j && j.login) { window.location.href = 'login.php'; }
        return j;
      })
      .catch(function () { return { ok: false, msg: 'Sem conexão. Tente novamente.' }; });
  }

  function setText(sel, value) {
    document.querySelectorAll(sel).forEach(function (el) { el.textContent = value; });
  }

  /* ---- Curtir ---- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.classList.contains('js-like-form')) {
      e.preventDefault();
      var btn = f.querySelector('button');
      if (btn.disabled) return;
      btn.disabled = true;
      var pid = f.querySelector('[name=id_projeto]').value;
      send(new FormData(f)).then(function (j) {
        btn.disabled = false;
        if (!j.ok) { if (!j.login) toast(j.msg || 'Não foi possível curtir.', 'err'); return; }
        btn.classList.toggle('liked', j.curtido);
        btn.querySelector('span').textContent = j.curtido ? 'Curtido' : 'Curtir';
        setText('[data-likes="' + pid + '"]', j.total);
      });
      return;
    }

    /* ---- Comentar ---- */
    if (f.classList.contains('js-comment-form')) {
      e.preventDefault();
      var input = f.querySelector('input[name=comentario]');
      var submit = f.querySelector('button[type=submit]');
      if (!input.value.trim() || submit.disabled) return;
      submit.disabled = true;
      var pid2 = f.querySelector('[name=id_projeto]').value;
      send(new FormData(f)).then(function (j) {
        submit.disabled = false;
        if (!j.ok) { if (!j.login) toast(j.msg || 'Não foi possível comentar.', 'err'); return; }
        var box = document.getElementById('comentarios-' + pid2);
        if (box) {
          var c = document.createElement('div');
          c.className = 'mini-comment';
          var s = document.createElement('strong'); s.textContent = j.nome;
          var t = document.createElement('span'); t.textContent = j.comentario;
          c.appendChild(s); c.appendChild(t);
          box.insertBefore(c, box.firstChild);
          if (box.hasAttribute('data-limit')) {
            var lim = parseInt(box.getAttribute('data-limit'), 10);
            while (box.children.length > lim) box.removeChild(box.lastChild);
          }
        }
        input.value = '';
        setText('[data-comments="' + pid2 + '"]', j.total);
      });
      return;
    }

    /* ---- Denunciar ---- */
    if (f.id === 'reportForm') {
      e.preventDefault();
      var errBox = document.getElementById('reportError');
      var sendBtn = document.getElementById('reportSend');
      errBox.hidden = true;
      sendBtn.disabled = true;
      send(new FormData(f)).then(function (j) {
        sendBtn.disabled = false;
        if (j.ok) {
          closeReport();
          toast(j.msg, 'ok');
        } else if (!j.login) {
          errBox.textContent = j.msg || 'Não foi possível enviar.';
          errBox.hidden = false;
        }
      });
    }
  });

  /* ---- Cliques (menu ..., denunciar, comentar, compartilhar) ---- */
  var modal = document.getElementById('reportModal');
  function openReport(pid) {
    if (!modal) return;
    document.getElementById('reportForm').reset();
    document.getElementById('reportError').hidden = true;
    document.getElementById('reportPid').value = pid;
    modal.hidden = false;
  }
  function closeReport() { if (modal) modal.hidden = true; }

  function closeMenus(except) {
    document.querySelectorAll('.post-menu-list').forEach(function (m) { if (m !== except) m.hidden = true; });
  }

  document.addEventListener('click', function (e) {
    var t = e.target;

    var more = t.closest('.post-more');
    if (more) {
      var list = more.parentElement.querySelector('.post-menu-list');
      var willOpen = list.hidden;
      closeMenus();
      list.hidden = !willOpen;
      return;
    }
    if (!t.closest('.post-menu-list')) closeMenus();

    var rep = t.closest('.js-report');
    if (rep) { closeMenus(); openReport(rep.getAttribute('data-id')); return; }

    if (t.closest('[data-report-close]')) { closeReport(); return; }
    if (modal && t === modal) { closeReport(); return; }

    var fc = t.closest('.js-focus-comment');
    if (fc) {
      var inp = document.querySelector('#projeto-' + fc.getAttribute('data-id') + ' .comment-form input[name=comentario]')
             || document.querySelector('.comment-form input[name=comentario]');
      if (inp) inp.focus();
    }
  });

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeReport(); closeMenus(); } });

  /* ---- Compartilhar ---- */
  document.querySelectorAll('.share-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = new URL(btn.dataset.url, location.href).href;
      var done = function () { toast('Link copiado!', 'ok'); };
      if (navigator.share) { navigator.share({ title: 'OctoArt', url: url }).catch(function () {}); }
      else if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done); }
    });
  });
})();
