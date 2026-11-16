/* ==========================================================================
   LA2PLUS — Панель управления: совместимость имён.

   Раньше этот файл был генератором демо-данных: он собирал игроков, платежи
   и тикеты из ничего и складывал их в localStorage. Из-за этого панель могла
   показать выдуманные цифры, а кнопки «работали» поверх фальшивого состояния.

   Сейчас единственный источник данных — сервер (/adm/state). Здесь остаётся
   только пустой объект, чтобы порядок подключения скриптов не ломался:
   api.js ниже наполняет его живыми данными.

   Ничего не генерируется и ничего не пишется в localStorage.
   ========================================================================== */
(function () {
  'use strict';

  window.DB = window.DB || {};

  /* Пустая заготовка: до прихода снимка состояние пустое, а не выдуманное. */
  if (!window.DB.state) {
    window.DB.state = function () {
      return {
        version: 0, ready: false, sideOpen: true, navOrder: [],
        me: { login: '', id: 0, role: 'user' },
        players: [], accounts: [], characters: [], clans: [], payments: [], walletOps: [],
        tickets: [], log: [], itemQueue: [], bans: [],
        settings: { gateways: [], bonuses: [] },
        staff: [], owners: [], roles: {}, analytics: null,
        dbSnapshot: {}, counters: {}
      };
    };
  }

  /* Сохранение и «сброс» больше не трогают браузер: данные живут на сервере. */
  if (!window.DB.save) window.DB.save = function () {};
  if (!window.DB.reset) window.DB.reset = function () { return window.DB.reload && window.DB.reload(); };
  if (!window.DB.now) window.DB.now = function () { return Date.now(); };
  if (!window.DB.superAdmins) window.DB.superAdmins = [];
})();
