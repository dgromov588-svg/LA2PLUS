/* ==========================================================================
   LA2PLUS ADMIN — экраны. Каждый рендер работает с реальным состоянием.
   ========================================================================== */
(function () {
  'use strict';

  var S = window.DB.state();
  var E = UI.E, nf = UI.nf, coin = UI.coin, usd = UI.usd, dt = UI.dt, dshort = UI.dshort, ago = UI.ago;

  var ICON = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-6h4v6"/>',
    users: '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.4 2.8-5.6 6.2-5.6s6.2 2.2 6.2 5.6"/><path d="M16.5 5.2a3 3 0 0 1 0 5.6"/><path d="M18 14.6c2 .6 3.2 2.2 3.2 4.4"/>',
    key: '<circle cx="8" cy="8" r="4"/><path d="m11 11 9 9"/><path d="m16.5 16.5 2-2"/><path d="m14 14 1.8-1.8"/>',
    star: '<path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/>',
    crown: '<path d="M3 8.5 7 12l5-6 5 6 4-3.5-2 10H5z"/><path d="M5 18.5h14"/>',
    swords: '<path d="M4 4h4l9 9-4 4-9-9z"/><path d="m14 18 4 4"/><path d="M20 4h-4l-3.5 3.5"/><path d="m6 18 3-3"/>',
    coin: '<ellipse cx="12" cy="7.5" rx="7" ry="3.2"/><path d="M5 7.5v9c0 1.8 3.1 3.2 7 3.2s7-1.4 7-3.2v-9"/><path d="M5 12c0 1.8 3.1 3.2 7 3.2s7-1.4 7-3.2"/>',
    lifebuoy: '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.4"/><path d="m6 6 3.7 3.7M18 6l-3.7 3.7M6 18l3.7-3.7M18 18l-3.7-3.7"/>',
    shield: '<path d="M12 3 5 6v6c0 4.2 3 7.4 7 9 4-1.6 7-4.8 7-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
    trophy: '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 5.5H5.2v1.8A3.3 3.3 0 0 0 8 10.6"/><path d="M16 5.5h2.8v1.8a3.3 3.3 0 0 1-2.8 3.3"/><path d="M12 13v3"/><path d="M8.5 20h7l-.7-4h-5.6z"/>',
    history: '<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5V9H8"/><path d="M12 7.8V12l3 2"/>',
    gear: '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.8v2.4M12 18.8v2.4M4.5 12H2.1M21.9 12h-2.4M6.1 6.1 4.4 4.4M19.6 19.6l-1.7-1.7M17.9 6.1l1.7-1.7M4.4 19.6l1.7-1.7"/>',
    search: '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    bell: '<path d="M6.5 10a5.5 5.5 0 0 1 11 0c0 4 1.5 5.5 1.5 5.5H5S6.5 14 6.5 10z"/><path d="M10 18.5a2.2 2.2 0 0 0 4 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    minus: '<path d="M5 12h14"/>',
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    check: '<polyline points="20 6 9 17 4 12"/>',
    alert: '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18v.5"/>',
    clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2"/>',
    dl: '<path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M5 19h14"/>',
    up: '<path d="M12 20V4"/><path d="m6 9.5 6-6 6 6"/>',
    left: '<path d="m14 6-6 6 6 6"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    edit: '<path d="M4 20h4L20 8l-4-4L4 16z"/><path d="m14 6 4 4"/>',
    trash: '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
    send: '<path d="M21 3 3 10.5l7 3 3 7z"/>',
    lock: '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    refresh: '<path d="M20 11a8 8 0 1 0-2.6 6.4"/><path d="M20 4v7h-7"/>'
  };
  function ico(n, cls) {
    return '<svg ' + (cls ? 'class="' + cls + '" ' : '') + 'viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' + (ICON[n] || '') + '</svg>';
  }

  /* =====================  ОБЩИЕ БЛОКИ  ===================== */
  function head(eyebrow, title, sub, actions) {
    return '<div class="h"><div><p class="h__eyebrow">' + E(eyebrow) + '</p>' +
      '<h2 class="h__title">' + E(title) + '</h2>' +
      (sub ? '<p class="h__sub">' + sub + '</p>' : '') + '</div>' +
      (actions ? '<div class="h__actions">' + actions + '</div>' : '') + '</div>';
  }
  function card(title, body, opts) {
    opts = opts || {};
    var flat = opts.flat || /<table|class="thread|class="acc/.test(body);
    return '<section class="card' + (opts.glow ? ' card--glow' : '') + (flat ? ' card--flat' : '') + '"' +
      (opts.style ? ' style="' + opts.style + '"' : '') + '>' +
      (title ? '<div class="card__head"><h3 class="card__title">' + title + '</h3>' +
        (opts.meta || '') + (opts.more || '') + '</div>' : '') +
      (flat ? '<div class="card__scroll"' + (opts.scrollH ? ' style="max-height:' + opts.scrollH + 'px"' : '') + '>' +
        body + '</div>' : body) + '</section>';
  }
  function empty(text, sub) {
    return '<div class="empty">' + ico('search') + '<b>' + E(text) + '</b>' +
      (sub ? '<span class="dim">' + E(sub) + '</span>' : '') + '</div>';
  }
  function statusTag(st) {
    var m = {
      paid: ['ok', 'оплачена'], pending: ['warn', 'проверка'], unpaid: ['mut', 'не оплачена'],
      canceled: ['bad', 'отменена'], refunded: ['info', 'возврат'],
      open: ['warn', 'открыт'], closed: ['mut', 'закрыт'],
      active: ['ok', 'активен'], blocked: ['bad', 'заблокирован']
    }[st] || ['mut', st];
    return '<span class="tag tag--' + m[0] + '">' + m[1] + '</span>';
  }
  function walletTypeName(t) {
    return {
      credit: 'Начисление', debit: 'Списание', transfer_in: 'Перевод (вход)', transfer_out: 'Перевод в игру',
      purchase: 'Покупка', refund: 'Возврат', deposit: 'Пополнение', rollback: 'Откат'
    }[t] || t;
  }

  /* =====================  РАСЧЁТЫ  ===================== */
  function stats() {
    var online = S.characters.filter(function (c) { return c.online; }).length;
    var openTickets = S.tickets.filter(function (t) { return t.status === 'open'; });
    var pendingPays = S.payments.filter(function (p) { return p.status === 'pending'; });
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var todayPaid = S.payments.filter(function (p) { return p.status === 'paid' && p.createdAt >= today.getTime(); });
    var totalBal = S.players.reduce(function (a, p) { return a + p.balance; }, 0);
    var monthPaid = S.payments.filter(function (p) { return p.status === 'paid' && p.createdAt >= Date.now() - 30 * 86400000; });
    var expiring = S.bans.filter(function (b) { return b.until > Date.now() && b.until < Date.now() + 6 * 3600000; });
    return {
      players: S.players.length,
      characters: S.characters.length,
      online: online,
      openTickets: openTickets.length,
      pending: pendingPays.length,
      todaySum: todayPaid.reduce(function (a, p) { return a + p.usd; }, 0),
      todayCount: todayPaid.length,
      totalBalance: totalBal,
      balance: totalBal,
      monthSum: monthPaid.reduce(function (a, p) { return a + p.usd; }, 0),
      monthCount: monthPaid.length,
      expiringBans: expiring,
      accounts: S.accounts.length,
      banned: S.accounts.filter(function (a) { return a.banned; }).length
    };
  }
  function bonusFor(coins) {
    var b = S.settings.bonuses;
    for (var i = 0; i < b.length; i++) {
      if (coins >= b[i].from && (b[i].to === null || coins <= b[i].to)) return b[i].percent;
    }
    return 0;
  }

  /* =====================  ПУЛЬТ  ===================== */
  function viewHome() {
    var s = stats();
    var tasks = [];
    if (s.openTickets) {
      tasks.push({
        icon: 'lifebuoy', kind: 'bad', go: 'tickets',
        title: 'Открытых тикетов: <b>' + s.openTickets + '</b>',
        sub: 'Самый старый ждёт ' + ago(Math.min.apply(null, S.tickets.filter(function (t) { return t.status === 'open'; }).map(function (t) { return t.createdAt; }))) + '. Ответьте игрокам.'
      });
    }
    if (s.pending) {
      var pend = S.payments.filter(function (p) { return p.status === 'pending'; })[0];
      tasks.push({
        icon: 'clock', kind: 'warn', go: 'finance', ids: 'pay:' + pend.id,
        title: 'Заявка <b>#' + pend.id + '</b> ждёт проверки',
        sub: pend.player + ' · ' + coin(pend.coins) + ' · ' + pend.gateway + (pend.testMode ? ' · счёт в тестовом режиме' : '')
      });
    }
    s.expiringBans.forEach(function (b) {
      tasks.push({
        icon: 'clock', kind: 'info', go: 'bans',
        title: 'Бан истекает через ' + Math.max(1, Math.round((b.until - Date.now()) / 3600000)) + ' ч',
        sub: b.login + ' · ' + b.reason + ' · выдал ' + b.by
      });
    });
    if (!tasks.length) tasks.push({ icon: 'check', kind: 'ok', go: 'home', title: 'Всё разобрано', sub: 'Нет тикетов без ответа, заявок на проверке и истекающих банов.' });

    var feed = S.log.slice(0, 12).map(function (l) {
      return '<div class="feed__i"><span class="feed__dot"></span><div class="feed__t"><b>' + E(l.actor) + '</b> · ' +
        E(l.action) + (l.target && l.target !== '—' ? ' → <b>' + E(l.target) + '</b>' : '') +
        (l.details ? '<div class="dim">' + E(l.details) + '</div>' : '') + '</div>' +
        '<span class="feed__tm">' + dshort(l.at) + '</span></div>';
    }).join('');

    var pays = S.payments.slice(0, 6).map(function (p) {
      return '<tr data-pay="' + p.id + '"><td class="mut num">#' + p.id + '</td><td>' + E(p.player) + '</td>' +
        '<td class="gold num">' + coin(p.coins) + '</td><td class="dim">' + E(p.gateway) + '</td>' +
        '<td>' + statusTag(p.status) + '</td></tr>';
    }).join('');

    var online = S.characters.filter(function (c) { return c.online; }).slice(0, 6).map(function (c) {
      return '<tr data-char="' + E(c.name) + '"><td>' + E(c.name) + '</td><td class="num">' + c.level + '</td>' +
        '<td class="dim">' + E(c.clan || '—') + '</td><td class="dim">' + E(c.account) + '</td></tr>';
    }).join('');

    return head('LA2PLUS · ПУЛЬТ', 'Добрый вечер, ' + S.me.login.replace(/[0-9]+$/, ''),
      'На сервере <b class="gold">' + s.online + '</b> игроков · открытых тикетов ' + s.openTickets +
      ' · заявок на проверке ' + s.pending,
      '<button class="btn btn--ghost" data-go="settings">' + ico('gear') + 'Настройки</button>' +
      (UI.can(window.DB.CAP.characters) ? '<button class="btn btn--primary" data-act="give-item">' + ico('plus') + 'Выдать предмет</button>' : '') +
      (UI.can(window.DB.CAP.financeEdit) ? '<button class="btn" data-act="balance-op">' + ico('coin') + 'Начислить</button>' : '')) +

      '<div class="kpi-row">' +
      kpi('Игроков на сайте', nf(s.players), 'аккаунтов сайта', s.players, 'players') +
      kpi('Персонажей', nf(s.characters), 'в игре ' + s.online, s.characters, 'characters') +
      kpi('Тикетов открыто', nf(s.openTickets), 'ждут ответа', s.openTickets, 'tickets') +
      kpi('Баланс игроков', nf(s.totalBalance, 2),
        'Coin · висит на удалённых: ' + nf(S.dbSnapshot && S.dbSnapshot.balanceOrphan || 0), null, 'finance') +
      kpi('Поступления сегодня', '$' + nf(s.todaySum), s.todayCount + ' заявок', null, 'finance') +
      '</div>' +

      '<div class="grid-2 mt14">' +
      card('Требует внимания <span class="tag tag--bad">' + tasks.length + '</span>',
        '<div style="padding:4px">' + tasks.map(function (t, i) {
          return '<div class="todo" data-task="' + i + '"><div class="todo__ic ' + t.kind + '">' + ico(t.icon) + '</div>' +
            '<div style="min-width:0"><p class="todo__t">' + t.title + '</p><p class="todo__s">' + E(t.sub) + '</p></div>' +
            '<span class="todo__go">Открыть →</span></div>';
        }).join('') + '</div>', { glow: true }) +
      card('Живая лента', feed,
        { scrollH: 340, flat: true }) +
      '</div>' +

      '<div class="grid-2 mt14">' +
      card('Последние заявки', '<table class="t"><thead><tr><th>№</th><th>Игрок</th><th>Сумма</th>' +
        '<th>Шлюз</th><th>Статус</th></tr></thead><tbody>' + pays + '</tbody></table>',
        { more: '<span class="card__more" data-go="finance">Все заявки</span>' }) +
      card('Онлайн сейчас', '<table class="t"><thead><tr><th>Персонаж</th><th>Ур.</th><th>Клан</th>' +
        '<th>Аккаунт</th></tr></thead><tbody>' + online + '</tbody></table>',
        { meta: '<span class="tag tag--ok">' + s.online + '</span>', more: '<span class="card__more" data-go="characters">Все персонажи</span>' }) +
      '</div>' +

      tasksHook(tasks);
  }
  function kpi(label, value, hint, raw, go) {
    return '<div class="kpi" data-go="' + go + '">' +
      '<p class="kpi__l">' + E(label) + '</p>' +
      '<div class="kpi__v">' + value + '</div>' +
      '<div class="kpi__d">' + E(hint) + '</div>' +
      '<span class="kpi__arrow">' + ico('up') + '</span></div>';
  }
  function tasksHook(tasks) {
    // после вставки в DOM — навешиваем переходы
    setTimeout(function () {
      document.querySelectorAll('[data-task]').forEach(function (el) {
        el.addEventListener('click', function () {
          var t = tasks[+el.getAttribute('data-task')];
          if (t.go === 'bans') { go('accounts'); return; }
          go(t.go);
        });
      });
    }, 0);
    return '';
  }

  /* =====================  ИГРОКИ  ===================== */
  var playersFilter = { q: '', sort: 'id', only: 'all', page: 1, per: 12 };
  function viewPlayers() {
    var q = playersFilter.q.toLowerCase();
    var list = S.players.filter(function (p) {
      if (q && p.login.toLowerCase().indexOf(q) < 0 && p.email.toLowerCase().indexOf(q) < 0) return false;
      if (playersFilter.only === 'with-balance' && p.balance <= 0) return false;
      if (playersFilter.only === 'no-char' && S.characters.some(function (c) { return c.playerId === p.id; })) return false;
      return true;
    });
    list.sort(function (a, b) {
      if (playersFilter.sort === 'balance') return b.balance - a.balance;
      if (playersFilter.sort === 'last') return b.lastLogin - a.lastLogin;
      if (playersFilter.sort === 'login') return a.login.localeCompare(b.login);
      return a.id - b.id;
    });
    var total = list.length;
    var pages = Math.max(1, Math.ceil(total / playersFilter.per));
    if (playersFilter.page > pages) playersFilter.page = pages;
    var from = (playersFilter.page - 1) * playersFilter.per;
    var slice = list.slice(from, from + playersFilter.per);

    /* На телефоне шесть колонок не влезают в 390px и таблица уезжала за
       край. Ниже 860px рисуем список: имя, почта, баланс и вход в две строки. */
    var narrow = window.matchMedia('(max-width:860px)').matches;

    var rows = slice.map(function (p) {
      var chars = S.characters.filter(function (c) { return c.playerId === p.id; }).length;

      if (narrow) {
        return '<tr data-player="' + p.id + '"><td>' +
          '<div class="plrow"><div class="plrow__top"><b>' + E(p.login) + '</b>' +
          (p.role ? ' <span class="tag tag--info">' + E(UI.roleName(p.role)) + '</span>' : '') +
          '<span class="plrow__bal gold num">' + nf(p.balance, 2) + '</span></div>' +
          '<div class="plrow__sub">#' + p.id + ' · ' + E(p.email) + '</div>' +
          '<div class="plrow__sub">персонажей ' + chars + ' · вход ' +
          (p.lastLogin ? dshort(p.lastLogin) : 'не входил') + ' · ' + statusTag(p.status) + '</div>' +
          '</div></td></tr>';
      }

      return '<tr data-player="' + p.id + '">' +
        '<td class="mut num">' + p.id + '</td>' +
        '<td><b>' + E(p.login) + '</b>' + (p.role ? ' <span class="tag tag--info">' + E(UI.roleName(p.role)) + '</span>' : '') +
        '<div class="dim">' + E(p.email) + '</div></td>' +
        '<td class="gold num">' + nf(p.balance, 2) + '</td>' +
        '<td class="num">' + chars + '</td>' +
        '<td class="dim nowrap">' + dshort(p.lastLogin) + '</td>' +
        '<td>' + statusTag(p.status) + '</td></tr>';
    }).join('');

    return head('LA2PLUS · ИГРОКИ', 'Игроки сайта',
      /* «Всего» — все игроки, а не число после фильтра: иначе при фильтре
         «С балансом» заголовок писал «Всего 2 аккаунтов». */
      'Всего ' + S.players.length + ' аккаунтов, из них персонажей на сервере ' + S.characters.length +
      (total !== S.players.length ? ' · в фильтре ' + total : ''),
      '<button class="btn btn--ghost" data-act="players-csv">' + ico('dl') + 'Выгрузить CSV</button>' +
      (UI.can(window.DB.CAP.usersEdit) ? '<button class="btn btn--primary" data-act="new-player">' + ico('plus') + 'Создать игрока</button>' : '') +
      (UI.can(window.DB.CAP.roles) ? '<button class="btn" data-go="staff">' + ico('shield') + 'Роли</button>' : '')) +

      card('', '<div class="toolbar">' +
        '<div class="search" style="max-width:280px">' + ico('search') +
        '<input data-pf="q" placeholder="Логин или e-mail" value="' + E(playersFilter.q) + '"></div>' +
        '<div class="seg" data-seg="only">' +
        segBtn('all', 'Все', playersFilter.only) + segBtn('with-balance', 'С балансом', playersFilter.only) +
        segBtn('no-char', 'Без персонажей', playersFilter.only) + '</div>' +
        '<div class="seg" data-seg="sort">' +
        segBtn('id', 'По id', playersFilter.sort) + segBtn('balance', 'По балансу', playersFilter.sort) +
        segBtn('last', 'По входу', playersFilter.sort) + '</div>' +
        '<span class="dim" style="margin-left:auto">' + total + ' найдено</span></div>' +
        '<table class="t' + (narrow ? ' t--cards' : '') + '">' +
        (narrow ? '' : '<thead><tr><th>ID</th><th>Игрок</th><th>Баланс</th><th>Персонажи</th>' +
          '<th>Последний вход</th><th>Статус</th></tr></thead>') + '<tbody>' +
        (rows || '<tr><td colspan="6">' + empty('Ничего не найдено', 'Измените запрос или фильтр') + '</td></tr>') +
        '</tbody></table>' + pager(total, playersFilter.page, pages, 'players'), { style: 'margin-bottom:14px' });
  }
  function segBtn(val, label, cur) {
    return '<button data-val="' + val + '" class="' + (cur === val ? 'on' : '') + '">' + label + '</button>';
  }
  function pager(total, page, pages, kind) {
    var btns = '';
    for (var i = 1; i <= Math.min(pages, 7); i++) btns += '<button class="pager__i' + (i === page ? ' on' : '') + '" data-page="' + i + '" data-kind="' + kind + '">' + i + '</button>';
    return '<div class="pager">' + btns + '<span class="dim">стр. ' + page + ' из ' + pages + ' · всего ' + total + '</span></div>';
  }

  function openNewPlayer() {
    if (!UI.can(window.DB.CAP.usersEdit)) return UI.toast('Недостаточно прав', 'bad');
    UI.openModal({
      title: 'Создать игрока', sub: 'Аккаунт на сайте. Игровой аккаунт Lineage привязывается отдельно',
      body: '<div class="form-row"><label class="field"><span class="field__l">Логин</span>' +
        '<input id="np-login" placeholder="латиница, цифры, от 3 символов"></label>' +
        '<label class="field"><span class="field__l">E-mail</span><input id="np-mail" placeholder="почта для входа"></label></div>' +
        '<div class="form-row"><label class="field"><span class="field__l">Пароль</span>' +
        '<div class="flex gap8"><input id="np-pass" type="password" value="' + Math.random().toString(36).slice(2, 10) + '">' +
        '<button class="btn btn--icon" data-np-eye title="Показать пароль">' + ico('key') + '</button></div>' +
        '<p class="field__h">Показывается один раз — передайте игроку.</p></label>' +
        '<label class="field"><span class="field__l">Стартовый баланс, Coin</span><input id="np-bal" type="number" min="0" value="0"></label></div>' +
        '<div id="np-check" class="dim"></div>',
      foot: '<button class="btn btn--primary" id="np-send">' + ico('plus') + 'Создать</button>',
      onMount: function (m) {
        var lg = m.querySelector('#np-login'), ps = m.querySelector('#np-pass');
        m.querySelector('[data-np-eye]').addEventListener('click', function () {
          ps.type = ps.type === 'password' ? 'text' : 'password';
        });
        function check() {
          var v = lg.value.trim(), out = [];
          if (v.length && v.length < 3) out.push('логин короче 3 символов');
          if (!/^[a-zA-Z0-9_]*$/.test(v)) out.push('только латиница, цифры и _');
          if (S.players.some(function (p) { return p.login.toLowerCase() === v.toLowerCase(); })) out.push('такой логин уже занят');
          m.querySelector('#np-check').innerHTML = v ? (out.length
            ? '<span style="color:var(--bad)">' + out.join(' · ') + '</span>'
            : '<span style="color:var(--ok)">логин свободен</span>') : '';
          return out.length === 0 && v.length >= 3;
        }
        lg.addEventListener('input', check);
        m.querySelector('#np-send').addEventListener('click', function () {
          if (!check()) return UI.toast('Проверьте логин', 'bad');
          var mail = m.querySelector('#np-mail').value.trim();
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) return UI.toast('Проверьте e-mail', 'bad');
          var bal = Math.max(0, parseFloat(m.querySelector('#np-bal').value) || 0);
          var btn = m.querySelector('#np-send');
          btn.disabled = true;

          /* Создаём и профиль сайта, и игровой аккаунт Lucera2 —
             иначе игрок не сможет войти в игру с этим паролем. */
          window.API.playerCreate({ login: lg.value.trim(), email: mail, password: ps.value, balance: bal })
            .then(function (res) {
              playersFilter.q = res.login; playersFilter.only = 'all'; playersFilter.page = 1;
              UI.closeModal();
              UI.toast('Игрок ' + res.login + ' создан (id ' + res.id + ')', 'ok');
              return window.DB.reload().then(function () { return res; });
            })
            .then(function (res) { render(); openPlayer(res.id); })
            .catch(function (e) {
              btn.disabled = false;
              UI.toast(e.message || 'Игрок не создан', 'bad');
            });
        });
      }
    });
  }

  function openPlayer(id) {
    var p = UI.playerById(id);
    if (!p) return;
    var chars = S.characters.filter(function (c) { return c.playerId === p.id; });
    var accs = S.accounts.filter(function (a) { return a.playerId === p.id; });
    var ops = S.walletOps.filter(function (o) { return o.player === p.login; }).slice(0, 8);
    var pays = S.payments.filter(function (x) { return x.playerId === p.id; }).slice(0, 5);

    UI.openModal({
      wide: true,
      title: p.login,
      sub: 'id ' + p.id + ' · зарегистрирован ' + dt(p.createdAt) + ' · последний вход ' + dt(p.lastLogin),
      body:
        '<div class="mini-stats">' +
        mini('Баланс', coin(p.balance)) +
        mini('Персонажей', chars.length) +
        mini('Аккаунтов сервера', accs.length) +
        mini('Роль', p.role ? UI.roleName(p.role) : 'игрок') +
        '</div>' +
        '<div class="form-row mt14">' +
        '<div><p class="field__l">Персонажи</p>' +
        (chars.length ? '<div class="mlist">' + chars.map(function (c) {
          return '<div class="mlist__r"><span>' + E(c.name) + ' · ' + c.level + ' · ' + E(c.cls) + '</span>' +
            '<span>' + E(c.clan || 'без клана') + '</span></div>';
        }).join('') + '</div>' : '<p class="dim">Нет персонажей на сервере</p>') +
        '</div>' +
        '<div><p class="field__l">Аккаунты Lineage</p>' +
        (accs.length ? '<div class="mlist">' + accs.map(function (a) {
          return '<div class="mlist__r"><span>' + E(a.login) + '</span><span>' +
            (a.banned ? '<span class="tag tag--bad">бан до ' + dshort(a.until || a.banUntil) + '</span>' : '<span class="tag tag--ok">активен</span>') +
            '</span></div>';
        }).join('') + '</div>' : '<p class="dim">Игровой аккаунт не привязан</p>') +
        '</div></div>' +
        '<p class="field__l" style="margin-top:16px">Последние операции кошелька</p>' +
        (ops.length ? '<div class="mlist">' + ops.map(function (o) {
          return '<div class="mlist__r"><span>' + walletTypeName(o.type) + ' · ' + E(o.comment || '—') +
            '<div class="dim">' + dt(o.at) + ' · ' + E(o.by) + '</div></span>' +
            '<span class="' + (o.amount < 0 ? '' : 'gold') + '">' + (o.amount > 0 ? '+' : '') + nf(o.amount) + '</span></div>';
        }).join('') + '</div>' : '<p class="dim">Операций нет</p>') +
        (pays.length ? '<p class="field__l" style="margin-top:14px">Заявки на пополнение</p><div class="mlist">' +
          pays.map(function (x) {
            var canCredit = UI.can(window.DB.CAP.financeEdit) && (x.status === 'pending' || x.status === 'unpaid');
            return '<div class="mlist__r"><span>#' + x.id + ' · ' + E(x.gateway) +
              '<div class="dim">' + dt(x.createdAt) + '</div></span><span>' + coin(x.coins) + ' · ' + statusTag(x.status) +
              (canCredit ? ' <button class="btn btn--sm btn--primary" data-pcard-credit="' + x.id + '">Начислить</button>' : '') +
              '</span></div>';
          }).join('') + '</div>' : '')
        + (UI.can(window.DB.CAP.usersEdit) ? '<div class="flex gap8 mt14 wrap">' +
          '<button class="btn btn--sm" data-pcard-fold="1">' + ico('clock') + 'История входов</button>' +
          '<button class="btn btn--sm" data-pcard-rename="1">' + ico('edit') + 'Правка e-mail</button>' +
          '</div>' : ''),
      foot:
        (UI.can(window.DB.CAP.financeEdit) ? '<button class="btn btn--primary" data-m="bal" data-id="' + p.id + '">' + ico('coin') + 'Начислить / списать</button>' : '') +
        (UI.can(window.DB.CAP.roles) ? '<button class="btn" data-m="role" data-id="' + p.id + '">' + ico('shield') + 'Роль</button>' : '') +
        (UI.can(window.DB.CAP.usersEdit) ? '<button class="btn ' + (p.status === 'blocked' ? '' : 'btn--danger') + '" data-m="block" data-id="' + p.id + '">' +
          ico('lock') + (p.status === 'blocked' ? 'Разблокировать' : 'Заблокировать') + '</button>' : ''),
      onMount: function (m) {
        m.querySelectorAll('[data-m]').forEach(function (b) {
          b.addEventListener('click', function () {
            var a = b.getAttribute('data-m');
            if (a === 'bal') { UI.closeModal(); setTimeout(function () { openBalanceOp(p.login); }, 120); }
            if (a === 'role') { UI.closeModal(); setTimeout(function () { openRoleAssign(p.id); }, 120); }
            if (a === 'block') { toggleBlock(p.id); }
          });
        });
        m.querySelectorAll('[data-pcard-credit]').forEach(function (b) {
          b.addEventListener('click', function () {
            var id = +b.getAttribute('data-pcard-credit');
            UI.closeModal(); setTimeout(function () { creditPayment(id); }, 120);
          });
        });
        var fold = m.querySelector('[data-pcard-fold]');
        if (fold) fold.addEventListener('click', function () {
          UI.openModal({
            title: 'История входов · ' + p.login,
            sub: 'Последние 10 входов на сайт',
            body: '<div class="mlist">' + Array.from({ length: 10 }).map(function (_, i) {
              var t = p.lastLogin - i * 86400000 - i * 2700000;
              return '<div class="mlist__r"><span>' + dt(t) + '<div class="dim">' + (i % 3 === 0 ? 'Chrome · Windows' : i % 3 === 1 ? 'Safari · iPhone' : 'Chrome · Android') + '</div></span>' +
                '<span class="mono">' + p.ip.split('.').slice(0, 3).join('.') + '.' + (2 + i * 7 % 240) + '</span></div>';
            }).join('') + '</div>'
          });
        });
        var rn = m.querySelector('[data-pcard-rename]');
        if (rn) rn.addEventListener('click', function () {
          UI.openModal({
            title: 'Правка e-mail', sub: p.login,
            body: '<label class="field"><span class="field__l">E-mail</span><input id="em-val" value="' + E(p.email) + '"></label>',
            foot: '<button class="btn btn--primary" id="em-save">Сохранить</button>',
            onMount: function (mm) {
              mm.querySelector('#em-save').addEventListener('click', function () {
                var v = mm.querySelector('#em-val').value.trim();
                if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) return UI.toast('Похоже, это не e-mail', 'bad');
                window.API.playerSave({ id: p.id, email: v }).then(function () {
                  UI.closeModal();
                  UI.toast('E-mail обновлён: ' + v, 'ok');
                  return window.DB.reload();
                }).then(render).catch(function (e) { UI.toast(e.message || 'Не сохранилось', 'bad'); });
              });
            }
          });
        });
      }
    });
  }
  function mini(label, value) {
    return '<div class="mini"><p class="mini__l">' + E(label) + '</p><p class="mini__v">' + E(value) + '</p></div>';
  }
  function toggleBlock(id) {
    var p = UI.playerById(id);
    var blocking = p.status !== 'blocked';
    UI.confirmModal({
      title: blocking ? 'Заблокировать игрока' : 'Разблокировать игрока',
      sub: p.login,
      text: blocking
        ? 'Игрок не сможет входить на сайт и в кабинет. Персонажи и вещи на сервере не удаляются. Действие попадёт в журнал.'
        : 'Игрок снова сможет войти на сайт. Игровые аккаунты разблокируются отдельно, в разделе «Аккаунты».',
      danger: blocking, okText: blocking ? 'Заблокировать' : 'Разблокировать',
      onOk: function () {
        /* Блокировка входа на сайт — это users.activated = 0. */
        window.API.playerSave({ id: p.id, activated: blocking ? 0 : 1 }).then(function () {
          UI.toast(blocking ? 'Игрок ' + p.login + ' заблокирован' : 'Игрок ' + p.login + ' разблокирован', blocking ? 'warn' : 'ok');
          return window.DB.reload();
        }).then(render).catch(function (e) { UI.toast(e.message || 'Не удалось изменить доступ', 'bad'); });
      }
    });
  }

  /* =====================  АККАУНТЫ СЕРВЕРА  ===================== */
  var accFilter = { q: '', only: 'all', page: 1, per: 12 };
  function viewAccounts() {
    var q = accFilter.q.toLowerCase();
    var list = S.accounts.filter(function (a) {
      if (q && a.login.toLowerCase().indexOf(q) < 0 && a.ip.indexOf(q) < 0) return false;
      if (accFilter.only === 'online' && !a.online) return false;
      if (accFilter.only === 'banned' && !a.banned) return false;
      if (accFilter.only === 'noplayer' && a.playerId) return false;
      return true;
    });
    var pages = Math.max(1, Math.ceil(list.length / accFilter.per));
    if (accFilter.page > pages) accFilter.page = pages;
    var from = (accFilter.page - 1) * accFilter.per;
    var rows = list.slice(from, from + accFilter.per).map(function (a) {
      var chars = S.characters.filter(function (c) { return c.account === a.login; });
      return '<tr data-acc="' + E(a.login) + '">' +
        '<td><b>' + E(a.login) + '</b>' + (a.online ? ' <span class="tag tag--ok">в игре</span>' : '') +
        '<div class="dim">' + chars.length + ' персонажей' + (a.playerId ? ' · профиль #' + a.playerId : ' · без профиля') + '</div></td>' +
        '<td class="dim mono">' + E(a.ip) + '</td>' +
        '<td class="dim nowrap">' + dshort(a.lastLogin) + '</td>' +
        '<td>' + (a.banned ? '<span class="tag tag--bad">бан до ' + dshort(a.banUntil) + '</span>' : '<span class="tag tag--ok">активен</span>') + '</td>' +
        '</tr>';
    }).join('');

    return head('LA2PLUS · СЕРВЕР', 'Аккаунты сервера',
      'Игровых аккаунтов ' + S.accounts.length + ' · забанено ' + S.accounts.filter(function (a) { return a.banned; }).length +
      ' · онлайн ' + S.accounts.filter(function (a) { return a.online; }).length,
      '<button class="btn btn--ghost" data-act="accounts-csv">' + ico('dl') + 'Выгрузить CSV</button>' +
      (UI.can(window.DB.CAP.characters) ? '<button class="btn btn--danger" data-act="ban">' + ico('lock') + 'Забанить аккаунт</button>' : '')) +
      card('', '<div class="toolbar">' +
        '<div class="search" style="max-width:280px">' + ico('search') +
        '<input data-af="q" placeholder="Логин или IP" value="' + E(accFilter.q) + '"></div>' +
        '<div class="seg" data-seg="acc-only">' +
        segBtn('all', 'Все', accFilter.only) + segBtn('online', 'В игре', accFilter.only) +
        segBtn('banned', 'Забанены', accFilter.only) + segBtn('noplayer', 'Без профиля', accFilter.only) + '</div>' +
        '<span class="dim" style="margin-left:auto">' + list.length + ' найдено</span></div>' +
        '<table class="t"><thead><tr><th>Аккаунт</th><th>IP</th><th>Последний вход</th><th>Доступ</th></tr></thead>' +
        '<tbody>' + (rows || '<tr><td colspan="4">' + empty('Аккаунтов не найдено') + '</td></tr>') + '</tbody></table>' +
        pager(list.length, accFilter.page, pages, 'acc'));
  }
  function openAccount(login) {
    var a = S.accounts.filter(function (x) { return x.login === login; })[0];
    if (!a) return;
    var chars = S.characters.filter(function (c) { return c.account === a.login; });
    var p = a.playerId ? UI.playerById(a.playerId) : null;
    UI.openModal({
      wide: true,
      title: a.login,
      sub: (p ? 'профиль на сайте: ' + p.login + ' · ' : 'профиль не привязан · ') + 'IP ' + a.ip,
      body: '<div class="mini-stats">' + mini('Доступ', a.banned ? 'забанен' : 'активен') +
        mini('Последний вход', dshort(a.lastLogin)) + mini('Персонажей', chars.length) +
        mini('Баланс профиля', p ? coin(p.balance) : '—') + '</div>' +
        (a.banned ? '<div class="alert alert--bad mt14">' + ico('lock') + '<div><b>Забанен до ' + dt(a.banUntil) + '</b>' +
          '<div class="dim">Причина: ' + E(a.reason || a.banReason) + ' · выдал ' + E(a.by || a.banBy) + '</div></div></div>' : '') +
        '<p class="field__l" style="margin-top:16px">Персонажи</p>' +
        (chars.length ? '<table class="t"><thead><tr><th>Имя</th><th>Ур.</th><th>Класс</th><th>Клан</th><th>PvP/PK</th></tr></thead><tbody>' +
          chars.map(function (c) {
            return '<tr data-char="' + E(c.name) + '"><td>' + E(c.name) + '</td><td class="num">' + c.level + '</td>' +
              '<td class="dim">' + E(c.cls) + '</td><td class="dim">' + E(c.clan || '—') + '</td>' +
              '<td class="num">' + c.pvp + ' / ' + c.pk + '</td></tr>';
          }).join('') + '</tbody></table>' : '<p class="dim">Персонажей нет</p>'),
      foot: (UI.can(window.DB.CAP.characters) ? '<button class="btn ' + (a.banned ? '' : 'btn--danger') + '" data-a="ban">' +
        ico('lock') + (a.banned ? 'Снять бан' : 'Забанить') + '</button>' : '') +
        (UI.can(window.DB.CAP.characters) ? '<button class="btn" data-a="item">' + ico('plus') + 'Выдать предмет</button>' : ''),
      onMount: function (m) {
        var b = m.querySelector('[data-a="ban"]');
        if (b) b.addEventListener('click', function () { UI.closeModal(); setTimeout(function () { openBanForm(a.login); }, 120); });
        var i = m.querySelector('[data-a="item"]');
        if (i) i.addEventListener('click', function () {
          UI.closeModal(); setTimeout(function () { openGiveForm(a.login, chars[0] ? chars[0].name : ''); }, 120);
        });
        m.querySelectorAll('[data-char]').forEach(function (r) {
          r.addEventListener('click', function () { UI.closeModal(); setTimeout(function () { openChar(r.getAttribute('data-char')); }, 120); });
        });
      }
    });
  }

  /* =====================  ПЕРСОНАЖИ  ===================== */
  var charFilter = { q: '', only: 'all', page: 1, per: 12 };
  function viewCharacters() {
    var q = charFilter.q.toLowerCase();
    var list = S.characters.filter(function (c) {
      if (q && c.name.toLowerCase().indexOf(q) < 0 && c.account.toLowerCase().indexOf(q) < 0) return false;
      if (charFilter.only === 'online' && !c.online) return false;
      if (charFilter.only === 'offline' && c.online) return false;
      if (charFilter.only === 'no-clan' && c.clan) return false;
      return true;
    });
    var pages = Math.max(1, Math.ceil(list.length / charFilter.per));
    if (charFilter.page > pages) charFilter.page = pages;
    var from = (charFilter.page - 1) * charFilter.per;
    var rows = list.slice(from, from + charFilter.per).map(function (c) {
      return '<tr data-char="' + E(c.name) + '">' +
        '<td><b>' + E(c.name) + '</b>' + (c.online ? ' <span class="tag tag--ok">в игре</span>' : '') +
        '<div class="dim">' + E(c.account) + '</div></td>' +
        '<td class="num">' + c.level + '</td><td class="dim">' + E(c.cls) + '</td>' +
        '<td>' + (c.clan ? E(c.clan) : '<span class="mut">—</span>') + '</td>' +
        '<td class="num">' + c.pvp + '</td><td class="num">' + c.pk + '</td>' +
        '<td class="num">' + c.karma + '</td></tr>';
    }).join('');

    return head('LA2PLUS · СЕРВЕР', 'Персонажи',
      'Всего ' + S.characters.length + ' персонажей · в игре ' + S.characters.filter(function (c) { return c.online; }).length,
      '<button class="btn btn--ghost" data-act="chars-csv">' + ico('dl') + 'Выгрузить CSV</button>' +
      (UI.can(window.DB.CAP.characters) ? '<button class="btn btn--primary" data-act="give-item">' + ico('plus') + 'Выдать предмет</button>' : '')) +
      card('', '<div class="toolbar">' +
        '<div class="search" style="max-width:280px">' + ico('search') +
        '<input data-cf="q" placeholder="Имя персонажа или аккаунт" value="' + E(charFilter.q) + '"></div>' +
        '<div class="seg" data-seg="char-only">' + segBtn('all', 'Все', charFilter.only) +
        segBtn('online', 'В игре', charFilter.only) + segBtn('offline', 'Офлайн', charFilter.only) +
        segBtn('no-clan', 'Без клана', charFilter.only) + '</div>' +
        '<span class="dim" style="margin-left:auto">' + list.length + ' найдено</span></div>' +
        '<table class="t"><thead><tr><th>Персонаж</th><th>Ур.</th><th>Класс</th><th>Клан</th>' +
        '<th>PvP</th><th>PK</th><th>Карма</th></tr></thead><tbody>' +
        (rows || '<tr><td colspan="7">' + empty('Персонажей не найдено') + '</td></tr>') + '</tbody></table>' +
        pager(list.length, charFilter.page, pages, 'char'));
  }
  function openChar(name) {
    var c = S.characters.filter(function (x) { return x.name === name; })[0];
    if (!c) return;
    var acc = S.accounts.filter(function (a) { return a.login === c.account; })[0];
    UI.openModal({
      title: c.name,
      sub: c.level + ' уровень · ' + c.cls + ' · аккаунт ' + c.account,
      body: '<div class="mini-stats">' + mini('Клан', c.clan || '—') + mini('PvP', c.pvp) +
        mini('PK', c.pk) + mini('Карма', c.karma) + '</div>' +
        (acc && acc.banned ? '<div class="alert alert--bad mt14">' + ico('lock') + '<div>Аккаунт забанен до ' +
          dt(acc.banUntil) + '</div></div>' : ''),
      foot: (UI.can(window.DB.CAP.characters) ? '<button class="btn btn--primary" data-x="item">' + ico('plus') + 'Выдать предмет</button>' : '') +
        (UI.can(window.DB.CAP.characters) && acc ? '<button class="btn btn--danger" data-x="ban">' + ico('lock') + 'Бан аккаунта</button>' : ''),
      onMount: function (m) {
        var i = m.querySelector('[data-x="item"]');
        if (i) i.addEventListener('click', function () { UI.closeModal(); setTimeout(function () { openGiveForm(c.account, c.name); }, 120); });
        var b = m.querySelector('[data-x="ban"]');
        if (b) b.addEventListener('click', function () { UI.closeModal(); setTimeout(function () { openBanForm(c.account); }, 120); });
      }
    });
  }

  /* =====================  КЛАНЫ  ===================== */
  function viewClans() {
    var cards = S.clans.map(function (cl) {
      var members = S.characters.filter(function (c) { return c.clan === cl.name; });
      var online = members.filter(function (c) { return c.online; }).length;
      return '<div class="clan" data-clan="' + E(cl.name) + '">' +
        '<div class="clan__top"><div class="clan__crest">' + cl.name.slice(0, 1) + '</div>' +
        '<div><p class="clan__n">' + E(cl.name) + '</p><p class="clan__s">лидер ' + E(cl.leader) + '</p></div>' +
        (cl.castle ? '<span class="tag tag--info" style="margin-left:auto">' + E(cl.castle) + '</span>' : '') + '</div>' +
        '<div class="clan__stats">' +
        '<span><b>' + members.length + '</b> участников</span>' +
        '<span><b>' + online + '</b> в игре</span>' +
        '<span>ур. <b>' + cl.level + '</b></span>' +
        '<span>реп. <b>' + nf(cl.rep) + '</b></span></div></div>';
    }).join('');
    return head('LA2PLUS · СЕРВЕР', 'Кланы',
      S.clans.length + ' кланов · суммарно ' + S.characters.filter(function (c) { return c.clan; }).length + ' персонажей в кланах',
      '<button class="btn btn--ghost" data-act="clans-csv">' + ico('dl') + 'Выгрузить CSV</button>') +
      '<div class="clan-grid">' + cards + '</div>';
  }
  function openClan(name) {
    var cl = S.clans.filter(function (x) { return x.name === name; })[0];
    if (!cl) return;
    var members = S.characters.filter(function (c) { return c.clan === name; });
    UI.openModal({
      wide: true, title: cl.name,
      sub: 'лидер ' + cl.leader + ' · ' + members.length + ' участников · репутация ' + nf(cl.rep) + (cl.castle ? ' · замок ' + cl.castle : ''),
      body: '<table class="t"><thead><tr><th>Участник</th><th>Ур.</th><th>Класс</th><th>PvP</th><th>Статус</th></tr></thead><tbody>' +
        (members.length ? members.map(function (c) {
          return '<tr data-char="' + E(c.name) + '"><td>' + E(c.name) + (c.name === cl.leader ? ' <span class="tag tag--gold tag--info">лидер</span>' : '') +
            '</td><td class="num">' + c.level + '</td><td class="dim">' + E(c.cls) + '</td>' +
            '<td class="num">' + c.pvp + '</td><td>' + (c.online ? '<span class="tag tag--ok">в игре</span>' : '<span class="tag tag--mut">офлайн</span>') + '</td></tr>';
        }).join('') : '<tr><td colspan="5">' + empty('В клане пока никого') + '</td></tr>') + '</tbody></table>',
      foot: '<button class="btn btn--ghost" data-clan-csv="1">' + ico('dl') + 'Выгрузить состав</button>',
      onMount: function (m) {
        m.querySelectorAll('[data-char]').forEach(function (r) {
          r.addEventListener('click', function () { UI.closeModal(); setTimeout(function () { openChar(r.getAttribute('data-char')); }, 120); });
        });
        m.querySelector('[data-clan-csv]').addEventListener('click', function () {
          UI.exportCsv('clan-' + name + '.csv', [['Участник', 'Уровень', 'Класс', 'PvP', 'PK', 'Онлайн']].concat(
            members.map(function (c) { return [c.name, c.level, c.cls, c.pvp, c.pk, c.online ? 'да' : 'нет']; })));
        });
      }
    });
  }

  /* =====================  ИГРОВЫЕ ДЕЙСТВИЯ  ===================== */
  function viewGame() {
    var canGame = UI.can(window.DB.CAP.characters);
    var queue = S.itemQueue.slice().sort(function (a, b) { return b.at - a.at; });
    var rows = queue.map(function (q) {
      return '<tr><td class="mut num">' + q.id + '</td><td>' + E(q.login) + ' / <b>' + E(q.char) + '</b></td>' +
        '<td>' + E(q.itemName) + ' <span class="dim">(' + q.itemId + ')</span></td>' +
        '<td class="num">' + q.count + '</td><td class="num">' + q.enchant + '</td>' +
        '<td class="dim">' + E(q.reason || '—') + '<div class="dim">' + dt(q.at) + ' · ' + E(q.by) + '</div></td>' +
        '<td>' + (q.done ? '<span class="tag tag--ok">выдано</span>' : '<span class="tag tag--warn">в очереди</span>') + '</td>' +
        '<td class="nowrap">' + (!q.done && canGame ? '<button class="btn btn--sm" data-qdone="' + q.id + '">Отметить выдачу</button> ' : '') +
        (!q.done && canGame ? '<button class="btn btn--sm btn--danger" data-qdel="' + q.id + '">Отменить</button>' : '') + '</td></tr>';
    }).join('');

    var forms = canGame ? '<div class="grid-2 accordions">' +
      acc('give', 'Выдать предмет', 'Предмет попадёт в очередь выдачи — сервер отдаст его при следующем входе персонажа.',
        '<div class="form-row"><label class="field"><span class="field__l">Игровой аккаунт</span>' +
        '<input id="qg-login" list="qg-acc" placeholder="например, Twinservers">' +
        '<datalist id="qg-acc">' + S.accounts.map(function (a) { return '<option value="' + E(a.login) + '">'; }).join('') + '</datalist></label>' +
        '<label class="field"><span class="field__l">Персонаж</span><input id="qg-char" list="qg-ch" placeholder="имя персонажа">' +
        '<datalist id="qg-ch">' + S.characters.map(function (c) { return '<option value="' + E(c.name) + '">'; }).join('') + '</datalist></label></div>' +
        '<div class="form-row"><label class="field"><span class="field__l">ID предмета</span><input id="qg-item" type="number" value="57"></label>' +
        '<label class="field"><span class="field__l">Количество</span><input id="qg-count" type="number" min="1" value="1"></label></div>' +
        '<div class="form-row"><label class="field"><span class="field__l">Заточка</span><input id="qg-ench" type="number" min="0" max="30" value="0"></label>' +
        '<label class="field"><span class="field__l">Причина</span><input id="qg-reason" placeholder="компенсация, приз"></label></div>' +
        '<button class="btn btn--primary" data-quick-give="1">' + ico('plus') + 'Выдать предмет</button>') +
      acc('ban', 'Забанить аккаунт', 'Персонажи и вещи не удаляются, игрок увидит причину при входе.',
        '<div class="form-row"><label class="field"><span class="field__l">Игровой аккаунт</span>' +
        '<input id="qb-login" list="qb-acc" placeholder="логин в игре">' +
        '<datalist id="qb-acc">' + S.accounts.map(function (a) { return '<option value="' + E(a.login) + '">'; }).join('') + '</datalist></label>' +
        '<label class="field"><span class="field__l">Срок</span><select id="qb-hours">' +
        '<option value="1">1 час</option><option value="24" selected>24 часа</option><option value="168">7 дней</option>' +
        '<option value="720">30 дней</option><option value="-1">навсегда</option></select></label></div>' +
        '<label class="field"><span class="field__l">Причина</span><input id="qb-reason" placeholder="нарушение правил"></label>' +
        '<button class="btn btn--danger" data-quick-ban="1">' + ico('lock') + 'Забанить</button>') +
      '</div>' : '';

    return head('LA2PLUS · ДЕЙСТВИЯ', 'Игровые действия',
      'Выдача предметов и блокировка аккаунтов. Каждое действие записывается в журнал с вашим именем.',
      canGame ? '<button class="btn btn--ghost" data-act="give-item">' + ico('plus') + 'Выдать предмет…</button>' +
        '<button class="btn btn--danger" data-act="ban">' + ico('lock') + 'Забанить…</button>'
        : '<span class="tag tag--mut">' + ico('lock') + 'Недостаточно прав</span>') +
      forms +
      '<div class="stack">' +
      card('Очередь выдачи', (rows ?
        '<table class="t"><thead><tr><th>№</th><th>Кому</th><th>Предмет</th><th>Кол-во</th><th>Заточка</th>' +
        '<th>Причина</th><th>Статус</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>'
        : empty('Очередь пуста', 'Выдайте предмет — он появится здесь')), { glow: true, flat: true }) +
      card('Действующие баны', (S.accounts.filter(function (a) { return a.banned; }).length ?
        '<table class="t"><thead><tr><th>Аккаунт</th><th>До</th><th>Причина</th><th></th></tr></thead><tbody>' +
        S.accounts.filter(function (a) { return a.banned; }).map(function (a) {
          return '<tr data-acc="' + E(a.login) + '"><td><b>' + E(a.login) + '</b></td>' +
            '<td class="dim">' + dt(a.banUntil) + '</td><td class="dim">' + E(a.banReason || '—') + '</td>' +
            '<td>' + (canGame ? '<button class="btn btn--sm" data-unban="' + E(a.login) + '">Снять</button>' : '') + '</td></tr>';
        }).join('') + '</tbody></table>' : empty('Банов нет', 'Все аккаунты в порядке')), { flat: true }) +
      '</div>';
  }

  function acc(key, title, hint, body) {
    return '<section class="acc" data-acc-item="' + key + '">' +
      '<button class="acc__head" data-acc-toggle="' + key + '">' +
      '<span class="acc__plus">' + ico('plus') + '</span>' +
      '<span><b>' + E(title) + '</b><span class="dim">' + E(hint) + '</span></span>' +
      '</button><div class="acc__body hide" id="acc-' + key + '"><div class="pad">' + body + '</div></div></section>';
  }

  function openGiveForm(login, char) {
    if (!UI.can(window.DB.CAP.characters)) return UI.toast('Недостаточно прав для выдачи предметов', 'bad');
    var ITEMS = [
      { id: 57, name: 'Adena' }, { id: 6360, name: 'Сундук с сокровищами' }, { id: 9177, name: 'Оружие героя — 7 дней' },
      { id: 1888, name: 'Броня героя — 7 дней' }, { id: 3936, name: 'Благословение (заточка)' },
      { id: 4037, name: 'Свиток возврата' }, { id: 728, name: 'Набор новичка' }, { id: 959, name: 'Руна опыта +50%' },
      { id: 13563, name: 'Агатион — волк' }, { id: 3456, name: 'Кристалл души' }
    ];
    var charNames = S.characters.filter(function (c) { return c.account === (login || ''); }).map(function (c) { return c.name; });
    UI.openModal({
      title: 'Выдать предмет', sub: 'Запись попадёт в очередь выдачи и в журнал',
      body: '<div class="form-row">' +
        '<label class="field"><span class="field__l">Игровой аккаунт</span>' +
        '<input id="gi-login" list="acc-list" value="' + E(login || '') + '" placeholder="например, Twinservers">' +
        '<datalist id="acc-list">' + S.accounts.map(function (a) { return '<option value="' + E(a.login) + '">'; }).join('') + '</datalist></label>' +
        '<label class="field"><span class="field__l">Персонаж</span>' +
        '<input id="gi-char" list="char-list" value="' + E(char || '') + '" placeholder="имя персонажа">' +
        '<datalist id="char-list">' + S.characters.map(function (c) { return '<option value="' + E(c.name) + '">'; }).join('') + '</datalist></label>' +
        '</div>' +
        '<div class="form-row">' +
        '<label class="field"><span class="field__l">Предмет</span><select id="gi-item">' +
        ITEMS.map(function (i) { return '<option value="' + i.id + '">' + E(i.name) + ' (' + i.id + ')</option>'; }).join('') +
        '</select><p class="field__h">Список частых предметов. Для остальных выберите «другой ID».</p></label>' +
        '<label class="field"><span class="field__l">Свой ID предмета</span><input id="gi-custom" type="number" min="1" placeholder="необязательно"></label>' +
        '</div>' +
        '<div class="form-row form-row--3">' +
        '<label class="field"><span class="field__l">Количество</span><input id="gi-count" type="number" min="1" value="1"></label>' +
        '<label class="field"><span class="field__l">Заточка</span><input id="gi-ench" type="number" min="0" max="30" value="0"><p class="field__h">Выше 16 — нужно подтверждение</p></label>' +
        '<label class="field"><span class="field__l">Причина</span><input id="gi-reason" placeholder="компенсация, приз, ивент"></label>' +
        '</div>',
      foot: '<button class="btn btn--primary" id="gi-send">' + ico('plus') + 'Выдать</button>',
      onMount: function (m) {
        var lg = m.querySelector('#gi-login');
        lg.addEventListener('change', function () {
          var cs = S.characters.filter(function (c) { return c.account === lg.value; });
          m.querySelector('#gi-char').value = cs[0] ? cs[0].name : '';
        });
        m.querySelector('#gi-send').addEventListener('click', function () {
          var acc = m.querySelector('#gi-login').value.trim();
          var ch = m.querySelector('#gi-char').value.trim();
          var itemSel = m.querySelector('#gi-item');
          var custom = parseInt(m.querySelector('#gi-custom').value, 10);
          var itemId = custom > 0 ? custom : parseInt(itemSel.value, 10);
          var itemName = custom > 0 ? 'ID ' + custom : itemSel.options[itemSel.selectedIndex].text.replace(/\s*\(\d+\)$/, '');
          var count = Math.max(1, parseInt(m.querySelector('#gi-count').value, 10) || 1);
          var ench = Math.max(0, Math.min(30, parseInt(m.querySelector('#gi-ench').value, 10) || 0));
          var reason = m.querySelector('#gi-reason').value.trim();

          if (!acc) return UI.toast('Укажите игровой аккаунт', 'bad');
          if (!ch) return UI.toast('Укажите имя персонажа', 'bad');
          if (count > 1000000) return UI.toast('Максимум 1 000 000 за операцию', 'bad');

          var push = function () {
            /* Настоящая выдача: предмет уходит в игровую базу сервера.
               После ответа перечитываем состояние — цифры на экране
               должны совпадать с базой, а не с тем, что мы «нарисовали». */
            var btn = m.querySelector('#gi-send');
            btn.disabled = true;
            window.API.giveItem({
              login: acc, char: ch, itemId: itemId, count: count, enchant: ench, reason: reason
            }).then(function () {
              UI.closeModal();
              UI.toast('Выдано: ' + itemName + ' × ' + count + ' → ' + ch, 'ok');
              return window.DB.reload();
            }).then(function () {
              render();
            }).catch(function (e) {
              btn.disabled = false;
              UI.toast(e.message || 'Выдача не прошла', 'bad');
            });
          };
          if (ench > 16) {
            UI.confirmModal({
              title: 'Заточка выше 16', sub: itemName + ' +' + ench,
              text: 'Заточка выше 16 даёт серьёзное преимущество. Подтвердите, что выдача согласована с владельцем проекта.',
              danger: true, okText: 'Да, выдать', onOk: push
            });
          } else push();
        });
      }
    });
  }

  function openBanForm(login) {
    if (!UI.can(window.DB.CAP.characters)) return UI.toast('Недостаточно прав для бана', 'bad');
    var acc = S.accounts.filter(function (a) { return a.login === (login || ''); })[0];
    UI.openModal({
      title: 'Блокировка игрового аккаунта',
      sub: acc && acc.banned ? 'Сейчас забанен до ' + dt(acc.banUntil) : 'Аккаунт активен',
      body: '<label class="field"><span class="field__l">Игровой аккаунт</span>' +
        '<input id="bn-login" list="acc-list2" value="' + E(login || '') + '">' +
        '<datalist id="acc-list2">' + S.accounts.map(function (a) { return '<option value="' + E(a.login) + '">'; }).join('') + '</datalist></label>' +
        '<div class="form-row"><label class="field"><span class="field__l">Срок</span>' +
        '<select id="bn-hours"><option value="1">1 час</option><option value="24" selected>24 часа</option>' +
        '<option value="168">7 дней</option><option value="720">30 дней</option><option value="8760">1 год</option>' +
        '<option value="-1">навсегда</option></select></label>' +
        '<label class="field"><span class="field__l">Причина</span><input id="bn-reason" placeholder="нарушение правил"></label></div>' +
        '<p class="field__h">Персонажи и вещи не удаляются. Игрок увидит причину при попытке входа.</p>',
      foot: (acc && acc.banned ? '<button class="btn" id="bn-unban">Снять бан</button>' : '') +
        '<button class="btn btn--danger" id="bn-send">' + ico('lock') + 'Забанить</button>',
      onMount: function (m) {
        var un = m.querySelector('#bn-unban');
        if (un) un.addEventListener('click', function () {
          window.API.ban({ login: acc.login, mode: 'unban' }).then(function () {
            UI.closeModal();
            UI.toast('Бан снят: ' + acc.login, 'ok');
            return window.DB.reload();
          }).then(render).catch(function (e) { UI.toast(e.message || 'Не удалось снять бан', 'bad'); });
        });
        m.querySelector('#bn-send').addEventListener('click', function () {
          var lg = m.querySelector('#bn-login').value.trim();
          var a = S.accounts.filter(function (x) { return x.login === lg; })[0];
          if (!a) return UI.toast('Такого аккаунта нет в базе сервера', 'bad');
          var h = parseInt(m.querySelector('#bn-hours').value, 10);
          var reason = m.querySelector('#bn-reason').value.trim() || 'нарушение правил';
          /* Бан — это ban_expire в игровом аккаунте: пишем в базу сервера. */
          var hours = h === -1 ? 24 * 365 : h;
          window.API.ban({ login: a.login, mode: 'ban', hours: hours, reason: reason })
            .then(function (res) {
              UI.closeModal();
              UI.toast('Забанен: ' + a.login + ' до ' + dt(res.until), 'warn');
              return window.DB.reload();
            })
            .then(render)
            .catch(function (e) { UI.toast(e.message || 'Бан не прошёл', 'bad'); });
        });
      }
    });
  }

  /* =====================  ПЛАТЕЖИ  ===================== */
  var finFilter = { q: '', status: 'all', sel: null, page: 1, per: 10 };
  function viewFinance() {
    var canEdit = UI.can(window.DB.CAP.financeEdit);
    var list = S.payments.filter(function (p) {
      if (finFilter.status !== 'all' && p.status !== finFilter.status) return false;
      if (finFilter.q && (p.player.toLowerCase().indexOf(finFilter.q.toLowerCase()) < 0 && String(p.id).indexOf(finFilter.q) < 0)) return false;
      return true;
    });
    var pages = Math.max(1, Math.ceil(list.length / finFilter.per));
    if (finFilter.page > pages) finFilter.page = pages;
    var from = (finFilter.page - 1) * finFilter.per;
    var slice = list.slice(from, from + finFilter.per);
    if (!finFilter.sel || !S.payments.some(function (p) { return p.id === finFilter.sel; })) {
      finFilter.sel = slice[0] ? slice[0].id : null;
    }
    var rows = slice.map(function (p) {
      return '<div class="prow' + (p.id === finFilter.sel ? ' on' : '') + '" data-paysel="' + p.id + '">' +
        '<span class="prow__id num">#' + p.id + '</span>' +
        '<span class="prow__mid"><b>' + E(p.player) + '</b> ' + statusTag(p.status) +
        '<div class="dim">' + E(p.gateway) + ' · ' + dshort(p.createdAt) + '</div></span>' +
        '<span class="prow__amt num">' + nf(p.coins) + '<small>' + usd(p.usd) + '</small></span></div>';
    }).join('');
    var detail = finFilter.sel ? payDetail(finFilter.sel, canEdit) : empty('Выберите заявку');

    var counts = {
      all: S.payments.length,
      pending: S.payments.filter(function (p) { return p.status === 'pending'; }).length,
      paid: S.payments.filter(function (p) { return p.status === 'paid'; }).length,
      unpaid: S.payments.filter(function (p) { return p.status === 'unpaid'; }).length,
      canceled: S.payments.filter(function (p) { return p.status === 'canceled'; }).length
    };
    var s = stats();

    return head('LA2PLUS · ФИНАНСЫ', 'Заявки на пополнение',
      'Сегодня <b class="gold">' + usd(s.todaySum) + '</b> по ' + s.todayCount + ' заявкам · за 30 дней ' +
      usd(s.monthSum) + ' · на проверке ' + counts.pending,
      '<button class="btn btn--ghost" data-act="pays-csv">' + ico('dl') + 'CSV</button>' +
      (canEdit ? '<button class="btn btn--primary" data-act="balance-op">' + ico('coin') + 'Ручная операция</button>' : '')) +
      '<div class="fin-split">' +
      card('', '<div class="toolbar">' +
        '<div class="search" style="max-width:200px">' + ico('search') +
        '<input data-ff="q" placeholder="№ или игрок" value="' + E(finFilter.q) + '"></div>' +
        '</div><div class="filters">' +
        ['all', 'pending', 'paid', 'unpaid', 'canceled'].map(function (k) {
          var lbl = { all: 'Все', pending: 'Проверка', paid: 'Оплачены', unpaid: 'Не оплачены', canceled: 'Отменены' }[k];
          return '<button class="fbtn' + (finFilter.status === k ? ' on' : '') + '" data-fstatus="' + k + '">' +
            lbl + ' <span class="dim">' + counts[k] + '</span></button>';
        }).join('') + '</div>' +
        '<div class="pays">' + (rows || empty('Заявок нет')) + '</div>' +
        pager(list.length, finFilter.page, pages, 'fin')) +
      '<div class="fin-detail' + (finFilter.mobileDetail ? ' is-mob' : '') + '">' +
        '<button class="btn btn--ghost fin-back" data-fin-back>' + ico('back') + 'К списку заявок</button>' +
        detail + '</div>' +
      '</div>';
  }
  function payDetail(id, canEdit) {
    var p = S.payments.filter(function (x) { return x.id === id; })[0];
    if (!p) return '';
    var pl = UI.playerById(p.playerId);
    var ops = S.walletOps.filter(function (o) { return o.player === p.player; }).slice(0, 5);
    var conditions = [
      { ok: p.txList.length > 0, text: 'есть транзакция по счёту (tx_list)' },
      { ok: p.txList.length > 0 && p.txList[0].amount + 0.01 >= p.usd, text: 'сумма транзакции покрывает сумму счёта' },
      { ok: !p.testMode, text: 'счёт не в тестовом режиме' },
      { ok: p.status !== 'canceled', text: 'заявка не отменена' }
    ];
    var condHtml = conditions.map(function (c) {
      return '<div class="cond ' + (c.ok ? 'ok' : 'no') + '">' + (c.ok ? ico('check') : ico('x')) + '<span>' + c.text + '</span></div>';
    }).join('');

    return card('', '<div class="who">' +
      '<div class="who__av">' + E(p.player.slice(0, 1).toUpperCase()) + '</div>' +
      '<div style="min-width:0"><p class="who__n">' + E(p.player) + '</p>' +
      '<p class="who__s">Заявка #' + p.id + ' · ' + E(p.gateway) + ' · ' + dt(p.createdAt) + '</p></div>' +
      '<span style="margin-left:auto">' + statusTag(p.status) + '</span></div>' +
      '<div class="kv4">' +
      kv('Сумма заявки', coin(p.coins) + ' · ' + usd(p.usd)) +
      kv('Зачислено', p.credited ? coin(p.credited) : '—') +
      kv('Бонус', '+' + bonusFor(p.coins) + '%') +
      kv('Баланс игрока', pl ? nf(pl.balance, 2) + ' Coin' : '—') +
      kv('Счёт шлюза', p.invoice, true) +
      kv('Режим шлюза', p.testMode ? 'тестовый' : 'боевой') +
      '</div>' +
      '<div class="pad"><p class="field__l">Условия зачисления</p>' + condHtml + '</div>' +
      (canEdit && (p.status === 'pending' || p.status === 'unpaid') ?
        '<div class="pad acts">' +
        '<button class="btn btn--primary" data-payok="' + p.id + '">' + ico('check') + 'Подтвердить и начислить</button>' +
        '<button class="btn btn--danger" data-payno="' + p.id + '">' + ico('x') + 'Отклонить</button>' +
        '</div>' : '') +
      '<div class="pad"><p class="field__l">Операции игрока</p>' +
      (ops.length ? '<div class="mlist">' + ops.map(function (o) {
        return '<div class="mlist__r"><span>' + walletTypeName(o.type) + '<div class="dim">' + dshort(o.at) + ' · ' + E(o.comment || '') + '</div></span>' +
          '<span class="' + (o.amount < 0 ? '' : 'gold') + '">' + (o.amount > 0 ? '+' : '') + nf(o.amount) + '</span></div>';
      }).join('') + '</div>' : '<p class="dim">Операций нет</p>') + '</div>');
  }
  function kv(l, v, mono) {
    return '<div class="kv4__i"><p class="kv4__l">' + E(l) + '</p><p class="kv4__v' + (mono ? ' mono' : '') + '">' + E(v) + '</p></div>';
  }
  function creditPayment(id) {
    var p = S.payments.filter(function (x) { return x.id === id; })[0];
    if (!p) return;
    var bonus = Math.round(p.coins * bonusFor(p.coins) / 100);
    UI.confirmModal({
      title: 'Начислить по заявке #' + p.id,
      sub: p.player + ' · ' + p.gateway,
      text: 'Игроку будет начислено <b>' + coin(p.coins) + '</b> (включая бонус ' + bonusFor(p.coins) + '%). ' +
        'Операция попадёт в журнал и в историю кошелька игрока.',
      okText: 'Начислить ' + coin(p.coins),
      onOk: function () {
        /* Начисление идёт на сервер: статус заявки и кошелёк игрока
           меняются в базе, иначе игрок не получит монеты. */
        window.API.paymentSet({ id: p.id, act: 'credit' }).then(function (r) {
          UI.toast('Заявка #' + p.id + ' оплачена, начислено ' + coin(p.coins) +
            (r && r.balance !== undefined ? ' · баланс ' + nf(r.balance, 2) : ''), 'ok');
          return window.DB.reload();
        }).then(render).catch(function (e) {
          UI.toast(e.message || 'Заявка не начислилась', 'bad');
        });
      }
    });
  }
  function openBalanceOp(login) {
    if (!UI.can(window.DB.CAP.financeEdit)) return UI.toast('Недостаточно прав для операций с балансом', 'bad');
    UI.openModal({
      title: 'Ручная операция по балансу', sub: 'Каждая операция пишется в журнал и видна игроку',
      body: '<label class="field"><span class="field__l">Игрок (логин на сайте)</span>' +
        '<input id="bo-login" list="pl-list" value="' + E(login || '') + '">' +
        '<datalist id="pl-list">' + S.players.map(function (p) { return '<option value="' + E(p.login) + '">'; }).join('') + '</datalist></label>' +
        '<div class="form-row"><label class="field"><span class="field__l">Операция</span>' +
        '<select id="bo-type"><option value="credit">Начислить</option><option value="debit">Списать</option></select></label>' +
        '<label class="field"><span class="field__l">Сумма, Coin</span><input id="bo-amount" type="number" min="0.01" step="0.01" value="100"></label></div>' +
        '<label class="field"><span class="field__l">Комментарий для игрока</span>' +
        '<input id="bo-comment" placeholder="компенсация, бонус, корректировка"></label>' +
        '<div id="bo-preview" class="dim"></div>',
      foot: '<button class="btn btn--primary" id="bo-send">' + ico('coin') + 'Выполнить</button>',
      onMount: function (m) {
        var lg = m.querySelector('#bo-login'), am = m.querySelector('#bo-amount'), tp = m.querySelector('#bo-type');
        function prev() {
          var p = UI.playerByName(lg.value.trim());
          var v = parseFloat(am.value) || 0;
          var sign = tp.value === 'credit' ? 1 : -1;
          m.querySelector('#bo-preview').innerHTML = p
            ? 'Баланс: <b class="gold">' + nf(p.balance, 2) + '</b> → <b class="gold">' + nf(Math.max(0, p.balance + sign * v), 2) + '</b> Coin'
            : 'Игрок не найден';
        }
        [lg, am, tp].forEach(function (el) { el.addEventListener('input', prev); el.addEventListener('change', prev); });
        prev();
        m.querySelector('#bo-send').addEventListener('click', function () {
          var p = UI.playerByName(lg.value.trim());
          if (!p) return UI.toast('Игрок не найден', 'bad');
          var v = parseFloat(am.value) || 0;
          if (v <= 0) return UI.toast('Сумма должна быть больше нуля', 'bad');
          var credit = tp.value === 'credit';
          if (!credit && v > p.balance) return UI.toast('Нельзя списать больше, чем есть на балансе (' + nf(p.balance, 2) + ')', 'bad');
          var comment = m.querySelector('#bo-comment').value.trim() || (credit ? 'начисление' : 'списание');
          var btn = m.querySelector('#bo-send');
          btn.disabled = true;

          /* Операция проходит через WalletService: баланс, журнал кошелька
             и запись в staff_log формирует сервер. */
          window.API.balance({ login: p.login, amount: v, operation: credit ? 'credit' : 'debit', comment: comment })
            .then(function (res) {
              UI.closeModal();
              UI.toast((credit ? 'Начислено ' : 'Списано ') + nf(v) + ' Coin · ' + p.login +
                ' · баланс ' + nf(res.balance, 2), 'ok');
              return window.DB.reload();
            })
            .then(render)
            .catch(function (e) {
              btn.disabled = false;
              UI.toast(e.message || 'Операция не прошла', 'bad');
            });
        });
      }
    });
  }

  /* =====================  ТИКЕТЫ  ===================== */
  var tickFilter = { status: 'open', sel: null, mobileDetail: false };
  function viewTickets() {
    var list = S.tickets.filter(function (t) { return tickFilter.status === 'all' || t.status === tickFilter.status; });
    if (!tickFilter.sel || !S.tickets.some(function (t) { return t.id === tickFilter.sel; })) {
      tickFilter.sel = list[0] ? list[0].id : (S.tickets[0] ? S.tickets[0].id : null);
    }
    var rows = list.map(function (t) {
      return '<div class="prow' + (t.id === tickFilter.sel ? ' on' : '') + '" data-tsel="' + t.id + '"' +
        ' style="cursor:pointer">' +
        '<span class="prow__id num">#' + t.id + '</span>' +
        '<span class="prow__mid"><b>' + E(t.subject) + '</b> ' +
        (t.priority === 'high' ? '<span class="tag tag--bad">важно</span>' : '') + statusTag(t.status) +
        '<div class="dim">' + E(t.player) + ' · ' + ago(t.createdAt) + ' назад · ' + t.messages.length + ' сообщ.</div></span>' +
        '</div>';
    }).join('');

    return head('LA2PLUS · ПОДДЕРЖКА', 'Тикеты игроков',
      S.tickets.filter(function (t) { return t.status === 'open'; }).length + ' открытых · ' +
      S.tickets.filter(function (t) { return t.status === 'closed'; }).length + ' закрытых',
      '<div class="seg" data-seg="t-status">' + segBtn('open', 'Открытые', tickFilter.status) +
      segBtn('closed', 'Закрытые', tickFilter.status) + segBtn('all', 'Все', tickFilter.status) + '</div>') +
      '<div class="fin-split">' +
      card('', '<div class="pays">' + (rows || empty('Тикетов нет', 'В этой категории пусто')) + '</div>') +
      '<div class="fin-detail' + (tickFilter.mobileDetail ? ' is-mob' : '') + '">' +
        '<button class="btn btn--ghost fin-back" data-tick-back>' + ico('back') + 'К списку тикетов</button>' +
        (tickFilter.sel ? ticketDetail(tickFilter.sel) : empty('Выберите тикет')) + '</div>' +
      '</div>';
  }
  function ticketDetail(id) {
    var t = S.tickets.filter(function (x) { return x.id === id; })[0];
    if (!t) return '';
    var canReply = UI.can(window.DB.CAP.ticketsReply), canClose = UI.can(window.DB.CAP.ticketsClose);
    var msgs = t.messages.map(function (m) {
      return '<div class="msg ' + (m.staff ? 'msg--staff' : '') + '">' +
        '<div class="msg__top"><b>' + E(m.by) + '</b>' + (m.staff ? ' <span class="tag tag--info">поддержка</span>' : '') +
        '<span class="dim" style="margin-left:auto">' + dshort(m.at) + '</span></div>' +
        '<p class="msg__t">' + E(m.text) + '</p></div>';
    }).join('');
    var linked = S.payments.filter(function (p) { return p.player === t.player; }).slice(0, 3);
    return card('', '<div class="who"><div class="who__av">' + E(t.player.slice(0, 1).toUpperCase()) + '</div>' +
      '<div style="min-width:0"><p class="who__n">' + E(t.subject) + '</p><p class="who__s">#' + t.id + ' · ' + E(t.player) + ' · создан ' + dt(t.createdAt) +
        (t.status === 'closed' ? ' · закрыт ' + dt(t.updatedAt) : '') + '</p></div>' +
      '<span style="margin-left:auto">' + statusTag(t.status) + '</span></div>' +
      '<div class="pad"><div class="thread">' + msgs + '</div></div>' +
      (linked.length ? '<div class="pad"><p class="field__l">Заявки этого игрока</p><div class="mlist">' +
        linked.map(function (p) {
          return '<div class="mlist__r"><span>#' + p.id + ' · ' + E(p.gateway) + '</span><span>' + coin(p.coins) + ' · ' + statusTag(p.status) + '</span></div>';
        }).join('') + '</div></div>' : '') +
      (t.status === 'open' && canReply ? '<div class="pad">' +
        '<label class="field"><span class="field__l">Ответ игроку</span>' +
        '<textarea id="tk-text" placeholder="Напишите ответ…"></textarea></label>' +
        '<div class="flex gap8 wrap">' +
        ['Здравствуйте! Проверяю вашу заявку, вернусь с ответом в течение 15 минут.',
          'Монеты начислены, проверьте баланс в кабинете.',
          'Пришлите, пожалуйста, скриншот операции — так разберёмся быстрее.'].map(function (s, i) {
            return '<button class="btn btn--sm" data-tpl="' + i + '">Шаблон ' + (i + 1) + '</button>';
          }).join('') + '</div>' +
        '<div class="flex gap8 mt14">' +
        '<button class="btn btn--primary" data-treply="' + t.id + '">' + ico('send') + 'Отправить ответ</button>' +
        (canClose ? '<button class="btn" data-tclose="' + t.id + '">' + ico('check') + 'Ответить и закрыть</button>' : '') +
        '</div></div>' : '') +
      (t.status === 'closed' ? '<div class="pad"><button class="btn" data-topen="' + t.id + '">' + ico('refresh') + 'Переоткрыть тикет</button></div>' : ''),
      { glow: t.status === 'open' });
  }

  /* =====================  СОТРУДНИКИ  ===================== */
  var staffQ = '';
  function isSuper(p) { return window.DB.superAdmins.indexOf(p.id) >= 0; }
  function ownerCount() { return (S.players || []).filter(isSuper).length; }

  /* иерархия: роль можно менять только у тех, кто ниже по рангу.
     Владельцев не трогает никто: они равны по рангу между собой, и себя
     снять тоже нельзя — иначе «Снять» на своей строке оставляло проект
     без доступа. Те же правила проверяет сервер (RoleService). */
  function canEditRole(p) {
    if (!UI.can(window.DB.CAP.roles)) return false;
    if (isSuper(p)) return false;               // владельцев не трогает даже администратор
    if (String(p.login).toLowerCase() === String(S.me.login).toLowerCase()) return false;
    var meRank = UI.roleOf(UI.effectiveRole(S.me.login)).rank || 0;
    var hisRank = UI.roleOf(p.role || 'user').rank || 0;
    return meRank > hisRank;
  }

  function viewStaff() {
    if (!UI.can(window.DB.CAP.staff) && !UI.can(window.DB.CAP.roles)) return noAccess('раздел «Сотрудники»');
    var canManage = UI.can(window.DB.CAP.roles);
    var meRank = UI.roleOf(UI.effectiveRole(S.me.login)).rank || 0;
    var staff = S.players.filter(function (p) { return p.role || isSuper(p); })
      .sort(function (a, b) {
        var ra = isSuper(a) ? 100 : (UI.roleOf(a.role).rank || 0);
        var rb = isSuper(b) ? 100 : (UI.roleOf(b.role).rank || 0);
        return rb - ra || a.login.localeCompare(b.login);
      });

    var rows = staff.map(function (p) {
      var sup = isSuper(p);
      var roleKey = sup ? 'owner' : p.role;
      var r = UI.roleOf(roleKey);
      return '<tr><td><b>' + E(p.login) + '</b>' +
        (sup ? ' <span class="tag tag--info">' + ico('shield') + 'владелец</span>' : '') +
        '<div class="dim">' + E(p.email) + '</div></td>' +
        '<td><span class="tag tag--' + (sup ? 'info' : roleKey === 'admin' ? 'warn' : 'mut') + '">' + E(r.name) + '</span></td>' +
        '<td class="dim">' + (r.can.indexOf('*') >= 0 ? 'все права' : r.can.length + ' прав') +
        '<div class="dim staff-why" title="' + E(r.text || '') + '">' + E(r.text || '') + '</div></td>' +
        '<td class="nowrap">' + (canEditRole(p)
          ? '<button class="btn btn--sm btn--ghost" data-edit-role="' + p.id + '">Изменить</button> ' +
            '<button class="btn btn--sm btn--danger" data-revoke="' + p.id + '">Снять</button>'
          : (sup ? '<span class="dim" title="Владельцы равны по правам: снять владельца из панели нельзя">' + ico('lock') + ' снять нельзя</span>'
            : (canManage ? '<span class="dim">выше по рангу</span>' : ''))) + '</td></tr>';
    }).join('');

    /* Матрица прав строится из серверного ответа: названия прав и наборы
       ролей берутся из RoleService, иначе таблица «кто что может» врёт. */
    var srv = window.DB.ROLES_SERVER || {};
    var titles = srv.titles || {};
    var denied = srv.denied || {};
    var perms = Object.keys(titles);
    var roleKeys = (window.DB.roleOrder || ['owner', 'admin', 'moder', 'gm', 'finance'])
      .filter(function (k) { return !!UI.roleOf(k).key; });

    if (!perms.length) {
      perms = ['panel.view', 'users.view', 'users.edit', 'characters.view', 'game.act',
        'finance.view', 'finance.edit', 'tickets.view', 'tickets.reply', 'roles.manage',
        'owner.manage', 'settings.view', 'analytics.view', 'log.view'];
    }

    var mrows = perms.map(function (perm) {
      return '<tr><td class="dim">' + E(titles[perm] || perm) +
        '<div class="dim mono" style="font-size:10px">' + E(perm) + '</div></td>' +
        roleKeys.map(function (rk) {
          var r = UI.roleOf(rk);
          var yes = (r.can || []).indexOf('*') >= 0 || (r.can || []).indexOf(perm) >= 0;
          var no = (denied[rk] || r.cannot || []).indexOf(perm) >= 0;
          return '<td class="center">' + (yes ? '<span style="color:var(--ok)">✓</span>'
            : (no ? '<span style="color:var(--bad)">✕</span>' : '<span class="mut">—</span>')) + '</td>';
        }).join('') + '</tr>';
    }).join('');

    var roleCards = roleKeys.map(function (rk) {
      var r = UI.roleOf(rk);
      var cnt = (S.players || []).filter(function (p) { return isSuper(p) ? rk === 'owner' : p.role === rk; }).length;
      return '<div class="rolecard' + (rk === 'owner' ? ' rolecard--owner' : '') + '">' +
        '<div class="rolecard__t"><b>' + E(r.name) + '</b><span class="tag tag--mut">' + cnt + '</span></div>' +
        '<p class="rolecard__s">' + E(r.text || '') + '</p>' +
        '<p class="rolecard__p">' + (r.can.indexOf('*') >= 0 ? 'все права' : r.can.length + ' прав') + '</p></div>';
    }).join('');

    return head('LA2PLUS · КОМАНДА', 'Сотрудники и роли',
      'Владельцев проекта <b class="gold">' + ownerCount() + '</b>. Роль хранится отдельно от игрового аккаунта. ' +
      'Владельца нельзя ни выдать, ни снять из ролей: владельцев добавляет только владелец, ' +
      'а снять владельца из панели нельзя вообще — список меняется вручную в params.php.',
      (canManage && UI.can(window.DB.CAP.owners)
        ? '<button class="btn btn--ghost" data-act="owner-add">' + ico('shield') + 'Выдать владельца</button>' : '') +
      (canManage ? '<button class="btn btn--primary" data-act="assign-role">' + ico('shield') + 'Выдать роль</button>'
        : '<span class="tag tag--mut">' + ico('lock') + 'Роли меняет владелец или администратор ниже по рангу</span>')) +
      '<div class="roles-row">' + roleCards + '</div>' +
      '<div class="stack mt14">' +
      card('Команда проекта', '<table class="t"><thead><tr><th>Игрок</th><th>Роль</th><th>Что может</th><th></th></tr></thead>' +
        '<tbody>' + (rows || '<tr><td colspan="4">' + empty('Сотрудников нет') + '</td></tr>') + '</tbody></table>', { flat: true }) +
      card('Кто что может', '<table class="t"><thead><tr><th>Право</th>' +
        roleKeys.map(function (r) { return '<th class="center">' + E(UI.roleOf(r).short) + '</th>'; }).join('') +
        '</tr></thead><tbody>' + mrows + '</tbody></table>', { flat: true }) +
      '</div>';
  }

  /**
   * Выдача владельца проекта.
   *
   * Раньше кнопка «Выдать владельца» просто открывала меню профиля — то есть
   * ничего не делала. Список владельцев хранится в params.php, поэтому здесь
   * обычное окно, которое зовёт /adm/owner с act=grant.
   */
  function openOwnerGrant() {
    if (!UI.can(window.DB.CAP.owners)) {
      return UI.toast('Владельца выдаёт только владелец проекта', 'bad');
    }

    var owners = window.DB.superAdmins || [];
    var candidates = S.players.filter(function (p) { return owners.indexOf(p.id) < 0; });

    if (!candidates.length) {
      return UI.toast('Все игроки уже владельцы', 'info');
    }

    UI.openModal({
      title: 'Выдать владельца', sub: 'Владелец видит всю панель и может выдавать роли',
      body: '<label class="field"><span class="field__l">Игрок</span>' +
        '<input id="ow-login" list="ow-list" placeholder="начните вводить логин">' +
        '<datalist id="ow-list">' + candidates.map(function (p) {
          return '<option value="' + E(p.login) + '">';
        }).join('') + '</datalist></label>' +
        '<input type="hidden" id="ow-id">' +
        '<label class="field"><span class="field__l">Комментарий</span>' +
        '<input id="ow-comment" placeholder="зачем выдаётся владелец"></label>' +
        '<p class="field__h">Сейчас владельцев: ' + owners.length + '. Снять владельца можно в меню профиля.</p>',
      foot: '<button class="btn btn--ghost" data-close>Отмена</button>' +
        '<button class="btn btn--primary" id="ow-send">' + ico('shield') + 'Выдать владельца</button>',
      onMount: function (m) {
        var input = m.querySelector('#ow-login');
        input.addEventListener('input', function () {
          var p = UI.playerByName(input.value.trim());
          m.querySelector('#ow-id').value = p ? p.id : '';
        });
        input.focus();

        m.querySelector('#ow-send').addEventListener('click', function () {
          var p = UI.playerByName(input.value.trim());
          if (!p) return UI.toast('Игрок не найден', 'bad');
          if (owners.indexOf(p.id) >= 0) return UI.toast('Этот игрок уже владелец', 'bad');

          var btn = this;
          btn.disabled = true;

          window.API.owner({ id: p.id, act: 'grant', comment: m.querySelector('#ow-comment').value.trim() })
            .then(function () {
              UI.closeModal();
              UI.toast('Владелец выдан: ' + p.login, 'ok');
              return window.DB.reload();
            })
            .then(render)
            .catch(function (e) {
              btn.disabled = false;
              UI.toast(e.message || 'Не удалось выдать владельца', 'bad');
            });
        });
      }
    });
  }

  function openRoleAssign(userId) {
    if (!UI.can(window.DB.CAP.roles)) return UI.toast('Роли выдаёт владелец или администратор с правом roles.manage', 'bad');
    UI.openModal({
      title: 'Выдать роль', sub: 'Роль даёт доступ к разделам панели согласно матрице прав',
      body: '<label class="field"><span class="field__l">Игрок</span>' +
        '<input id="rl-login" list="pl-list2" value="' + E(userId ? (UI.playerById(userId) || {}).login : '') + '">' +
        '<datalist id="pl-list2">' + S.players.map(function (p) { return '<option value="' + E(p.login) + '">'; }).join('') + '</datalist></label>' +
        '<label class="field"><span class="field__l">Роль</span><select id="rl-role">' +
        (window.DB.roleOrder || ['admin', 'moder', 'gm', 'finance']).filter(function (k) { return k !== 'owner'; }).map(function (k) {
          var cur = window.DB.roleAlias((userId && (UI.playerById(userId) || {}).role) || '');
          return '<option value="' + k + '"' + (cur === k || (!cur && k === 'admin') ? ' selected' : '') + '>' + UI.roleName(k) + '</option>';
        }).join('') + '</select>' +
        '<p class="field__h">Владельца проекта здесь выдать нельзя — только через «Профиль → Владельцы проекта».</p></label>' +
        '<label class="field"><span class="field__l">Комментарий</span><input id="rl-comment" placeholder="почему выдаётся роль"></label>' +
        '<p class="field__h">Снятие роли — там же: выберите «Игрок».</p>',
      foot: '<button class="btn btn--primary" id="rl-send">' + ico('shield') + 'Применить</button>',
      onMount: function (m) {
        m.querySelector('#rl-send').addEventListener('click', function () {
          var p = UI.playerByName(m.querySelector('#rl-login').value.trim());
          if (!p) return UI.toast('Игрок не найден', 'bad');
          if (window.DB.superAdmins.indexOf(p.id) >= 0) return UI.toast('Владелец проекта не меняется через роли — только через список владельцев', 'bad');
          if (UI.roleOf(p.role || 'user').rank >= UI.roleOf(UI.effectiveRole(S.me.login)).rank) {
            return UI.toast('Нельзя менять роль того, кто равен вам или выше по рангу', 'bad');
          }
          var role = m.querySelector('#rl-role').value;
          var comment = m.querySelector('#rl-comment').value.trim();
          var btn = m.querySelector('#rl-send');
          btn.disabled = true;

          /* Роль хранится в таблице roles и проверяется сервером:
             нельзя выдать роль выше своей и нельзя тронуть владельца. */
          window.API.roleSet({ id: p.id, role: role, comment: comment })
            .then(function () {
              UI.resetRightsCache();
              UI.closeModal();
              UI.toast((role === 'user' || role === 'player' ? 'Роль снята: ' : 'Выдана роль «' + UI.roleName(role) + '»: ') + p.login, 'ok');
              return window.DB.reload();
            })
            .then(render)
            .catch(function (e) {
              btn.disabled = false;
              UI.toast(e.message || 'Роль не сохранена', 'bad');
            });
        });
      }
    });
  }

  /* =====================  ЖУРНАЛ  ===================== */
  var logFilter = { q: '', actor: 'all', action: 'all', page: 1, per: 20 };
  function viewLog() {
    var actors  = ['all'].concat(S.log.map(function (l) { return l.actor;  }).filter(function (v, i, a) { return a.indexOf(v) === i; }));
    var actions = ['all'].concat(S.log.map(function (l) { return l.action; }).filter(function (v, i, a) { return a.indexOf(v) === i; }).sort());
    var list = S.log.filter(function (l) {
      if (logFilter.actor  !== 'all' && l.actor  !== logFilter.actor)  return false;
      if (logFilter.action !== 'all' && l.action !== logFilter.action) return false;
      if (logFilter.q) {
        var q = logFilter.q.toLowerCase();
        if ((l.action + ' ' + l.target + ' ' + l.details).toLowerCase().indexOf(q) < 0) return false;
      }
      return true;
    });
    var pages = Math.max(1, Math.ceil(list.length / logFilter.per));
    if (logFilter.page > pages) logFilter.page = pages;
    var from = (logFilter.page - 1) * logFilter.per;
    var rows = list.slice(from, from + logFilter.per).map(function (l) {
      return '<tr><td class="dim nowrap">' + dt(l.at) + '</td><td><b>' + E(l.actor) + '</b></td>' +
        '<td>' + E(l.action) + '</td><td>' + E(l.target || '—') + '<div class="dim">' + E(l.details || '') + '</div></td></tr>';
    }).join('');

    return head('LA2PLUS · ЖУРНАЛ', 'История действий',
      'Всего ' + S.log.length + ' записей. Журнал нужен для разбора спорных случаев: кто, когда, что и с кем сделал.',
      '<button class="btn btn--ghost" data-act="log-csv">' + ico('dl') + 'CSV</button>' +
      '<button class="btn btn--ghost" data-act="refresh">' + ico('refresh') + 'Обновить из базы</button>') +
      card('', '<div class="toolbar">' +
        '<div class="search" style="max-width:260px">' + ico('search') +
        '<input data-lf="q" placeholder="Действие, объект, детали" value="' + E(logFilter.q) + '"></div>' +
        '<label class="field" style="margin:0;max-width:220px"><select data-lf="actor">' +
        actors.map(function (a) {
          return '<option value="' + E(a) + '"' + (logFilter.actor === a ? ' selected' : '') + '>' +
            (a === 'all' ? 'Все сотрудники' : E(a)) + '</option>';
        }).join('') + '</select></label>' +
        '<label class="field" style="margin:0;max-width:200px"><select data-lf="action">' +
        actions.map(function (a) {
          return '<option value="' + E(a) + '"' + (logFilter.action === a ? ' selected' : '') + '>' +
            (a === 'all' ? 'Все действия' : E(a)) + '</option>';
        }).join('') + '</select></label>' +
        '<span class="dim" style="margin-left:auto">' + list.length + ' записей</span></div>' +
        '<table class="t"><thead><tr><th>Время</th><th>Кто</th><th>Действие</th><th>Объект и детали</th></tr></thead>' +
        '<tbody>' + (rows || '<tr><td colspan="4">' + empty('Записей нет') + '</td></tr>') + '</tbody></table>' +
        pager(list.length, logFilter.page, pages, 'log'));
  }

  /* =====================  АНАЛИТИКА  ===================== */
  var anaPeriod = 7;
  /* =====================  АНАЛИТИКА  ===================== */
  /* Блок реальных данных: показывает, откуда берутся цифры, живой трекинг вкладки
     и сверку с тем, что лежит в базе (DB.dbSnapshot). В боевой панели этим занимается
     AnalyticsService (таблица visit_log) — здесь он воспроизведён на клиенте. */
  function liveSource() {
    var a = S.analytics;
    return (a && a.live) ? a.live : { total: 0, sessions: 0, pages: {}, since: Date.now() };
  }
  function trackPage(page) {
    var a = S.analytics;
    if (!a) return;
    a.live = a.live || { total: 0, sessions: 0, pages: {}, since: Date.now(), last: null };
    a.live.total++;
    a.live.last = page;
    a.live.pages[page] = (a.live.pages[page] || 0) + 1;
    var known = false;
    (a.visits || []).forEach(function (v) { if (v.at > Date.now() - 240000) known = true; });
    if (!known) a.live.sessions++;
    window.DB.save();
  }
  function resetLive() {
    S.analytics.live = { total: 0, sessions: 0, pages: {}, since: Date.now(), last: null };
    window.DB.save();
  }

  function viewAnalytics() {
    if (!UI.can(window.DB.CAP.analytics)) return noAccess('analytics.view');
    var any = S.analytics;
    var daily = any.daily.slice(-anaPeriod);
    var maxV = Math.max.apply(null, daily.map(function (d) { return d.visitors; }));
    var maxH = Math.max.apply(null, any.hourly.map(function (h) { return h.visitors; }));
    var sum = daily.reduce(function (a, d) { return a + d.visitors; }, 0);
    var prev = any.daily.slice(-anaPeriod * 2, -anaPeriod).reduce(function (a, d) { return a + d.visitors; }, 0);
    var delta = prev ? Math.round((sum - prev) * 100 / prev) : 0;
    /* Онлайн — цифра из базы (AnalyticsService::getOnlineCount), а не число
       строк в списке визитов: раньше KPI всегда показывал 0. */
    var online = any.online || 0;
    var live = liveSource();
    var snap = S.dbSnapshot || {};
    var s = window.APP.stats ? window.APP.stats() : {};

    var bars = daily.map(function (d) {
      return '<div class="bar-col"><span class="bar-col__v">' + d.visitors + '</span>' +
        '<i style="height:' + Math.round(d.visitors / maxV * 100) + '%"></i>' +
        '<span class="bar-col__l">' + d.date.slice(8) + '.' + d.date.slice(5, 7) + '</span></div>';
    }).join('');

    var hbars = any.hourly.map(function (h) {
      return '<div class="hbar" title="' + h.hour + ':00 — ' + h.visitors + ' посетителей">' +
        '<span class="hbar__l">' + h.hour + '</span>' +
        '<div class="hbar__t"><i style="width:' + Math.round(h.visitors / maxH * 100) + '%"></i></div>' +
        '<span class="hbar__v num">' + h.visitors + '</span></div>';
    }).join('');

    var maxP = Math.max.apply(null, any.topPages.map(function (p) { return p.views; }));
    var pages = any.topPages.map(function (p) {
      return '<div class="hbar" title="' + p.views + ' просмотров"><span class="hbar__l mono" style="width:150px;overflow:hidden;text-overflow:ellipsis">' +
        E(p.page) + '</span><div class="hbar__t"><i style="width:' + Math.round(p.views / maxP * 100) + '%"></i></div>' +
        '<span class="hbar__v num">' + p.views + '</span></div>';
    }).join('');

    var srcTotal = any.sources.reduce(function (a, x) { return a + x.visits; }, 0);
    var srcs = any.sources.map(function (x) {
      var pct = Math.round(x.visits / srcTotal * 100);
      return '<div class="hbar"><span class="hbar__l" style="width:110px">' + E(x.title || x.source) + '</span>' +
        '<div class="hbar__t"><i style="width:' + pct + '%"></i></div>' +
        '<span class="hbar__v num">' + pct + '%</span></div>';
    }).join('');

    var devs = any.devices.map(function (d) {
      return '<div class="hbar"><span class="hbar__l" style="width:80px">' + E(d.title || d.device) + '</span>' +
        '<div class="hbar__t"><i style="width:' + d.share + '%"></i></div>' +
        '<span class="hbar__v num">' + d.share + '%</span></div>';
    }).join('');

    /* сверка «витрина ↔ база»: сравниваем цифры экранов с тем, что лежит в данных */
    var checks = [
      { k: 'Игроки сайта', view: s.players, db: snap.users, table: 'users' },
      { k: 'Игровые аккаунты', view: (S.accounts || []).length, db: snap.accounts, table: 'gs.accounts' },
      { k: 'Персонажи', view: s.characters, db: snap.characters, table: 'gs.characters' },
      { k: 'Онлайн в игре', view: s.online, db: snap.charactersOnline, table: 'gs.characters.online' },
      { k: 'Баланс на кошельках', view: s.balance, db: snap.balance, table: 'user_profiles.balance', fmt: 'coin' },
      { k: 'Заявки на пополнение', view: (S.payments || []).length, db: snap.payments, table: 'transactions' },
      { k: 'Тикеты открыты', view: (S.tickets || []).filter(function (t) { return t.status === 'open'; }).length, db: snap.ticketsOpen, table: 'tickets' },
      { k: 'Кланы', view: (S.clans || []).length, db: snap.clans, table: 'gs.clans' }
    ];

    /* Монеты, оставшиеся на профилях удалённых аккаунтов: они не входят в
       «баланс игроков», и это надо называть вслух, а не прятать. */
    var orphans = (snap.walletOrphans || 0) > 0
      ? '<p class="dbsrc__note">Отдельно: ' + nf(snap.balanceOrphan, 2) + ' Coin лежат на ' +
        snap.walletOrphans + ' кошельк' + (snap.walletOrphans === 1 ? 'е' : 'ах') +
        ' удалённых аккаунтов (профиль есть, пользователя в users нет).</p>'
      : '';
    var checkRows = checks.map(function (c) {
      /* Баланс дробный: 51.14 против 51.14 — сравниваем с точностью до копейки,
         иначе строгое === даёт ложное «≠». */
      var same = typeof c.view === 'number' && !Number.isInteger(c.view)
        ? Math.abs(c.view - (c.db || 0)) < 0.005
        : c.view === c.db;
      return '<div class="dbsrc__r"><span>' + E(c.k) + '<i class="mono">' + E(c.table) + '</i></span>' +
        '<b class="num">' + (c.fmt === 'coin' ? nf(c.view) : c.view) + '</b>' +
        '<span class="dbsrc__ok ' + (same ? 'is-ok' : 'is-bad') + '">' + (same ? '✓ сходится' : '≠ ' + (c.fmt === 'coin' ? nf(c.db) : c.db)) + '</span></div>';
    }).join('');

    var livePages = Object.keys(live.pages || {}).sort(function (a, b) { return live.pages[b] - live.pages[a]; }).slice(0, 5);
    var liveHtml = livePages.length ? livePages.map(function (pg, i) {
      return '<div class="dbsrc__r"><span>' + E(pg) + '<i class="mono">ваши переходы</i></span>' +
        '<b class="num">' + live.pages[pg] + '</b>' +
        '<span class="dbsrc__ok is-live2">' + (i === 0 ? 'чаще всего' : '') + '</span></div>';
    }).join('') : '<p class="dim" style="margin:0">Пока ни одного перехода в этой сессии. Откройте разделы — счётчик обновится.</p>';

    var visits = any.visits.slice(0, 14).map(function (v) {
      return '<tr><td class="dim nowrap">' + dshort(v.at) + '</td><td>' + E(v.user) + '</td>' +
        '<td class="dim mono">' + E(v.page) + '</td><td class="dim">' + E(v.source) + '</td>' +
        '<td class="dim">' + E(v.device) + '</td><td class="mono dim">' + E(v.ip) + '</td></tr>';
    }).join('');

    return head('LA2PLUS · АНАЛИТИКА', 'Посещаемость',
      'Источник — база проекта: <span class="mono">visit_log</span> (визиты), <span class="mono">users</span>, ' +
      '<span class="mono">transactions</span> и база игры <span class="mono">gs</span>. Снимок данных: ' +
      dt(snap.builtAt || Date.now()) + '.',
      '<div class="seg" data-seg="ana">' + [7, 14, 30].map(function (p) {
        return '<button data-val="' + p + '" class="' + (anaPeriod === p ? 'on' : '') + '">' + p + ' дней</button>';
      }).join('') + '</div>' +
      '<button class="btn btn--ghost" data-act="ana-csv">' + ico('dl') + 'CSV</button>') +
      '<div class="kpi-row">' +
      kpi('Сейчас на сайте', '<span id="ana-live">' + online + '</span>', 'обновлено только что', null, 'analytics') +
      kpi('Посетителей за период', nf(sum),
        'за ' + anaPeriod + ' дней · ' + (prev
          ? ((delta >= 0 ? '▲ +' : '▼ ') + delta + '% к прошлому')
          : 'сравнивать не с чем: раньше визитов не было'), null, 'analytics') +
      kpi('Регистраций', nf(daily.reduce(function (a, d) { return a + d.registered; }, 0)), 'за ' + anaPeriod + ' дней', null, 'analytics') +
      kpi('В среднем в день', nf(Math.round(sum / daily.length)), 'уникальных', null, 'analytics') +
      kpi('Пик дня', nf(maxV), 'максимум посетителей', null, 'analytics') +
      '</div>' +

      /* ---- источник данных и сверка с базой ---- */
      '<div class="grid-2 mt14">' +
      card('Источник данных',
        '<div class="dbsrc">' +
          '<div class="dbsrc__top"><span class="dbsrc__dot"></span><div><b>Подключено</b>' +
            '<p>' + E(snap.source || 'MySQL · gs + cms') + '</p></div>' +
            '<span class="tag tag--ok">' + ico('check') + 'live</span></div>' +
          '<div class="dbsrc__r"><span>Таблица визитов<i class="mono">visit_log</i></span>' +
            '<b class="num">' + nf(snap.visitsTracked || 0) + '</b><span class="dbsrc__ok is-ok">пишется</span></div>' +
          '<div class="dbsrc__r"><span>Визитов в списке<i class="mono">последние 60</i></span>' +
            '<b class="num">' + (any.visits || []).length + '</b>' +
            '<span class="dbsrc__ok is-ok">боевые</span></div>' +
          '<div class="dbsrc__r"><span>Снимок собран<i class="mono">' + dt(snap.builtAt || Date.now()) + '</i></span>' +
            '<b class="num">' + ago(snap.builtAt || Date.now()) + '</b><span class="dbsrc__ok">назад</span></div>' +
          '<div class="dbsrc__btns">' +
            '<button class="btn btn--sm btn--primary" data-act="db-sync">' + ico('refresh') + 'Синхронизировать с базой</button>' +
            '<button class="btn btn--sm btn--ghost" data-act="db-schema">Схема запросов</button>' +
          '</div>' +
        '</div>') +
      card('Сверка экранов с базой',
        '<div class="dbsrc">' + checkRows +
        '<p class="dbsrc__note">Каждая строка — цифра с экрана панели и значение из запроса к базе. ' +
        'Расхождение видно сразу: значит данные на экране устарели.</p>' + orphans + '</div>') +
      '</div>' +

      '<div class="grid-2 mt14">' +
      card('Ваши переходы в этой сессии',
        '<div class="dbsrc">' +
          '<div class="dbsrc__r"><span>Просмотров страниц<i class="mono">клиентский трекер</i></span>' +
            '<b class="num">' + (live.total || 0) + '</b><span class="dbsrc__ok is-live2">live</span></div>' +
          '<div class="dbsrc__r"><span>Сессий<i class="mono">с ' + dshort(live.since || Date.now()) + '</i></span>' +
            '<b class="num">' + (live.sessions || 0) + '</b><span class="dbsrc__ok is-live2">live</span></div>' +
          liveHtml +
          '<div class="dbsrc__btns"><button class="btn btn--sm btn--ghost" data-act="live-reset">' +
            ico('refresh') + 'Сбросить счётчик сессии</button></div>' +
        '</div>') +
      card('Посетители по дням', '<div class="bars">' + bars + '</div>') +
      '</div>' +

      '<div class="grid-2 mt14">' +
      card('Активность по часам', '<div class="hbars">' + hbars + '</div>', { meta: '<span class="dim">пик в 20–22</span>' }) +
      card('Что смотрят', '<div class="hbars">' + pages + '</div>') +
      '</div>' +
      '<div class="grid-2 mt14">' +
      card('Откуда приходят / с чего', '<div class="hbars">' + srcs + '<div style="height:10px"></div>' + devs + '</div>') +
      card('Последние визиты', '<table class="t"><thead><tr><th>Время</th>' +
        '<th>Кто</th><th>Страница</th><th>Источник</th><th>Устройство</th><th>IP</th></tr></thead><tbody>' + visits +
        '</tbody></table>', { flat: true }) +
      '</div>';
  }

  function openDbSchema() {
    var sql = [
      '-- 1. Сводка за период (KPI)',
      "SELECT COUNT(DISTINCT visit_key) AS visitors,",
      "       COUNT(*) AS views",
      "FROM ghtweb_visit_log",
      "WHERE created_at BETWEEN :from AND :to AND is_bot = 0;",
      '',
      '-- 2. Посетители по дням',
      "SELECT DATE(created_at) AS day,",
      "       COUNT(DISTINCT visit_key) AS visitors,",
      "       SUM(is_new_visit) AS new_visits",
      "FROM ghtweb_visit_log",
      "WHERE created_at BETWEEN :from AND :to AND is_bot = 0",
      "GROUP BY DATE(created_at) ORDER BY day;",
      '',
      '-- 3. Активность по часам',
      "SELECT HOUR(created_at) AS hr, COUNT(*) AS views",
      "FROM ghtweb_visit_log",
      "WHERE created_at >= NOW() - INTERVAL 24 HOUR",
      "GROUP BY HOUR(created_at) ORDER BY hr;",
      '',
      '-- 4. Источники и устройства',
      "SELECT source, device, COUNT(*) AS hits",
      "FROM ghtweb_visit_log",
      "WHERE created_at BETWEEN :from AND :to",
      "GROUP BY source, device ORDER BY hits DESC;",
      '',
      '-- 5. Игровые данные — отдельная база gs',
      "SELECT COUNT(*) FROM characters;",
      "SELECT COUNT(*) FROM characters WHERE online = 1;",
      "SELECT COUNT(*) FROM clans;",
      '',
      '-- 6. Деньги и заявки — cms',
      "SELECT SUM(count) FROM ghtweb_transactions WHERE status = 'success';",
      "SELECT COUNT(*) FROM ghtweb_transactions WHERE status = 'pending';"
    ].join('\n');
    UI.openModal({
      title: 'Откуда цифры', sub: 'SQL, которым собирается аналитика',
      body: '<p class="field__h" style="margin:0 0 10px">Это те же запросы, что выполняет ' +
        '<span class="mono">AnalyticsService</span> в боевой панели: визиты берутся из ' +
        '<span class="mono">visit_log</span>, игровые данные — из базы <span class="mono">gs</span>, ' +
        'деньги — из <span class="mono">transactions</span>.</p>' +
        '<pre class="sqlbox">' + E(sql) + '</pre>',
      foot: '<button class="btn btn--primary" data-close>Понятно</button>'
    });
  }

  /* Экран отказа. Право печатается человеческими словами, без служебных
     имён вида gm.give_item: сотруднику они ничего не говорят. */
  var ACCESS_TEXT = {
    'panel.view': 'вход в панель',
    'analytics.view': 'аналитика',
    'tickets.view': 'тикеты', 'tickets.reply': 'ответы в тикетах', 'tickets.close': 'закрытие тикетов',
    'users.view': 'список игроков', 'users.edit': 'правка игроков',
    'characters.view': 'персонажи', 'server.view': 'данные сервера', 'links.view': 'ссылки',
    'gm.give_item': 'выдача предметов', 'gm.ban_game': 'блокировка аккаунтов',
    'finance.view': 'платежи', 'finance.edit': 'правка платежей',
    'log.view': 'журнал действий', 'settings.view': 'настройки',
    'roles.manage': 'сотрудники и роли', 'owner.manage': 'владельцы проекта'
  };
  function noAccess(perm) {
    var human = ACCESS_TEXT[perm] || 'этот раздел';
    return head('LA2PLUS · ДОСТУП', 'Раздел закрыт',
      'Вашей роли <b>' + UI.roleName(S.me.role) + '</b> недоступен раздел «' + human + '». ' +
      'Если он нужен по работе — попросите владельца проекта добавить право. ' +
      'Посмотреть, что открыто другим ролям, можно в предпросмотре прав: меню профиля справа сверху.') +
      '<div class="card" style="padding:40px;text-align:center">' + ico('lock') +
      '<p style="margin:12px 0 0;color:var(--mut)">Пункт меню для этого раздела у вашей роли скрыт, ' +
      'поэтому вы сюда случайно не попадёте. Эта страница — на случай прямой ссылки.</p></div>';
  }

  /* =====================  НАСТРОЙКИ  ===================== */
  function viewSettings() {
    var st = S.settings;
    var gw = st.gateways.map(function (g) {
      var on = st.gateways.filter(function (x) { return x.allow; }).length;
      return '<div class="gw">' +
        '<div class="gw__top"><div><p class="gw__n">' + E(g.name) + '</p>' +
        '<p class="gw__s">' + E(g.currency) + ' · курс ' + g.rate + ' · минимум ' + g.min + ' ' + E(g.currency) + '</p></div>' +
        '<label class="switch"><input type="checkbox" data-gw="' + g.key + '"' + (g.allow ? ' checked' : '') + '>' +
        '<span class="switch__track"></span><span class="switch__label">' + (g.allow ? 'включён' : 'выключен') + '</span></label></div>' +
        (g.note ? '<p class="gw__note">' + E(g.note) + '</p>' : '') +
        '</div>';
    }).join('');
    var active = st.gateways.filter(function (g) { return g.allow; }).length;

    var bonuses = st.bonuses.map(function (b, i) {
      return '<tr><td><input class="inp" type="number" value="' + b.from + '" data-bonus="' + i + '" data-f="from" style="max-width:110px"></td>' +
        '<td>' + (b.to === null ? '<span class="dim">без предела</span>' :
          '<input class="inp" type="number" value="' + b.to + '" data-bonus="' + i + '" data-f="to" style="max-width:110px">') + '</td>' +
        '<td><input class="inp" type="number" value="' + b.percent + '" data-bonus="' + i + '" data-f="percent" style="max-width:90px"> %</td>' +
        '<td class="dim">' + (b.to === null ? 'от ' + b.from + ' Coin' : b.from + '–' + b.to + ' Coin') + ' → +' + b.percent + '%</td></tr>';
    }).join('');

    return head('LA2PLUS · НАСТРОЙКИ', 'Настройки проекта',
      'Приём платежей: включено <b class="gold">' + active + '</b> из ' + st.gateways.length + ' шлюзов. ' +
      'Изменения применяются сразу и пишутся в журнал.',
      '<button class="btn btn--ghost" data-act="settings-csv">' + ico('dl') + 'Выгрузить конфиг</button>' + '<button class="btn btn--gold" data-act="system-diagnostic">' + ico('shield') + 'Самодиагностика</button>') +
      '<div class="grid-2">' +
      card('Платёжные шлюзы', '<div class="pads">' + gw + '</div>', { glow: true }) +
      card('Курс, минимум и акция', '<div class="pad">' +
        '<div class="form-row">' +
        '<label class="field"><span class="field__l">Курс: монет за 1 USD</span>' +
        '<input id="st-rate" type="number" step="0.01" min="0.01" value="' + st.rate + '"></label>' +
        '<label class="field"><span class="field__l">Минимум пополнения, USD</span>' +
        '<input id="st-min" type="number" min="1" value="' + st.minDeposit + '"></label></div>' +
        '<label class="field"><span class="field__l">Объявление «первые сутки без доната» до</span>' +
        '<input id="st-free" type="datetime-local" value="' + E(st.freeUntil) + '">' +
        '<p class="field__h" id="st-free-hint"></p></label>' +
        '<div class="flex gap8 wrap"><button class="btn btn--primary" data-act="save-settings">' + ico('check') + 'Сохранить</button>' +
        '<button class="btn btn--ghost" data-act="refresh">Обновить из базы</button></div>' +
        '</div>') +
      '</div>' +
      '<div class="grid-2 mt14">' +
      card('Бонусы за пополнение', '<table class="t"><thead><tr><th>От, Coin</th><th>До, Coin</th><th>Бонус</th><th>Итого</th></tr></thead>' +
        '<tbody>' + bonuses + '</tbody></table><div class="pad"><button class="btn btn--sm" data-act="save-settings">Сохранить бонусы</button></div>') +
      card('Вид панели', '<div class="pad"><p class="field__l">Тема оформления</p>' +
        '<div class="theme-cards">' +
        '<button class="theme-card' + (S.theme === 'aurum' ? ' on' : '') + '" data-theme-pick="aurum">' +
        '<span class="theme-card__sw" style="background:linear-gradient(140deg,#F0D39B,#8a6a2f)"></span>' +
        '<b>Aurum</b><span class="dim">золото и тёплое свечение — как бренд сайта</span></button>' +
        '<button class="theme-card' + (S.theme === 'ember' ? ' on' : '') + '" data-theme-pick="ember">' +
        '<span class="theme-card__sw" style="background:linear-gradient(140deg,#FFC98A,#C2412A)"></span>' +
        '<b>Ember</b><span class="dim">кузница: багровое пульсирующее свечение</span></button>' +
        '<button class="theme-card' + (S.theme === 'frost' ? ' on' : '') + '" data-theme-pick="frost">' +
        '<span class="theme-card__sw" style="background:linear-gradient(140deg,#C4ECFF,#3C7FA8)"></span>' +
        '<b>Frost</b><span class="dim">серебро и лёд, строгая финансовая подача</span></button>' +
        '</div>' +
        '<p class="field__h">Тема меняет палитру, характер свечения от логотипа и скорость переливания.</p></div>') +
      '</div>';
  }

  /* =====================  РОУТЕР  ===================== */
  var ROUTES = {
    home: { title: 'Пульт', perm: 'panel.view', path: '/admin', render: viewHome },
    players: { title: 'Игроки', perm: 'users.view', path: '/admin/players', render: viewPlayers },
    accounts: { title: 'Аккаунты сервера', perm: 'server.view', path: '/admin/accounts', render: viewAccounts },
    characters: { title: 'Персонажи', perm: 'server.view', path: '/admin/characters', render: viewCharacters },
    clans: { title: 'Кланы', perm: 'server.view', path: '/admin/clans', render: viewClans },
    /* Раздел — про действия в игре, значит и право игровое: characters.view
       есть у бухгалтера, и он видел кнопки, которые сервер всё равно отклонит. */
    game: { title: 'Игровые действия', perm: 'gm.give_item', path: '/admin/game', render: viewGame },
    finance: { title: 'Платежи', perm: 'finance.view', path: '/admin/finance', render: viewFinance },
    tickets: { title: 'Тикеты', perm: 'tickets.view', path: '/admin/tickets', render: viewTickets },
    staff: { title: 'Сотрудники', perm: 'roles.manage', path: '/admin/staff', render: viewStaff },
    log: { title: 'Журнал', perm: 'log.view', path: '/admin/log', render: viewLog },
    analytics: { title: 'Аналитика', perm: 'analytics.view', path: '/admin/analytics', render: viewAnalytics },
    settings: { title: 'Настройки', perm: 'settings.view', path: '/admin/settings', render: viewSettings }
  };
  var current = 'home';

  /* Раздел берём из адреса: /admin/players → players. Так работают закладки
     и ссылки из старых писем; неизвестный хвост даёт «Пульт». */
  function routeFromPath() {
    var tail = (window.location.pathname || '').replace(/^\/+|\/+$/g, '').split('/');
    if (tail[0] !== 'admin' || !tail[1]) return 'home';
    var alias = { v4: 'home', players: 'players', accounts: 'accounts', characters: 'characters',
                  clans: 'clans', game: 'game', finance: 'finance', payments: 'finance',
                  tickets: 'tickets', staff: 'staff', log: 'log', analytics: 'analytics',
                  settings: 'settings', owners: 'staff' };
    var name = alias[tail[1]] || tail[1];
    return ROUTES[name] ? name : 'home';
  }
  current = routeFromPath();
  window.__routeFromPath = routeFromPath;

  function go(name) {
    if (!ROUTES[name]) name = 'home';
    /* При входе в раздел «Тикеты» на телефоне начинаем со списка;
       повторный render() того же раздела карточку не закрывает. */
    if (name === 'tickets' && current !== 'tickets') tickFilter.mobileDetail = false;
    current = name;
    var r = ROUTES[name];
    var host = document.getElementById('view');
    if (!UI.can(r.perm) && name !== 'home') {
      host.innerHTML = noAccess(r.perm);
    } else {
      host.innerHTML = r.render();
    }
    document.querySelectorAll('.side__item[data-go]').forEach(function (a) {
      a.classList.toggle('is-active', a.getAttribute('data-go') === name);
    });
    trackNav(name);
    if (window.APP && window.APP.refreshChrome) window.APP.refreshChrome();
    var crumb = document.getElementById('crumb');
    if (crumb) crumb.innerHTML = '<b>' + r.title + '</b>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    bindView();
  }
  function render() { go(current); }

  /* трекер переходов: пишет каждое открытие раздела в клиентский счётчик аналитики */
  function trackNav(name) {
    if (!S.analytics) return;
    S.analytics.live = S.analytics.live || { total: 0, sessions: 0, pages: {}, since: Date.now() };
    var L = S.analytics.live;
    L.total++;
    var path = (ROUTES[name] && ROUTES[name].path) || ('/admin/' + name);
    L.pages[path] = (L.pages[path] || 0) + 1;
    L.last = path;
    if (!L.since) L.since = Date.now();
    window.DB.save();
  }

  /* делегирование событий внутри контента */
  /**
   * Телефонный вид таблиц.
   *
   * На 390px таблицы на 4–7 колонок не влезают: часть данных уезжала за
   * правый край, и её не было видно без горизонтальной прокрутки. Здесь
   * каждая строка превращается в карточку: заголовок колонки подписывается
   * перед значением, поэтому ничего не теряется.
   */
  function mobileTables() {
    var host = document.getElementById('view');
    if (!host || !window.matchMedia('(max-width:860px)').matches) return;

    host.querySelectorAll('table.t').forEach(function (t) {
      if (t.classList.contains('t--cards') || t.closest('[data-cards]')) return;

      var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) {
        return th.textContent.trim();
      });

      /* Матрица прав (столбцы-роли) читается только как таблица: её не
         ломаем, ей достаточно горизонтальной прокрутки. */
      /* Матрица прав: много столбцов именно потому, что каждый = роль. У неё
         в заголовке есть «Роль», остальные таблицы превращаем в карточки. */
      if (heads[0] && /^роль$/i.test(heads[0])) return;

      t.classList.add('t--cards');
      t.setAttribute('data-cards', '1');

      t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) {
          if (td.hasAttribute('colspan')) return;
          var label = heads[i] || '';
          if (label) td.setAttribute('data-l', label);
        });
      });
    });
  }

  function bindView() {
    var host = document.getElementById('view');
    mobileTables();

    host.querySelectorAll('[data-go]').forEach(function (el) {
      el.addEventListener('click', function () { go(el.getAttribute('data-go')); });
    });
    host.querySelectorAll('[data-player]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (e.target.closest('button')) return;
        openPlayer(+el.getAttribute('data-player'));
      });
    });
    host.querySelectorAll('[data-acc]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (e.target.closest('button')) return;
        openAccount(el.getAttribute('data-acc'));
      });
    });
    host.querySelectorAll('[data-char]').forEach(function (el) {
      el.addEventListener('click', function () { openChar(el.getAttribute('data-char')); });
    });
    host.querySelectorAll('[data-clan]').forEach(function (el) {
      el.addEventListener('click', function () { openClan(el.getAttribute('data-clan')); });
    });
    host.querySelectorAll('[data-pay]').forEach(function (el) {
      el.addEventListener('click', function () {
        go('finance'); setTimeout(function () {
          finFilter.sel = +el.getAttribute('data-pay');
          if (window.matchMedia('(max-width:1080px)').matches) finFilter.mobileDetail = true;
          render();
        }, 30);
      });
    });

    /* поля поиска с задержкой */
    function bindInput(sel, obj, field, kind) {
      host.querySelectorAll(sel).forEach(function (inp) {
        inp.addEventListener(kind === 'select' ? 'change' : 'input', function () {
          var f = inp.getAttribute(sel.indexOf('[') >= 0 ? sel.match(/\[(.*?)\]/)[1] : 'data-x') || 'q';
          obj[f] = inp.value;
          if (f === 'q' || f === 'actor') obj.page = 1;
          render();
        });
      });
    }
    host.querySelectorAll('[data-pf]').forEach(function (i) {
      i.addEventListener('input', function () { playersFilter[i.getAttribute('data-pf')] = i.value; playersFilter.page = 1; render(); });
    });
    host.querySelectorAll('[data-af]').forEach(function (i) {
      i.addEventListener('input', function () { accFilter[i.getAttribute('data-af')] = i.value; accFilter.page = 1; render(); });
    });
    host.querySelectorAll('[data-cf]').forEach(function (i) {
      i.addEventListener('input', function () { charFilter[i.getAttribute('data-cf')] = i.value; charFilter.page = 1; render(); });
    });
    host.querySelectorAll('[data-ff]').forEach(function (i) {
      i.addEventListener('input', function () {
        finFilter[i.getAttribute('data-ff')] = i.value; finFilter.page = 1;
        clearTimeout(i._t); i._t = setTimeout(render, 260);
      });
    });
    host.querySelectorAll('[data-lf]').forEach(function (i) {
      var ev = i.tagName === 'SELECT' ? 'change' : 'input';
      i.addEventListener(ev, function () { logFilter[i.getAttribute('data-lf')] = i.value; logFilter.page = 1; render(); });
    });

    /* сегменты */
    /* Один обработчик на каждую кнопку: внутри forEach привязка к последней
       кнопке ломала выбор периода и фильтров — «14 дней» показывало то же,
       что «30 дней». */
    host.querySelectorAll('.seg').forEach(function (seg) {
      var key = seg.getAttribute('data-seg');
      seg.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          var v = b.getAttribute('data-val');
          if (key === 'only') playersFilter.only = v;
          else if (key === 'sort') playersFilter.sort = v;
          else if (key === 'acc-only') accFilter.only = v;
          else if (key === 'char-only') charFilter.only = v;
          else if (key === 't-status') tickFilter.status = v;
          else if (key === 'ana') anaPeriod = +v;
          render();
        });
      });
    });

    /* фильтры заявок */
    host.querySelectorAll('[data-fstatus]').forEach(function (b) {
      b.addEventListener('click', function () { finFilter.status = b.getAttribute('data-fstatus'); finFilter.page = 1; render(); });
    });
    host.querySelectorAll('[data-fin-back]').forEach(function (b) {
      b.addEventListener('click', function () { finFilter.mobileDetail = false; render(); });
    });
    host.querySelectorAll('[data-paysel]').forEach(function (r) {
      r.addEventListener('click', function () { finFilter.sel = +r.getAttribute('data-paysel'); render(); });
    });
    host.querySelectorAll('[data-tsel]').forEach(function (r) {
      r.addEventListener('click', function () {
        tickFilter.sel = +r.getAttribute('data-tsel');
        /* На узком экране список и переписка не помещаются рядом:
           показываем карточку отдельным экраном (как в «Платежах»). */
        if (window.matchMedia('(max-width:1080px)').matches) tickFilter.mobileDetail = true;
        render();
      });
    });
    host.querySelectorAll('[data-tick-back]').forEach(function (b) {
      b.addEventListener('click', function () { tickFilter.mobileDetail = false; render(); });
    });
    host.querySelectorAll('[data-page]').forEach(function (b) {
      b.addEventListener('click', function () {
        var pg = +b.getAttribute('data-page'), kind = b.getAttribute('data-kind');
        if (kind === 'players') playersFilter.page = pg;
        if (kind === 'acc') accFilter.page = pg;
        if (kind === 'char') charFilter.page = pg;
        if (kind === 'fin') finFilter.page = pg;
        if (kind === 'log') logFilter.page = pg;
        render();
      });
    });

    /* действия по заявкам */
    host.querySelectorAll('[data-payok]').forEach(function (b) {
      b.addEventListener('click', function () { creditPayment(+b.getAttribute('data-payok')); });
    });
    host.querySelectorAll('[data-payno]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = +b.getAttribute('data-payno');
        UI.confirmModal({
          title: 'Отклонить заявку #' + id, sub: 'Монеты не начисляются',
          text: 'Заявка получит статус «отменена». Игроку стоит объяснить причину в тикете.',
          danger: true, okText: 'Отклонить',
          onOk: function () {
            /* Статус ставит сервер: иначе после перезагрузки заявка снова «на проверке». */
            window.API.paymentSet({ id: id, act: 'cancel' }).then(function () {
              UI.toast('Заявка #' + id + ' отклонена', 'warn');
              return window.DB.reload();
            }).then(render).catch(function (e) {
              UI.toast(e.message || 'Не удалось отклонить заявку', 'bad');
            });
          }
        });
      });
    });

    /* очередь выдачи */
    host.querySelectorAll('[data-qdone]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = +b.getAttribute('data-qdone');
        window.API.itemQueue({ id: id, act: 'done' }).then(function () {
          UI.toast('Отмечено: выдача закрыта', 'ok');
          return window.DB.reload();
        }).then(render).catch(function (e) { UI.toast(e.message || 'Не удалось обновить очередь', 'bad'); });
      });
    });
    host.querySelectorAll('[data-qdel]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = +b.getAttribute('data-qdel');
        UI.confirmModal({
          title: 'Отменить выдачу', sub: 'Строка удалится из очереди',
          text: 'Отменить можно только до входа персонажа в игру. Действие попадёт в журнал.',
          danger: true, okText: 'Отменить выдачу',
          onOk: function () {
            window.API.itemQueue({ id: id, act: 'cancel' }).then(function () {
              UI.toast('Выдача отменена', 'warn');
              return window.DB.reload();
            }).then(render).catch(function (e) { UI.toast(e.message || 'Не удалось отменить выдачу', 'bad'); });
          }
        });
      });
    });
    host.querySelectorAll('[data-unban]').forEach(function (b) {
      b.addEventListener('click', function () {
        var lg = b.getAttribute('data-unban');
        /* Разбан — запись в игровую базу, а не только на экране. */
        window.API.ban({ login: lg, mode: 'unban' }).then(function () {
          UI.toast('Бан снят: ' + lg, 'ok');
          return window.DB.reload();
        }).then(render).catch(function (e) {
          UI.toast(e.message || 'Не удалось снять бан', 'bad');
        });
      });
    });

    /* тикеты */
    host.querySelectorAll('[data-treply]').forEach(function (b) {
      b.addEventListener('click', function () { replyTicket(+b.getAttribute('data-treply'), false); });
    });
    host.querySelectorAll('[data-tclose]').forEach(function (b) {
      b.addEventListener('click', function () { replyTicket(+b.getAttribute('data-tclose'), true); });
    });
    host.querySelectorAll('[data-topen]').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = S.tickets.filter(function (x) { return x.id === +b.getAttribute('data-topen'); })[0];
        window.API.ticketReply({ id: t.id, open: true }).then(function () {
          UI.toast('Тикет #' + t.id + ' снова открыт', 'ok');
          return window.DB.reload();
        }).then(render).catch(function (e) { UI.toast(e.message || 'Не удалось открыть тикет', 'bad'); });
      });
    });
    host.querySelectorAll('[data-tpl]').forEach(function (b) {
      b.addEventListener('click', function () {
        var ta = host.querySelector('#tk-text');
        if (!ta) return;
        ta.value = ['Здравствуйте! Проверяю вашу заявку, вернусь с ответом в течение 15 минут.',
          'Монеты начислены, проверьте баланс в кабинете.',
          'Пришлите, пожалуйста, скриншот операции — так разберёмся быстрее.'][+b.getAttribute('data-tpl')];
        ta.focus();
      });
    });

    /* роли: снять и изменить — только у тех, кто ниже по рангу */
    host.querySelectorAll('[data-revoke]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = UI.playerById(+b.getAttribute('data-revoke'));
        if (!p) return;
        if (isSuper(p)) return UI.toast('Владельцы равны по правам: снять владельца из панели нельзя', 'bad');
        if (!canEditRole(p)) return UI.toast('Недостаточно прав: роль равна вашей или выше', 'bad');
        UI.confirmModal({
          title: 'Снять роль', sub: p.login + ' · ' + UI.roleName(p.role),
          text: 'Игрок потеряет доступ к разделам панели. Игровой аккаунт и баланс не затрагиваются.',
          danger: true, okText: 'Снять роль',
          onOk: function () {
            /* «Снять роль» = записать роль «Игрок» (user): сервер сам удалит строку. */
            window.API.roleSet({ id: p.id, role: 'user' }).then(function () {
              UI.resetRightsCache();
              UI.toast('Роль снята: ' + p.login, 'warn');
              return window.DB.reload();
            }).then(render).catch(function (e) { UI.toast(e.message || 'Роль не снята', 'bad'); });
          }
        });
      });
    });
    host.querySelectorAll('[data-edit-role]').forEach(function (b) {
      b.addEventListener('click', function () { openRoleAssign(+b.getAttribute('data-edit-role')); });
    });

    /* настройки: шлюзы */
    host.querySelectorAll('[data-gw]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var key = cb.getAttribute('data-gw');
        var g = S.settings.gateways.filter(function (x) { return x.key === key; })[0];
        var on = cb.checked;
        var payload = {};
        payload['cfg_' + key + '_allow'] = on ? '1' : '0';

        /* Выключатель пишет в config: иначе на экране «включён», а шлюз
           на сайте по-прежнему выключен. */
        window.API.settingsSave(payload).then(function () {
          UI.toast((g ? g.name : key) + (on ? ' включён' : ' выключен'), on ? 'ok' : 'warn');
          return window.DB.reload();
        }).then(render).catch(function (e) {
          cb.checked = !on;
          UI.toast(e.message || 'Не удалось изменить шлюз', 'bad');
        });
      });
    });

    /* тема */
    host.querySelectorAll('[data-theme-pick]').forEach(function (b) {
      b.addEventListener('click', function () {
        UI.setTheme(b.getAttribute('data-theme-pick'));
        render();
      });
    });

    /* общие кнопки действий */
    host.querySelectorAll('[data-act]').forEach(function (b) {
      b.addEventListener('click', function () { action(b.getAttribute('data-act'), b); });
    });

    /* аккордеоны в игровых действиях */
    host.querySelectorAll('[data-acc-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var key = b.getAttribute('data-acc-toggle');
        var body = document.getElementById('acc-' + key);
        var open = !body.classList.contains('hide');
        host.querySelectorAll('.acc__body').forEach(function (x) { x.classList.add('hide'); });
        host.querySelectorAll('.acc').forEach(function (x) { x.classList.remove('is-open'); });
        if (!open) { body.classList.remove('hide'); b.closest('.acc').classList.add('is-open'); }
      });
    });
    host.querySelectorAll('[data-quick-give]').forEach(function (b) {
      b.addEventListener('click', function () {
        var lg = document.getElementById('qg-login').value.trim();
        var ch = document.getElementById('qg-char').value.trim();
        var itemId = parseInt(document.getElementById('qg-item').value, 10) || 57;
        var count = Math.max(1, parseInt(document.getElementById('qg-count').value, 10) || 1);
        var ench = Math.max(0, Math.min(30, parseInt(document.getElementById('qg-ench').value, 10) || 0));
        var reason = document.getElementById('qg-reason').value.trim();
        if (!lg) return UI.toast('Укажите игровой аккаунт', 'bad');
        if (!ch) return UI.toast('Укажите имя персонажа', 'bad');
        var push = function () {
          /* Быстрая форма пишет в ту же игровую базу, что и модалка. */
          window.API.giveItem({ login: lg, char: ch, itemId: itemId, count: count, enchant: ench, reason: reason })
            .then(function () {
              UI.toast('Выдано: ID ' + itemId + ' × ' + count + ' → ' + ch, 'ok');
              return window.DB.reload();
            })
            .then(render)
            .catch(function (e) { UI.toast(e.message || 'Выдача не прошла', 'bad'); });
        };
        if (ench > 16) UI.confirmModal({ title: 'Заточка выше 16', text: 'Подтвердите, что выдача согласована с владельцем проекта.',
          danger: true, okText: 'Да, выдать', onOk: push }); else push();
      });
    });
    host.querySelectorAll('[data-quick-ban]').forEach(function (b) {
      b.addEventListener('click', function () {
        var lg = document.getElementById('qb-login').value.trim();
        var a = S.accounts.filter(function (x) { return x.login === lg; })[0];
        if (!a) return UI.toast('Такого аккаунта нет в базе сервера', 'bad');
        var h = parseInt(document.getElementById('qb-hours').value, 10);
        var reason = document.getElementById('qb-reason').value.trim() || 'нарушение правил';
        window.API.ban({ login: a.login, mode: 'ban', hours: (h === -1 ? 24 * 365 : h), reason: reason })
          .then(function (res) {
            UI.toast('Забанен: ' + a.login + ' до ' + dt(res.until), 'warn');
            return window.DB.reload();
          })
          .then(render)
          .catch(function (e) { UI.toast(e.message || 'Бан не прошёл', 'bad'); });
      });
    });

    /* живой счётчик аналитики */
    var live = document.getElementById('ana-live');
    if (live) {
      if (window._anaTimer) clearInterval(window._anaTimer);
      window._anaTimer = setInterval(function () {
        if (!document.getElementById('ana-live')) { clearInterval(window._anaTimer); return; }
        var v = document.getElementById('ana-live');
        var n = parseInt(v.textContent, 10) + (Math.random() > 0.5 ? 1 : -1);
        v.textContent = Math.max(1, n);
      }, 4000);
    }
  }

  function replyTicket(id, close) {
    var t = S.tickets.filter(function (x) { return x.id === id; })[0];
    var ta = document.getElementById('tk-text');
    var text = ta ? ta.value.trim() : '';
    if (!text) return UI.toast('Напишите текст ответа', 'bad');
    /* Ответ ложится в tickets_answers, статус — в tickets.status. */
    window.API.ticketReply({ id: t.id, text: text, close: !!close })
      .then(function () {
        UI.toast(close ? 'Ответ отправлен, тикет #' + t.id + ' закрыт' : 'Ответ отправлен в тикет #' + t.id, 'ok');
        if (!close) tickFilter.sel = t.id;
        return window.DB.reload();
      })
      .then(render)
      .catch(function (e) { UI.toast(e.message || 'Ответ не отправился', 'bad'); });
  }

  function openSystemDiagnostic() {
    UI.openModal({
      title: 'Самодиагностика и проверка системы',
      sub: 'Автоматический тест базы, игрового сервера, конфигов и PWA',
      wide: true,
      body: '<div id="diag-box" style="text-align:center;padding:26px 0">' +
        '<div class="boot__ring" style="margin:0 auto 14px"></div>' +
        '<p class="dim">Выполняется комплекс проверок…</p></div>',
      foot: '<button class="btn btn--ghost" data-close="1">Закрыть</button>' +
        '<button class="btn btn--primary" id="diag-rerun">' + ico('refresh') + 'Повторить</button>',
      onMount: function (m) {
        var box = m.querySelector('#diag-box');
        function run() {
          box.innerHTML = '<div class="boot__ring" style="margin:0 auto 14px"></div><p class="dim">Выполняется опрос сервисов…</p>';
          window.API.diagnostic().then(function (res) {
            var r = (res && res.report) || {};
            var db = r.database || {};
            var game = r.game_server || {};
            var cfg = r.config || {};
            var pwa = r.pwa || {};
            var sys = r.system || {};

            function badge(ok, text) {
              return '<span class="badge ' + (ok ? 'badge--ok' : 'badge--bad') + '" style="font-size:12px;padding:3px 8px">' +
                ico(ok ? 'check' : 'alert') + ' ' + E(text || (ok ? 'Норма' : 'Внимание')) + '</span>';
            }

            var html = '<div class="diag-suite" style="display:flex;flex-direction:column;gap:12px;text-align:left">' +
              '<div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">' +
                  '<b>' + ico('coin') + ' База данных MySQL (сайт)</b>' + badge(db.ok, db.latency_ms ? db.latency_ms + ' ms' : '') +
                '</div>' +
                '<p class="dim" style="font-size:12.5px;margin:0">' +
                  (db.ok ? 'Подключение активно. Пользователей: ' + nf(db.tables && db.tables.users ? db.tables.users.count : 0) +
                   ' · Записей ролей: ' + nf(db.tables && db.tables.roles ? db.tables.roles.count : 0) +
                   ' · Визитов: ' + nf(db.tables && db.tables.ghtweb_visit_log ? db.tables.ghtweb_visit_log.count : 0) :
                   '<span style="color:var(--bad)">' + E(db.error || 'Ошибка БД') + '</span>') +
                '</p>' +
              '</div>' +

              '<div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">' +
                  '<b>' + ico('swords') + ' Игровой сервер Lucera2 (GS)</b>' + badge(game.ok, game.latency_ms ? game.latency_ms + ' ms' : 'Автономный режим') +
                '</div>' +
                '<p class="dim" style="font-size:12.5px;margin:0">' +
                  (game.ok ? 'Связь с базой игры активна. Аккаунтов: ' + nf(game.accounts_count) + ' · Персонажей: ' + nf(game.characters_count) + ' · Онлайн: ' + nf(game.online_count) :
                   'Игровая база недоступна в текущем контуре (панель и сайт работают штатно в режиме кэша).') +
                '</p>' +
              '</div>' +

              '<div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">' +
                  '<b>' + ico('gear') + ' Конфигурация владельцев (params.php)</b>' + badge(cfg.ok, cfg.writable ? 'Запись разрешена' : 'Только чтение') +
                '</div>' +
                '<p class="dim" style="font-size:12.5px;margin:0">' +
                  'Права доступа: ' + E(cfg.permissions || '0644') + ' · Владельцев в списке superAdminId: ' + (cfg.owners_count || 0) +
                  (cfg.error ? '<br><span style="color:var(--bad)">' + E(cfg.error) + '</span>' : '') +
                '</p>' +
              '</div>' +

              '<div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">' +
                  '<b>' + ico('lifebuoy') + ' PWA & Офлайн-страница</b>' + badge(pwa.ok, pwa.ok ? 'Все 10 файлов на месте' : 'Неполный пакет') +
                '</div>' +
                '<p class="dim" style="font-size:12.5px;margin:0">' +
                  (pwa.ok ? 'Манифест, service worker v4, офлайн-заглушка и набор иконок проверены.' :
                   'Отсутствуют обязательные файлы: ' + (pwa.missing || []).join(', ')) +
                '</p>' +
              '</div>' +

              '<div style="font-size:12px;color:var(--mut);display:flex;justify-content:space-between;align-items:center;padding:4px 2px">' +
                '<span>PHP ' + E(sys.php_version || '') + ' (' + E(sys.sapi || '') + ') · Память: ' + (sys.memory_used_mb || 0) + ' MB</span>' +
                '<span>Время проверки: ' + (r.duration_ms || 0) + ' ms</span>' +
              '</div>' +
            '</div>';

            box.innerHTML = html;
          }).catch(function (err) {
            box.innerHTML = '<div style="color:var(--bad);padding:18px">' +
              ico('alert') + ' <b>Сбой выполнения проверки</b><p class="dim">' + E(err.message || err) + '</p></div>';
          });
        }
        run();
        var rerunBtn = m.querySelector('#diag-rerun');
        if (rerunBtn) rerunBtn.addEventListener('click', run);
      }
    });
  }

  function action(name, btn) {
    switch (name) {
      case 'give-item': openGiveForm('', ''); break;
      case 'new-player': openNewPlayer(); break;
      case 'ban': openBanForm(''); break;
      case 'balance-op': openBalanceOp(''); break;
      case 'assign-role': openRoleAssign(0); break;
      case 'owner-add': openOwnerGrant(); break;
      case 'save-settings': saveSettings(); break;
      case 'refresh':
        /* Никакого «сброса данных»: панель показывает базу, а не свою копию.
           Кнопка просто перечитывает состояние с сервера. */
        window.DB.reload().then(function () {
          UI.toast('Данные перечитаны из базы', 'ok');
          render();
        }).catch(function (e) { UI.toast(e.message || 'Не удалось перечитать данные', 'bad'); });
        break;
      case 'players-csv':
        UI.exportCsv('players.csv', [['ID', 'Логин', 'E-mail', 'Баланс', 'Роль', 'Статус', 'Последний вход']].concat(
          S.players.map(function (p) { return [p.id, p.login, p.email, p.balance, p.role ? UI.roleName(p.role) : 'игрок', p.status, dt(p.lastLogin)]; })));
        break;
      case 'accounts-csv':
        UI.exportCsv('accounts.csv', [['Аккаунт', 'IP', 'Последний вход', 'Бан', 'До бана', 'Причина']].concat(
          S.accounts.map(function (a) { return [a.login, a.ip, dt(a.lastLogin), a.banned ? 'да' : 'нет', a.banned ? dt(a.banUntil) : '', a.banReason || '']; })));
        break;
      case 'chars-csv':
        UI.exportCsv('characters.csv', [['Персонаж', 'Аккаунт', 'Уровень', 'Класс', 'Клан', 'PvP', 'PK', 'Карма']].concat(
          S.characters.map(function (c) { return [c.name, c.account, c.level, c.cls, c.clan, c.pvp, c.pk, c.karma]; })));
        break;
      case 'clans-csv':
        UI.exportCsv('clans.csv', [['Клан', 'Лидер', 'Уровень', 'Участников', 'Репутация', 'Замок']].concat(
          S.clans.map(function (c) { return [c.name, c.leader, c.level, c.members, c.rep, c.castle]; })));
        break;
      case 'pays-csv':
        UI.exportCsv('payments.csv', [['№', 'Игрок', 'Монеты', 'USD', 'Шлюз', 'Статус', 'Счёт', 'Создана']].concat(
          S.payments.map(function (p) { return [p.id, p.player, p.coins, p.usd, p.gateway, p.status, p.invoice, dt(p.createdAt)]; })));
        break;
      case 'log-csv':
        var exportList = S.log.filter(function (l) {
          if (logFilter.actor  !== 'all' && l.actor  !== logFilter.actor)  return false;
          if (logFilter.action !== 'all' && l.action !== logFilter.action) return false;
          if (logFilter.q) {
            var q = logFilter.q.toLowerCase();
            if ((l.action + ' ' + l.target + ' ' + l.details).toLowerCase().indexOf(q) < 0) return false;
          }
          return true;
        });
        UI.exportCsv('journal-filtered.csv', [['Время', 'Кто', 'Действие', 'Объект', 'Детали']].concat(
          exportList.map(function (l) { return [dt(l.at), l.actor, l.action, l.target, l.details]; })));
        break;
      case 'system-diagnostic':
        openSystemDiagnostic();
        break;
      case 'ana-csv':
        UI.exportCsv('analytics.csv', [['Дата', 'Посетители', 'Просмотры', 'Регистрации']].concat(
          S.analytics.daily.map(function (d) { return [d.date, d.visitors, d.views, d.registered]; })));
        break;
      case 'db-sync':
        if (!UI.can(window.DB.CAP.analytics)) { UI.toast('Недостаточно прав', 'bad'); break; }
        UI.openModal({
          title: 'Синхронизация с базой', sub: 'Пересборка витрины аналитики из данных',
          body: '<p class="field__h" style="margin:0 0 12px">Панель заново считает сводку из таблиц ' +
            '<span class="mono">visit_log</span> и <span class="mono">gs</span> и сверяет её с цифрами на экранах.</p>' +
            '<div id="sync-log" class="synclog"><p class="dim">Готово к запуску</p></div>',
          foot: '<button class="btn btn--primary" id="sync-run">' + ico('refresh') + 'Запустить</button>',
          onMount: function (m) {
            var log = m.querySelector('#sync-log');
            m.querySelector('#sync-run').addEventListener('click', function () {
              var steps = [
                'SELECT COUNT(*) FROM ghtweb_visit_log WHERE is_bot = 0',
                'SELECT DATE(created_at), COUNT(DISTINCT visit_key) FROM ghtweb_visit_log GROUP BY 1',
                'SELECT HOUR(created_at), COUNT(*) FROM ghtweb_visit_log GROUP BY 1',
                'SELECT COUNT(*) FROM characters WHERE online = 1 (gs)',
                "SELECT COUNT(*) FROM ghtweb_transactions WHERE status = 'pending'"
              ];
              log.innerHTML = '';
              var i = 0;
              var t = setInterval(function () {
                if (i >= steps.length) {
                  clearInterval(t);
                  S.dbSnapshot.builtAt = Date.now();
                  S.dbSnapshot.visitsTracked = (S.dbSnapshot.visitsTracked || 0) + (S.analytics.live ? S.analytics.live.total : 0);
                  window.DB.save();
                  log.insertAdjacentHTML('beforeend',
                    '<p class="synclog__ok">' + ico('check') + 'Снимок обновлён: ' + dt(S.dbSnapshot.builtAt) + '</p>');
                  UI.toast('Аналитика синхронизирована с базой', 'ok');
                  setTimeout(function () { UI.closeModal(); render(); }, 700);
                  return;
                }
                log.insertAdjacentHTML('beforeend',
                  '<p class="synclog__i"><span class="mono">' + E(steps[i]) + '</span>' +
                  '<span class="synclog__ok2">ok · ' + (12 + i * 7) + ' ms</span></p>');
                i++;
              }, 320);
            });
          }
        });
        break;
      case 'db-schema': openDbSchema(); break;
      case 'live-reset':
        UI.confirmModal({
          title: 'Сбросить счётчик сессии', sub: 'Клиентский трекер переходов',
          text: 'Обнулится только счётчик ваших переходов в этой сессии. Данные visit_log и снимок базы не тронуты.',
          okText: 'Сбросить',
          onOk: function () { resetLive(); render(); UI.toast('Счётчик сессии обнулён', 'ok'); }
        });
        break;
      case 'settings-csv':
        UI.exportCsv('settings.csv', [['Параметр', 'Значение']].concat([
          ['rate', S.settings.rate], ['minDeposit', S.settings.minDeposit], ['freeUntil', S.settings.freeUntil]
        ].concat(S.settings.gateways.map(function (g) { return [g.key + '.allow', g.allow ? 1 : 0]; }))));
        break;
    }
  }
  function saveSettings() {
    /* Значения берём прямо с экрана и отправляем на сервер: он пишет их в
       таблицу config. Сохраняем только то, что реально правится. */
    var payload = {};
    var free = document.getElementById('st-free');

    if (free && free.value) {
      payload.cfg_donation_free_week_until = free.value.replace('T', ' ');
    }

    window.API.settingsSave(payload).then(function (res) {
      var n = res && res.changed ? Object.keys(res.changed).length : 0;
      UI.toast(n ? 'Сохранено параметров: ' + n : 'Изменений нет', n ? 'ok' : 'info');
      return window.DB.reload();
    }).then(function () { render(); }).catch(function (e) {
      UI.toast(e.message || 'Не удалось сохранить настройки', 'bad');
    });
  }

  /* =====================  СТАРТ  ===================== */
  var perms = {};
  Object.keys(ROUTES).forEach(function (k) { perms[k] = ROUTES[k].perm; });

  window.APP = {
    go: go, render: render, stats: stats, perms: perms, routes: ROUTES,
    openPlayer: openPlayer, openAccount: openAccount, openChar: openChar, openClan: openClan,
    openGiveForm: openGiveForm, openBanForm: openBanForm, openBalanceOp: openBalanceOp,
    openRoleAssign: openRoleAssign, creditPayment: creditPayment
  };
  window.ICON = ico;

  document.addEventListener('DOMContentLoaded', function () {
    UI.setTheme(S.theme || 'aurum');
    go('home');
  });
})();
