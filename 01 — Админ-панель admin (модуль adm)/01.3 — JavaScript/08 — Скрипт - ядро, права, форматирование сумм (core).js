/* ==========================================================================
   LA2PLUS ADMIN — ядро: утилиты, права, модалки, тосты, темы
   ========================================================================== */
(function () {
  'use strict';

  var S = window.DB.state();

  /* ---------- утилиты ---------- */
  function E(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function nf(n, d) {
    var v = Number(n) || 0;
    return v.toLocaleString('ru-RU', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
  }
  /* Монеты дробные (0.15, 0.99): округление до целого показывало 0 и 1. */
  function coin(n) { return nf(n, 2) + ' Coin'; }
  function usd(n) { return '$' + nf(n, 2); }
  function dt(ts) {
    var d = new Date(ts);
    return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear() +
      ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  function dshort(ts) {
    var d = new Date(ts);
    return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + ' ' +
      ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  function ago(ts) {
    var s = Math.floor((Date.now() - ts) / 1000);
    if (s < 60) return s + ' с';
    if (s < 3600) return Math.floor(s / 60) + ' мин';
    if (s < 86400) return Math.floor(s / 3600) + ' ч';
    return Math.floor(s / 86400) + ' дн';
  }
  function tsOf(v) { return typeof v === 'string' ? new Date(v).getTime() : v; }

  /* ---------- права ---------- */
  /* Ключ роли всегда через роль-алиас: player → user, accountant → finance.
     Так экраны могут звать роль как удобно, а матрица остаётся серверной. */
  function roleOf(roleKey) {
    var k = window.DB.roleAlias ? window.DB.roleAlias(roleKey) : roleKey;
    return window.DB.ROLES[k] || window.DB.ROLES.user || window.DB.ROLES.player;
  }
  function can(perm) {
    var r = roleOf(effectiveRole(S.me.login));
    if (r.can.indexOf('*') >= 0) return true;
    return r.can.indexOf(perm) >= 0;
  }
  function roleName(key) { return roleOf(key).name; }
  var _effCache = { login: null, role: null };
  /* Владелец: для себя — по выбранной роли (предпросмотр прав), для остальных — по списку владельцев */
  function isOwner(login) {
    login = login || S.me.login;
    if (login === S.me.login) return S.me.role === 'owner';
    var p = (window.DB.state().players || []).filter(function (x) { return x.login === login; })[0];
    return !!(p && window.DB.superAdmins.indexOf(p.id) >= 0);
  }
  /* реальный владелец по списку superAdmins — можно вернуть себе роль владельца */
  function realOwner(login) {
    login = login || S.me.login;
    var p = (window.DB.state().players || []).filter(function (x) { return x.login === login; })[0];
    return !!(p && window.DB.superAdmins.indexOf(p.id) >= 0);
  }
  /* Роль для проверки прав: владелец определяется списком superAdmins, а не полем role */
  function effectiveRole(login) {
    login = login || S.me.login;
    if (_effCache.login === login) return _effCache.role;
    var role = 'user';
    if (login === S.me.login) {
      role = S.me.role || 'user';
    } else if (isOwner(login)) {
      role = 'owner';
    } else {
      var p = (window.DB.state().players || []).filter(function (x) { return x.login === login; })[0];
      role = (p && p.role) || S.me.role || 'user';
    }
    if (window.DB.roleAlias) role = window.DB.roleAlias(role);
    _effCache = { login: login, role: role };
    return role;
  }
  /* вызывать после смены роли/владельца */
  function resetRightsCache() { _effCache = { login: null, role: null }; }
  function canAs(perm) {
    var r = roleOf(effectiveRole(S.me.login));
    if ((r.can || []).indexOf('*') >= 0) return true;
    return (r.can || []).indexOf(perm) >= 0;
  }

  /* ---------- журнал ---------- */
  function logAction(action, target, details) {
    S.log.unshift({
      id: ++S.counters.logId, at: Date.now(), actor: S.me.login,
      action: action, target: target || '', details: details || ''
    });
    window.DB.save();
  }
  function addOp(player, type, amount, comment) {
    S.walletOps.unshift({
      id: ++S.counters.walletId, player: player, type: type, amount: amount,
      comment: comment || '', by: S.me.login, at: Date.now()
    });
    var p = playerByName(player);
    if (p) p.balance = Math.max(0, Math.round((p.balance + amount) * 100) / 100);
    window.DB.save();
  }
  function playerByName(login) {
    for (var i = 0; i < S.players.length; i++) if (S.players[i].login === login) return S.players[i];
    return null;
  }
  function playerById(id) {
    for (var i = 0; i < S.players.length; i++) if (S.players[i].id === id) return S.players[i];
    return null;
  }

  /* ---------- тосты ---------- */
  var toastBox;
  function toast(text, kind) {
    if (!toastBox) {
      toastBox = document.createElement('div');
      toastBox.className = 'toasts';
      document.body.appendChild(toastBox);
    }
    var icons = {
      ok: '<polyline points="20 6 9 17 4 12"/>',
      warn: '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18v.5"/>',
      bad: '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
      info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 8v.5"/>'
    };
    var el = document.createElement('div');
    el.className = 'toast toast--' + (kind || 'ok');
    el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      (icons[kind] || icons.ok) + '</svg><div>' + E(text) + '</div>';
    toastBox.appendChild(el);
    setTimeout(function () {
      el.style.transition = '.25s'; el.style.opacity = '0'; el.style.transform = 'translateY(8px)';
      setTimeout(function () { el.remove(); }, 260);
    }, 3200);
  }

  /* ---------- модалки ---------- */
  var modalEl, scrimEl, modalSpec = null;
  function ensureModal() {
    if (modalEl) return;
    scrimEl = document.createElement('div');
    scrimEl.className = 'scrim';
    scrimEl.id = 'scrim';
    modalEl = document.createElement('div');
    modalEl.className = 'modal';
    modalEl.id = 'modal';
    modalEl.innerHTML = '<div class="modal__head"><div><h3 class="modal__title" id="m-title"></h3>' +
      '<p class="modal__sub" id="m-sub"></p></div>' +
      '<button class="modal__x" data-close="1" aria-label="Закрыть"><svg viewBox="0 0 24 24" fill="none" ' +
      'stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>' +
      '<div class="modal__body" id="m-body"></div><div class="modal__foot" id="m-foot"></div>';
    document.body.appendChild(scrimEl);
    document.body.appendChild(modalEl);
    scrimEl.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
  }
  /*
   * Закрытие окна: кнопки «Отмена» и «×» помечены data-close, но обработчика
   * у них не было — окна не закрывались. Слушаем клик на документе, потому
   * что кнопки перерисовываются вместе с содержимым окна.
   */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-close]');
    if (!t) return;
    e.preventDefault();
    closeModal();
  });

  function openModal(spec) {
    ensureModal();
    modalSpec = spec;
    modalEl.classList.toggle('modal--wide', !!spec.wide);
    document.getElementById('m-title').textContent = spec.title || '';
    document.getElementById('m-sub').textContent = spec.sub || '';
    document.getElementById('m-body').innerHTML = spec.body || '';
    document.getElementById('m-foot').innerHTML = spec.foot === null ? '' :
      '<button class="btn btn--ghost" data-close="1">Отмена</button>' + (spec.foot || '');
    scrimEl.classList.add('on');
    modalEl.classList.add('on');
    var f = modalEl.querySelector('input,textarea,select');
    if (f && spec.autofocus !== false) setTimeout(function () { f.focus(); }, 80);
    if (spec.onMount) spec.onMount(modalEl);
  }
  function closeModal() {
    if (!modalEl) return;
    scrimEl.classList.remove('on');
    modalEl.classList.remove('on');
    modalSpec = null;
  }
  function confirmModal(o) {
    openModal({
      title: o.title, sub: o.sub || '',
      body: '<p style="margin:0;font-size:13.5px;line-height:1.6;color:var(--mut)">' + (o.text || '') + '</p>' +
        (o.extra || ''),
      foot: '<button class="btn ' + (o.danger ? 'btn--danger' : 'btn--primary') + '" data-confirm="1">' +
        E(o.okText || 'Подтвердить') + '</button>',
      onMount: function (m) {
        m.querySelector('[data-confirm]').addEventListener('click', function () {
          closeModal(); o.onOk();
        });
      }
    });
  }

  /* ---------- тема ---------- */
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    S.theme = t;
    window.DB.save();
    document.querySelectorAll('[data-theme-pick]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-theme-pick') === t);
    });
  }

  /* ---------- общий экспорт ---------- */
  window.UI = {
    E: E, nf: nf, coin: coin, usd: usd, dt: dt, dshort: dshort, ago: ago, tsOf: tsOf,
    can: can, roleName: roleName, roleOf: roleOf, isOwner: isOwner, realOwner: realOwner,
    effectiveRole: effectiveRole, canAs: canAs, resetRightsCache: resetRightsCache, logAction: logAction, addOp: addOp,
    playerByName: playerByName, playerById: playerById, toast: toast,
    openModal: openModal, closeModal: closeModal, confirmModal: confirmModal,
    setTheme: setTheme, S: S,
    exportCsv: function (filename, rows) {
      var csv = rows.map(function (r) {
        return r.map(function (c) {
          var s = String(c === null || c === undefined ? '' : c).replace(/"/g, '""');
          return '"' + s + '"';
        }).join(';');
      }).join('\r\n');
      var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
      toast('Файл ' + filename + ' сформирован', 'ok');
    }
  };
})();
