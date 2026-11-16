/* ==========================================================================
   LA2PLUS ADMIN — живой источник данных.

   Прототип брал состояние из localStorage. Здесь тот же объект приходит с
   сервера одним запросом, а действия уходят на /adm/*. Формат совпадает с
   прототипом, поэтому экраны (views.js) не пришлось переписывать.
   ========================================================================== */
(function () {
  'use strict';

  var BASE = '/adm';

  /* Токен CSRF: Yii проверяет его на POST и читает только из $_POST
     (этот CHttpRequest не смотрит на заголовки). Поэтому токен идёт
     и полем формы, и заголовком. */
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /* Имя поля с токеном задаётся конфигом сайта (GHTWEB_CSRF_TOKEN), поэтому
     берём его из шаблона, а не хардкодим. */
  function csrfName() {
    var m = document.querySelector('meta[name="csrf-name"]');
    return m && m.getAttribute('content') ? m.getAttribute('content') : 'YII_CSRF_TOKEN';
  }

  function url(action, params) {
    var q = [];
    for (var k in params) {
      if (params.hasOwnProperty(k) && params[k] !== undefined && params[k] !== null) {
        q.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
      }
    }
    return BASE + '/' + action + (q.length ? '?' + q.join('&') : '');
  }

  function raw(action, data) {
    var token = csrf();
    var body = data || {};
    /* Токен — полем POST, данные — JSON в теле. Собираем их вместе,
       чтобы сервер получил и то и другое. */
    var form = new FormData();
    form.append(csrfName(), token);
    form.append('__json', JSON.stringify(body));

    return fetch(url(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': token
      },
      body: form
    }).then(function (res) {
      return res.text().then(function (text) {
        var json;
        try { json = JSON.parse(text); } catch (e) {
          throw new Error('Сервер ответил не JSON (код ' + res.status + '). ' + text.slice(0, 200));
        }
        if (!json.ok) throw new Error(json.error || ('Ошибка ' + res.status));
        return json;
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /* Состояние                                                        */
  /* ---------------------------------------------------------------- */

  /* Один объект на всю страницу: core/views/shell берут ссылку один раз при
     загрузке, поэтому состояние не подменяется, а наполняется. */
  var state = null;
  var listeners = [];

  function empty() {
    return {
      version: 6, ready: false, theme: 'aurum', sideOpen: true, navOrder: [],
      me: { login: '', id: 0, role: 'user' },
      players: [], accounts: [], characters: [], clans: [], payments: [], walletOps: [],
      tickets: [], log: [], itemQueue: [], bans: [], settings: { gateways: [], bonuses: [] },
      staff: [], owners: [], roles: {}, analytics: null,
      dbSnapshot: {}, counters: {}
    };
  }

  /** Снимок с сервера → объект состояния прототипа. */
  function fromServer(data) {
    var st = empty();
    var snap = data.server || {};

    st.me = {
      login: data.me.login, id: data.me.id, role: data.me.role,
      roleName: data.me.roleName, isOwner: data.me.isOwner,
      canManageOwners: data.me.canManageOwners, perms: data.me.perms || [],
      balance: data.me.balance, gsId: data.me.gsId, lsId: data.me.lsId
    };

    st.players = data.players || [];
    st.accounts = data.accounts || [];
    st.characters = data.characters || [];
    st.clans = data.clans || [];
    st.payments = data.payments || [];
    st.walletOps = data.walletOps || [];
    st.tickets = data.tickets || [];
    st.log = data.log || [];
    st.bans = data.bans || [];
    st.settings = data.settings || { gateways: [], bonuses: [] };
    st.staff = data.staff || [];
    st.owners = data.owners || [];
    st.roles = data.roles || {};
    st.analytics = data.analytics || null;

    st.dbSnapshot = {
      builtAt: data.builtAt,
      source: 'MySQL сайта + игровая база Lucera2',
      users: snap.users || 0,
      usersTotal: snap.accountsTotal || snap.users || 0,
      accounts: snap.accounts || 0,
      characters: snap.characters || 0,
      charactersOnline: snap.charactersOnline || 0,
      balance: snap.balance || 0,
      balanceOrphan: snap.balanceOrphan || 0,
      walletOrphans: snap.walletOrphans || 0,
      clans: snap.clans || 0,
      payments: st.payments.length,
      paymentsPending: st.payments.filter(function (p) { return p.status === 'pending'; }).length,
      ticketsOpen: snap.ticketsOpen || 0,
      visitsTracked: (st.analytics && st.analytics.summary && st.analytics.summary.visits) || 0,
      online: snap.online || 0,
      gameOk: !!snap.gameOk,
      payToday: snap.payToday || 0
    };

    /* то, что прототип держал отдельно, а сервер отдаёт вместе */
    st.onlineNow = snap.online || 0;
    st.balanceOrphan = snap.balanceOrphan || 0;
    st.walletOrphans = snap.walletOrphans || 0;
    st.ticketsOpen = snap.ticketsOpen || 0;
    st.gameOk = !!snap.gameOk;
    st.payToday = snap.payToday || 0;

    /* владельцы: прототип проверяет их по списку ID */
    window.DB.superAdmins = (data.owners || []).slice();
    /* карта «действие панели → право» тоже серверная: интерфейс не держит
       второй список прав, который можно забыть обновить */
    window.DB.actionPerms = data.actions || {};
    /* матрица прав приходит с сервера — «кто что может» показывает реальную */
    if (data.roles && data.roles.list) {
      window.DB.ROLES_SERVER = data.roles;
      window.DB.rebuildRoles();
    }

    return st;
  }

  function emit() {
    for (var i = 0; i < listeners.length; i++) {
      try { listeners[i](state); } catch (e) { /* не роняем интерфейс из-за подписчика */ }
    }
  }

  function load() {
    return fetch(url('state'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (res.status === 401 || res.status === 403) {
          window.location.href = '/login/?next=' + encodeURIComponent('/admin/');
          throw new Error('Нужен вход');
        }
        return res.json();
      })
      .then(function (json) {
        if (!json.ok) throw new Error(json.error || 'Не удалось загрузить состояние');

        var fresh = fromServer(json);
        var target = state || (state = empty());

        for (var k in target) {
          if (target.hasOwnProperty(k) && !fresh.hasOwnProperty(k)) delete target[k];
        }
        for (var j in fresh) {
          if (fresh.hasOwnProperty(j)) target[j] = fresh[j];
        }

        emit();
        return target;
      });
  }

  /**
   * После действия: попросить свежие данные и перерисовать.
   * Так цифры на экране всегда совпадают с базой.
   */
  function reload() {
    return load();
  }

  /* Периодическое обновление шапки: онлайн и тикеты меняются сами */
  var timer = null;

  function pulse() {
    return fetch(url('pulse'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok || !state) return;
        state.onlineNow = j.online;
        state.ticketsOpen = j.ticketsOpen;
        if (state.dbSnapshot) {
          state.dbSnapshot.ticketsOpen = j.ticketsOpen;
          state.dbSnapshot.online = j.online;
        }
        if (window.APP && window.APP.refreshChrome) window.APP.refreshChrome();
        return j;
      })
      .catch(function () { /* сеть моргнула — не страшно */ });
  }

  function startPolling() {
    if (timer) return;
    timer = setInterval(pulse, 30000);

    /* Экономия батареи и трафика: при скрытой вкладке останавливаем опрос,
       при возврате — обновляем данные сразу, без ожидания тика таймера. */
    if (typeof document.addEventListener === 'function' && 'visibilityState' in document) {
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          if (timer) { clearInterval(timer); timer = null; }
        } else {
          pulse();
          if (!timer) timer = setInterval(pulse, 30000);
        }
      });
    }
  }

  /* ---------------------------------------------------------------- */
  /* Публичный объект                                                 */
  /* ---------------------------------------------------------------- */

  window.DB = window.DB || {};
  window.DB.superAdmins = window.DB.superAdmins || [];
  window.DB.roleKeys = ['owner', 'admin', 'moder', 'gm', 'finance', 'user'];

  /** Живое состояние: тот же объект, который наполняет load(). */
  window.DB.state = function () { return state || (state = empty()); };

  /** Сохранить: в прототипе это писало в localStorage, здесь — просто уведомить. */
  window.DB.save = function () { emit(); };

  /** Сброс больше не «удаляет данные»: это перезагрузка с сервера. */
  window.DB.reset = function () { return reload(); };

  window.DB.now = function () { return Date.now(); };

  /** Загрузка состояния до старта интерфейса. */
  window.DB.load = load;
  window.DB.reload = reload;
  window.DB.onChange = function (fn) { listeners.push(fn); };
  window.DB.startPolling = startPolling;
  window.DB.pulse = pulse;

  /* ---------------------------------------------------------------- */
  /* Роли и права                                                     */
  /*                                                                  */
  /* Ключи ролей — серверные: user и finance. Раньше здесь жили       */
  /* player/accountant, которых RoleService не знает: панель           */
  /* отправляла «player», сервер отвечал «Неизвестная роль», и снятие  */
  /* доступа не работало. Названия кнопок берём из ответа сервера,     */
  /* чтобы подписи в панели и в базе не расходились.                   */
  /* ---------------------------------------------------------------- */

  /* ------------------------------------------------------------------ */
  /* Права экранов                                                        */
  /*                                                                      */
  /* Один словарь на всю панель. Раньше экраны звали права, которых нет   */
  /* в RoleService (staff.view, game.act), и раздел молча закрывался для  */
  /* тех, кому он разрешён. Здесь каждое право экрана — имя права сервера. */
  /*                                                                      */
  /* Как добавить новое право: только сюда. Всё остальное подхватится.    */
  /* ------------------------------------------------------------------ */
  var CAP = {
    panel: 'panel.view',
    usersView: 'users.view',
    usersEdit: 'users.edit',
    characters: 'characters.view',
    server: 'server.view',
    giveItem: 'gm.give_item',
    ban: 'gm.ban_game',
    financeView: 'finance.view',
    financeEdit: 'finance.edit',
    ticketsView: 'tickets.view',
    ticketsReply: 'tickets.reply',
    ticketsClose: 'tickets.close',
    staff: 'roles.manage',        /* раздел «Сотрудники» в RoleService — roles.manage */
    roles: 'roles.manage',
    owners: 'owner.manage',
    settings: 'settings.view',
    analytics: 'analytics.view',
    log: 'log.view'
  };

  /* Публикуем словарь прав: экраны зовут UI.can(window.DB.CAP.xxx).
     Строки выше — единственное место, где право привязано к имени. */
  window.DB.CAP = CAP;

  /* Резерв, пока серверный снимок не пришёл. Значения совпадают с
     RoleService::getMatrix() — при расхождении панель всё равно берёт
     матрицу с сервера (см. buildRoles), поэтому правки здесь не критичны. */
  var SCREEN_CAPS = {
    owner: ['*'],
    admin: [CAP.panel, CAP.analytics, CAP.ticketsView, CAP.ticketsReply, CAP.ticketsClose,
      CAP.usersView, 'users.characters', CAP.usersEdit, CAP.characters, CAP.server, 'links.view',
      CAP.giveItem, CAP.ban, 'gm.unban_game', CAP.financeView, CAP.financeEdit, 'finance.refund',
      'finance.balance', 'transfers.view', CAP.log],
    moder: [CAP.panel, CAP.analytics, CAP.ticketsView, CAP.ticketsReply, CAP.ticketsClose,
      CAP.usersView, 'users.characters', CAP.characters, CAP.server, 'links.view', CAP.log],
    gm: [CAP.panel, CAP.ticketsView, CAP.ticketsReply, CAP.usersView, 'users.characters',
      CAP.giveItem, CAP.ban, 'gm.unban_game', CAP.characters, CAP.server, 'links.view', CAP.log],
    finance: [CAP.panel, CAP.analytics, CAP.financeView, 'finance.refund', 'finance.balance',
      CAP.financeEdit, CAP.usersView, CAP.characters, CAP.server, 'transfers.view', CAP.log],
    user: []
  };

  var ROLE_TEXT = {
    owner: 'Полный доступ: владельцы, роли, настройки, деньги и журнал.',
    admin: 'Всё, кроме владельцев, ролей и настроек проекта.',
    moder: 'Игроки, тикеты, аналитика, чтение журнала. Без денег и ролей.',
    gm: 'Выдача предметов, баны и разбаны, ответы в тикетах. Без денег.',
    finance: 'Платежи, начисления и возвраты. Игровые действия недоступны.',
    user: 'Нет доступа к панели — только личный кабинет.'
  };

  var ROLE_ORDER = ['owner', 'admin', 'moder', 'gm', 'finance'];
  var ROLE_ALIASES = { player: 'user', accountant: 'finance' };

  function buildRoles() {
    var srv = window.DB.ROLES_SERVER || {};
    var list = srv.short || {};
    var ranks = srv.ranks || {};
    var notes = srv.notes || {};
    var keys = Object.keys(list);
    var out = {};

    (keys.length ? keys : ROLE_ORDER.concat(['user'])).forEach(function (k) {
      var caps = (srv.matrix && srv.matrix[k]) ? srv.matrix[k] : SCREEN_CAPS[k];
      out[k] = {
        key: k,
        name: list[k] ? (srv.list && srv.list[k]) || list[k] : (k === 'user' ? 'Игрок' : k),
        short: list[k] || (k === 'user' ? 'Игрок' : k),
        owner: k === 'owner',
        rank: ranks[k] !== undefined ? ranks[k] : 0,
        can: caps || (k === 'user' ? [] : ['panel.view']),
        cannot: (srv.denied && srv.denied[k]) || [],
        text: notes[k] || ROLE_TEXT[k] || ''
      };
    });

    if (!out.user) {
      out.user = { key: 'user', name: 'Игрок (без доступа к панели)', short: 'Игрок',
        rank: 0, can: [], cannot: ['panel.view'], text: ROLE_TEXT.user };
    }

    return out;
  }

  var ROLES = buildRoles();

  /** Любое написание ключа роли → серверное (player → user, accountant → finance). */
  function roleKey(k) {
    k = String(k || '').toLowerCase();
    return ROLE_ALIASES[k] || k;
  }

  window.DB.ROLES = ROLES;
  window.DB.roleAlias = roleKey;
  window.DB.roleOrder = ROLE_ORDER;
  window.DB.rebuildRoles = function () { window.DB.ROLES = buildRoles(); };

  /* ---------------------------------------------------------------- */
  /* Действия                                                         */
  /* ---------------------------------------------------------------- */

  window.API = {
    /* Владелец и появление снимка */
    state: load,
    pulse: function () { return raw('pulse'); },

    /* Игроки */
    playerSave: function (data) { return raw('playerSave', data); },
    playerCreate: function (data) { return raw('playerCreate', data); },
    roleSet: function (data) { return raw('roleSet', data); },
    balance: function (data) { return raw('balance', data); },
    gamePassword: function (data) { return raw('gamePassword', data); },

    /* Сервер */
    giveItem: function (data) { return raw('giveItem', data); },
    ban: function (data) { return raw('ban', data); },

    /* Тикеты */
    ticketReply: function (data) { return raw('ticketReply', data); },
    paymentSet: function (data) { return raw('paymentSet', data); },
    itemQueue: function (data) { return raw('itemQueue', data); },

    /* Владельцы */
    owner: function (data) { return raw('owner', data); },

    /* Настройки проекта (пока только просмотр — см. AdmController) */
    settingsSave: function (data) { return raw('settingsSave', data); },
    /* Самодиагностика и проверка целостности */
    diagnostic: function () { return raw('diagnostic'); }
  };
})();
