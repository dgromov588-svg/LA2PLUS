/* ==========================================================================
   LA2PLUS ADMIN — каркас: логотип, свечение, тема, поиск, профиль
   ========================================================================== */
(function () {
  'use strict';

  /*
   * Снимок берём каждый раз заново: api.js подменяет объект состояния при
   * загрузке, и если держать ссылку, полученную до неё, в шапке остаётся
   * пустой логин и «—» вместо инициалов.
   */
  function state() { return window.DB.state(); }
  var E = UI.E;

  /* ---------- логотип со свечением и переливом ---------- */
  function mark() { return window.LA2PLUS_BRAND_HTML || ''; }

  /* ореол на фоне идёт от логотипа */
  function syncAura() {
    var logo = document.getElementById('sideLogo');
    var blob = document.getElementById('auraLogo');
    if (!logo || !blob) return;
    var r = logo.getBoundingClientRect();
    var cx = r.left + r.width / 2;
    var cy = r.top + r.height / 2;
    blob.style.setProperty('--logo-x', (cx - 260) + 'px');
    blob.style.setProperty('--logo-y', (cy - 260) + 'px');
    blob.style.left = (cx - 260) + 'px';
    blob.style.top = (cy - 260) + 'px';
  }

  /* ---------- тема (Aurum — основная, остальные доступны в «Настройках») ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('#themePick [data-theme-pick]');
    if (!b) return;
    UI.setTheme(b.getAttribute('data-theme-pick'));
    if (window.APP) window.APP.render();
  });

  /* ---------- сайдбар ---------- */
  var side = document.getElementById('side');
  if (state().sideOpen === false) side.classList.remove('is-open');
  document.getElementById('sideToggle').addEventListener('click', function (e) {
    e.preventDefault();
    side.classList.toggle('is-open');
    state().sideOpen = side.classList.contains('is-open');
    window.DB.save();
    setTimeout(syncAura, 220);
  });
  document.getElementById('sideLogo').addEventListener('click', function () {
    side.classList.toggle('is-open');
    state().sideOpen = side.classList.contains('is-open');
    window.DB.save();
    setTimeout(syncAura, 220);
  });
  side.querySelectorAll('.side__item').forEach(function (a) {
    a.addEventListener('click', function () {
      window.APP.go(a.getAttribute('data-go'));
    });
  });

  function centerActive() {
    if (!window.matchMedia('(max-width:860px)').matches) return;
    var cur = side.querySelector('.side__item.is-active');
    if (cur && cur.scrollIntoView) cur.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
  }

  /* ---------- порядок пунктов меню перетаскиванием ---------- */
  var order = state().navOrder && state().navOrder.length ? state().navOrder : [];
  var items = Array.prototype.slice.call(side.querySelectorAll('.side__item'));
  if (order.length) {
    order.forEach(function (key) {
      var el = items.filter(function (a) { return a.getAttribute('data-go') === key; })[0];
      if (el) side.insertBefore(el, side.querySelector('.side__foot'));
    });
  }
  items.forEach(function (a) {
    a.setAttribute('draggable', 'true');
    a.addEventListener('dragstart', function (e) {
      e.dataTransfer.setData('text/plain', a.getAttribute('data-go'));
      a.classList.add('is-drag');
    });
    a.addEventListener('dragend', function () { a.classList.remove('is-drag'); });
    a.addEventListener('dragover', function (e) { e.preventDefault(); a.classList.add('is-over'); });
    a.addEventListener('dragleave', function () { a.classList.remove('is-over'); });
    a.addEventListener('drop', function (e) {
      e.preventDefault(); a.classList.remove('is-over');
      var from = e.dataTransfer.getData('text/plain');
      if (!from || from === a.getAttribute('data-go')) return;
      var src = side.querySelector('.side__item[data-go="' + from + '"]');
      if (!src) return;
      side.insertBefore(src, a);
      state().navOrder = Array.prototype.slice.call(side.querySelectorAll('.side__item')).map(function (x) {
        return x.getAttribute('data-go');
      });
      window.DB.save();
      UI.toast('Порядок меню сохранён', 'ok');
    });
  });

  /* ---------- чипы статусов ---------- */
  document.querySelectorAll('[data-chip]').forEach(function (c) {
    c.addEventListener('click', function () {
      var k = c.getAttribute('data-chip');
      if (k === 'online') window.APP.go('characters');
      if (k === 'tickets') window.APP.go('tickets');
      if (k === 'pays') window.APP.go('finance');
    });
  });

  function refreshChrome() {
    var s = window.APP.stats();
    var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
    set('chipOnline', s.online);
    set('chipTickets', s.openTickets);
    set('chipPays', s.pending);
    var bp = document.getElementById('badgePays');
    var bt = document.getElementById('badgeTickets');
    if (bp) { bp.textContent = s.pending; bp.style.display = s.pending ? '' : 'none'; }
    if (bt) { bt.textContent = s.openTickets; bt.style.display = s.openTickets ? '' : 'none'; }
    /* недоступные роли разделы прячем в меню — как в боевой панели */
    if (window.APP && window.APP.perms) {
      document.querySelectorAll('.side__item[data-go]').forEach(function (a) {
        var perm = window.APP.perms[a.getAttribute('data-go')];
        a.style.display = (!perm || UI.can(perm)) ? '' : 'none';
      });
    }
    var rl = document.getElementById('meRole');
    if (rl) rl.textContent = UI.roleName(UI.effectiveRole(state().me.login));
  }
  window.APP = window.APP || {};
  window.APP.refreshChrome = refreshChrome;
  window.APP.refreshMe = function () { renderMe(); buildMe(); };
  setInterval(refreshChrome, 1500);

  /* ---------- глобальный поиск ---------- */
  var qInput = document.getElementById('globalQ');
  var panel = document.createElement('div');
  panel.className = 'gsearch';
  panel.style.display = 'none';
  document.body.appendChild(panel);

  function searchAll(q) {
    q = q.trim().toLowerCase();
    if (!q) return [];
    var out = [];
    state().players.forEach(function (p) {
      if (p.login.toLowerCase().indexOf(q) >= 0 || p.email.toLowerCase().indexOf(q) >= 0)
        out.push({ kind: 'player', id: p.id, title: p.login, sub: p.email + ' · ' + UI.nf(p.balance, 2) + ' Coin', tag: 'Игрок' });
    });
    state().accounts.forEach(function (a) {
      if (a.login.toLowerCase().indexOf(q) >= 0 || a.ip.indexOf(q) >= 0)
        out.push({ kind: 'account', id: a.login, title: a.login, sub: 'IP ' + a.ip + (a.banned ? ' · бан' : ''), tag: 'Аккаунт' });
    });
    state().characters.forEach(function (c) {
      if (c.name.toLowerCase().indexOf(q) >= 0)
        out.push({ kind: 'char', id: c.name, title: c.name, sub: c.level + ' ур. · ' + c.cls + ' · ' + c.account, tag: 'Персонаж' });
    });
    state().payments.forEach(function (p) {
      if (String(p.id).indexOf(q) >= 0 || p.player.toLowerCase().indexOf(q) >= 0)
        out.push({ kind: 'pay', id: p.id, title: 'Заявка #' + p.id, sub: p.player + ' · ' + p.coins + ' Coin · ' + p.gateway, tag: 'Заявка' });
    });
    state().tickets.forEach(function (t) {
      if (t.subject.toLowerCase().indexOf(q) >= 0 || t.player.toLowerCase().indexOf(q) >= 0)
        out.push({ kind: 'ticket', id: t.id, title: '#' + t.id + ' ' + t.subject, sub: t.player, tag: 'Тикет' });
    });
    out.push({ kind: 'clan', id: null, title: 'Кланы', sub: 'Открыть раздел кланов', tag: 'Переход', always: true });
    return out.slice(0, 12);
  }

  function renderSearch(q) {
    var res = searchAll(q).filter(function (r) { return r.always || q; });
    if (!q) { panel.style.display = 'none'; return; }
    var box = document.getElementById('globalSearch').getBoundingClientRect();
    panel.style.top = (box.bottom + 8) + 'px';
    panel.style.left = Math.max(12, box.left) + 'px';
    panel.style.width = Math.max(320, box.width + 80) + 'px';
    panel.innerHTML = res.length
      ? res.map(function (r, i) {
        return '<button class="gsearch__i" data-si="' + i + '"><span class="gsearch__tag">' + E(r.tag) + '</span>' +
          '<span class="gsearch__t"><b>' + E(r.title) + '</b><span class="dim">' + E(r.sub) + '</span></span></button>';
      }).join('')
      : '<div class="gsearch__empty">Ничего не найдено. Ищите по логину, e-mail, IP, имени персонажа или номеру заявки.</div>';
    panel.style.display = 'block';
    panel.querySelectorAll('[data-si]').forEach(function (b) {
      b.addEventListener('click', function () {
        var r = res[+b.getAttribute('data-si')];
        panel.style.display = 'none';
        qInput.value = '';
        openResult(r);
      });
    });
  }
  function openResult(r) {
    if (r.kind === 'player') window.APP.openPlayer(r.id);
    if (r.kind === 'account') window.APP.openAccount(r.id);
    if (r.kind === 'char') window.APP.openChar(r.id);
    if (r.kind === 'pay') { window.APP.go('finance'); }
    if (r.kind === 'ticket') { window.APP.go('tickets'); }
    if (r.kind === 'clan') window.APP.go('clans');
  }
  qInput.addEventListener('input', function () { renderSearch(qInput.value); });
  qInput.addEventListener('focus', function () { if (qInput.value) renderSearch(qInput.value); });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#globalSearch') && !e.target.closest('.gsearch')) panel.style.display = 'none';
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault(); qInput.focus(); qInput.select();
    }
    if (e.key === 'Escape') panel.style.display = 'none';
  });

  /* ---------- меню профиля: роль, владельцы, вход ---------- */
  var meWrap = document.getElementById('meWrap');
  var meMenu = document.getElementById('meMenu');

  function owners() {
    return (state().players || []).filter(function (p) { return window.DB.superAdmins.indexOf(p.id) >= 0; });
  }
  function staffCandidates() {
    return (state().players || []).filter(function (p) { return p.role || window.DB.superAdmins.indexOf(p.id) >= 0; });
  }
  function renderMe() {
    var l = document.getElementById('meLogin');
    var r = document.getElementById('meRole');
    var a = document.getElementById('meAvatar');
    if (l) l.textContent = state().me.login;
    if (r) r.textContent = UI.roleName(UI.effectiveRole(state().me.login));
    if (a) {
      a.textContent = (state().me.login || '?').slice(0, 2).toUpperCase();
      a.classList.toggle('is-owner', UI.isOwner(state().me.login));
    }
  }
  function closeMe() { meMenu.hidden = true; meWrap.classList.remove('is-open'); }

  function buildMe() {
    var canOwner = UI.can(window.DB.CAP.roles) && UI.can(window.DB.CAP.owners);
    var st = window.APP.stats ? window.APP.stats() : {};
    var html =
      '<div class="me__head">' +
        '<p class="me__name">' + E(state().me.login) + '</p>' +
        '<p class="me__sub">' + E(UI.roleName(UI.effectiveRole(state().me.login))) +
        (UI.realOwner(state().me.login) && state().me.role !== 'owner' ? ' · <span class="gold">в списке владельцев</span>' : '') + '</p>' +
      '</div>' +

      '<p class="me__l">Предпросмотр прав другой роли</p>' +
      '<div class="me__chips">' +
        window.DB.roleKeys.filter(function (k) { return k !== 'player'; }).map(function (k) {
          var on = UI.effectiveRole(state().me.login) === k;
          var lock = k === 'owner' && !UI.realOwner(state().me.login);
          return '<button class="fbtn' + (on ? ' on' : '') + '" data-role="' + k + '"' +
            (lock ? ' disabled title="Роль владельца выдаётся только владельцем"' : '') + '>' +
            window.DB.ROLES[k].short + (lock ? ' ⛨' : '') + '</button>';
        }).join('') +
      '</div>' +
      '<p class="me__hint">Предпросмотр: кнопка показывает, что видит роль в панели. ' +
        'Ваша роль в базе от этого не меняется. Владелец проекта не выдаётся ролью — только списком владельцев ниже.</p>' +

      '<p class="me__l">Вход в систему</p>' +
      '<div class="me__row"><span>Вы вошли как</span><b>' + E(state().me.login) + '</b></div>' +
      '<div class="me__row"><span>Данные</span><b>из базы на ' +
        (state().dbSnapshot && state().dbSnapshot.builtAt ? UI.dt(state().dbSnapshot.builtAt) : '—') + '</b></div>' +

      '<p class="me__l">Владельцы проекта · ' + owners().length + '</p>' +
      '<div class="me__owners">' + owners().map(function (o) {
        /* Свой доступ и доступ равного по рангу снять нельзя: владельцы
           равны между собой, поэтому кнопки «Снять» здесь нет ни у кого.
           Список владельцев меняется вручную в params.php. */
        var isMe = String(o.login).toLowerCase() === String(state().me.login).toLowerCase();
        var why = isMe ? 'Себя снять нельзя — попросите другого владельца проекта'
                       : 'Владельцы равны по правам: снять другого владельца из панели нельзя. Список меняется в params.php.';
        return '<div class="me__owner"><span class="me__oav">' + E(o.login.slice(0, 2).toUpperCase()) + '</span>' +
          '<span class="me__oname">' + E(o.login) + '<i>#' + o.id + ' · владелец' + (isMe ? ' · это вы' : '') + '</i></span>' +
          '<span class="me__locked" title="' + why + '">не снимается</span>' +
          '</div>';
      }).join('') + '</div>' +
      (canOwner ? '<button class="btn btn--sm me__wide" data-owner-add>' + window.ICON('plus') + 'Выдать владельца</button>'
        : '<p class="me__hint me__warn">' + window.ICON('lock') + ' Выдавать владельцев может только владелец проекта. ' +
          'Список владельцев не снимается из панели: он меняется вручную в protected/config/params.php.</p>') +

      '<div class="me__foot">' +
        '<button class="btn btn--sm btn--ghost" data-me-cab>' + window.ICON('home') + 'Кабинет игрока</button>' +
        '<button class="btn btn--sm btn--ghost" data-me-logout>' + window.ICON('lock') + 'Выйти</button>' +
      '</div>';

    meMenu.innerHTML = html;

    meMenu.querySelectorAll('[data-role]').forEach(function (b) {
      b.addEventListener('click', function () {
        var r = b.getAttribute('data-role');
        if (r === 'owner' && !UI.realOwner(state().me.login)) {
          return UI.toast('Роль владельца так не выдаётся — нужен список владельцев', 'bad');
        }
        state().me.role = r;
        UI.resetRightsCache();
        window.DB.save();
        renderMe(); buildMe();
        window.APP.render();
        UI.toast('Роль переключена: ' + UI.roleName(r), 'ok');
      });
    });
    meMenu.querySelectorAll('[data-owner-del]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = +b.getAttribute('data-owner-del');
        var p = state().players.filter(function (x) { return x.id === id; })[0];
        if (!p) return;
        if (window.DB.superAdmins.length <= 1) {
          return UI.toast('Нельзя снять последнего владельца проекта', 'bad');
        }
        /* Снять владельца из панели нельзя ни себе, ни равному по рангу.
           Кнопка для таких строк не рисуется, а здесь — страховка на случай
           старой разметки. Настоящее правило живёт на сервере: RoleService. */
        if (String(p.login).toLowerCase() === String(state().me.login).toLowerCase()) {
          return UI.toast('Себя снять нельзя — попросите другого владельца', 'bad');
        }
        return UI.toast('Владельцы равны по правам: снять другого владельца из панели нельзя. Список меняется в params.php.', 'info');
      });
    });
    var addBtn = meMenu.querySelector('[data-owner-add]');
    if (addBtn) addBtn.addEventListener('click', openOwnerAdd);
    meMenu.querySelector('[data-me-cab]').addEventListener('click', function () { closeMe(); toCabinet(); });
    meMenu.querySelector('[data-me-logout]').addEventListener('click', function () { closeMe(); logout(); });
  }

  function openOwnerAdd() {
    if (!(UI.can(window.DB.CAP.roles) && UI.can(window.DB.CAP.owners))) {
      return UI.toast('Выдавать владельца может только владелец проекта', 'bad');
    }
    closeMe();
    var list = (state().players || []).filter(function (p) { return window.DB.superAdmins.indexOf(p.id) < 0; });
    UI.openModal({
      title: 'Выдать владельца проекта', sub: 'Владельцев может быть несколько',
      body: '<label class="field"><span class="field__l">Игрок</span>' +
        '<input id="ow-login" list="ow-list" placeholder="логин на сайте">' +
        '<datalist id="ow-list">' + list.map(function (p) {
          return '<option value="' + E(p.login) + '">';
        }).join('') + '</datalist>' +
        '<p class="field__h">Владелец получает полный доступ, включая управление владельцами. ' +
        'Администратор этого сделать не может.</p></label>' +
        '<div id="ow-preview" class="dim"></div>',
      foot: '<button class="btn btn--primary" id="ow-send">' + window.ICON('shield') + 'Сделать владельцем</button>',
      onMount: function (m) {
        var inp = m.querySelector('#ow-login'), prev = m.querySelector('#ow-preview');
        inp.addEventListener('input', function () {
          var p = list.filter(function (x) { return x.login.toLowerCase() === inp.value.trim().toLowerCase(); })[0];
          prev.innerHTML = p
            ? 'Найден: <b>' + E(p.login) + '</b> · #' + p.id + ' · текущая роль: ' +
              E(p.role ? UI.roleName(p.role) : 'игрок')
            : (inp.value ? '<span style="color:var(--bad)">такого игрока нет</span>' : '');
        });
        m.querySelector('#ow-send').addEventListener('click', function () {
          var p = list.filter(function (x) { return x.login.toLowerCase() === inp.value.trim().toLowerCase(); })[0];
          if (!p) return UI.toast('Проверьте логин', 'bad');
          window.API.owner({ act: 'grant', user_id: p.id }).then(function (res) {
            window.DB.superAdmins = res.owners.slice();
            UI.resetRightsCache();
            UI.closeModal(); renderMe();
            return window.DB.reload();
          }).then(function () {
            window.APP.render();
            UI.toast(p.login + ' — владелец проекта', 'ok');
          }).catch(function (e) { UI.toast(e.message || 'Владельца выдать не удалось', 'bad'); });
        });
      }
    });
  }

  /* ---------- вход / выход: кабинет и Панель управления отдельными кнопками ---------- */
  function toCabinet() {
    UI.openModal({
      title: 'Выход из Панели управления', sub: 'Переход в личный кабинет игрока',
      body: '<p class="field__h" style="margin:0 0 14px">В боевой панели это <span class="mono">/cabinet/</span> — ' +
        'отдельная кнопка «Кабинет», она не соседствует с «Панелью управления» внутри самой панели. ' +
        'Обратно в панель ведёт кнопка «Панель управления» в кабинете, и её видно только ролям с правом входа.</p>' +
        '<div class="areacard"><div class="areacard__i"><b>Кабинет игрока</b><span>/cabinet/ · баланс, персонажи, пополнение</span></div>' +
        '<div class="areacard__i is-now"><b>Панель управления</b><span>/admin · сотрудники проекта</span></div></div>',
      foot: '<button class="btn btn--primary" data-go-cab>Перейти в кабинет</button>' +
        '<button class="btn btn--ghost" data-stay>Остаться в панели</button>',
      onMount: function (m) {
        m.querySelector('[data-go-cab]').addEventListener('click', function () {
          UI.closeModal();
          window.location.href = '/cabinet/';
        });
        m.querySelector('[data-stay]').addEventListener('click', function () { UI.closeModal(); });
      }
    });
  }
  function logout() {
    UI.confirmModal({
      title: 'Выйти из аккаунта', sub: 'Сессия панели будет закрыта',
      text: 'Вы вернётесь на страницу входа и сможете войти снова.',
      okText: 'Выйти',
      onOk: function () {
        document.documentElement.classList.add('is-logout');
        var box = document.getElementById('logoutScreen');
        if (!box) {
          box = document.createElement('div');
          box.id = 'logoutScreen';
          box.className = 'logout';
          document.body.appendChild(box);
        }
        box.innerHTML = '<div class="logout__card">' +
          '<span class="logout__mark">' + mark() + '</span>' +
          '<p class="logout__t">Вы вышли из Панели управления</p>' +
          '<p class="logout__s">Сессия закрыта. В боевой панели откроется страница входа в кабинет.</p>' +
          '<button class="btn btn--primary" data-relogin>Войти снова как ' + E(state().me.login) + '</button>' +
          '<button class="btn btn--ghost" data-tocab>Перейти в кабинет</button></div>';
        box.querySelector('[data-relogin]').addEventListener('click', function () {
          box.remove(); document.documentElement.classList.remove('is-logout');
          UI.toast('С возвращением, ' + state().me.login, 'ok');
        });
        box.querySelector('[data-tocab]').addEventListener('click', function () {
          box.remove(); document.documentElement.classList.remove('is-logout'); toCabinet();
        });
      }
    });
  }

  document.getElementById('meBtn').addEventListener('click', function (e) {
    e.stopPropagation();
    if (meMenu.hidden) { buildMe(); meMenu.hidden = false; meWrap.classList.add('is-open'); }
    else closeMe();
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#meWrap')) closeMe();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMe(); });

  /* кнопки «Кабинет / Панель управления» в топбаре */
  document.querySelectorAll('[data-area]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      if (a.getAttribute('data-area') === 'cabinet') toCabinet();
      else UI.toast('Вы уже в Панели управления', 'info');
    });
  });

  /* ---------- старт ---------- */
  /* Шапка рисуется до прихода данных, поэтому логин и инициалы
     дорисовываем, как только состояние загрузится с сервера. */
  if (window.DB && window.DB.onChange) {
    window.DB.onChange(function () {
      renderMe();
      buildMe();
    });
  }

  document.getElementById('brandMark').innerHTML = mark();
  window.addEventListener('resize', syncAura);
  window.addEventListener('load', function () {
    syncAura();
    renderMe();
    setTimeout(syncAura, 300);
    refreshChrome();
    var s = window.APP.stats();
    UI.logAction('Вход в панель', '—', 'браузер ' + (navigator.userAgent.indexOf('Firefox') > -1 ? 'Firefox' : 'Chrome'));
    refreshChrome();
  });

  /* подсветка активного пункта после каждого рендера */
  var origGo = window.APP.go;
  window.APP.go = function (n) {
    origGo(n);
    document.querySelectorAll('.side__item').forEach(function (a) {
      a.classList.toggle('is-active', a.getAttribute('data-go') === n);
    });
    refreshChrome();
    if (typeof renderMe === 'function') renderMe();
    setTimeout(syncAura, 60);
    setTimeout(centerActive, 80);
  };
})();
