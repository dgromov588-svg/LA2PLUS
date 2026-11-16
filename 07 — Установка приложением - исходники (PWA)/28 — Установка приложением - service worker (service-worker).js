/**
 * LA2PLUS • service worker для установки сайта приложением и офлайна.
 *
 * Версия 4 (16.11.2026) — улучшенное кэширование и надёжный офлайн:
 *   1. Делает сайт устанавливаемым в Chrome/Edge/Android/Samsung Internet.
 *   2. Обеспечивает мгновенный показ фирменной офлайн-страницы LA2PLUS
 *      при потере связи для любых навигационных переходов (включая кабинет).
 *   3. Безопасность данных: HTML кабинета, панели, API, токены и POST-запросы
 *      НИКОГДА не сохраняются в кэш. Все приватные данные идут строго через сеть.
 *   4. Автоматическое обновление: статика по stale-while-revalidate, манифест
 *      и worker с no-cache. При смене VERSION старые кэши удаляются на активации.
 */

var VERSION = 'v4-20261116';
var PRECACHE = 'la2plus-pre-' + VERSION;
var OFFLINE_URL = '/offline.html';

/* Предзагрузка при установке: офлайн-заглушка, иконки всех размеров и стили PWA */
var CORE = [
    OFFLINE_URL,
    '/design/assets/icons/favicon.ico',
    '/design/assets/icons/favicon-180.png',
    '/design/assets/icons/favicon-192.png',
    '/design/assets/icons/favicon-512.png',
    '/design/assets/icons/favicon-maskable-512.png',
    '/design/css/pages/pwa.css'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(PRECACHE).then(function (cache) {
            // Загружаем по одному, чтобы сбой одного ресурса (например, 404) не срывал всю установку
            return Promise.all(CORE.map(function (url) {
                return cache.add(new Request(url, { cache: 'reload' })).catch(function () {});
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            // Удаляем устаревшие кэши прошлых версий
            return Promise.all(keys.map(function (key) {
                if (key.indexOf('la2plus-') === 0 && key !== PRECACHE) {
                    return caches.delete(key);
                }
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', function (event) {
    var req = event.request;

    // Только чтение GET. POST, PUT, DELETE идут напрямую в сеть.
    if (req.method !== 'GET') return;

    var url = new URL(req.url);

    // Сторонние домены (CDN шрифтов, метрики, шлюзы) не перехватываем
    if (url.origin !== self.location.origin) return;

    // 1. Навигационные запросы (переход по страницам в браузере / приложении)
    // Всегда идут в сеть. Если связи нет — показываем брендовую страницу «Нет связи».
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // 2. Приватные данные и динамические API — строго сеть, без кэширования
    if (/^\/(admin|cabinet|adm|login|register|forgottenPassword|deposit)/.test(url.pathname)) return;
    if (url.pathname.indexOf('/api/') === 0 || url.search.indexOf('ajax=') >= 0) return;

    // 3. Статические ресурсы сайта (картинки, стили, шрифты, скрипты)
    // Стратегия stale-while-revalidate: отдаём из кэша мгновенно, в фоне обновляем.
    var staticLike = /\.(css|js|png|jpe?g|svg|webp|gif|ico|woff2?|ttf)$/i.test(url.pathname);

    if (staticLike) {
        event.respondWith(
            caches.match(req).then(function (hit) {
                var network = fetch(req).then(function (res) {
                    if (res && res.ok) {
                        var copy = res.clone();
                        caches.open(PRECACHE).then(function (cache) {
                            cache.put(req, copy);
                        });
                    }
                    return res;
                }).catch(function () {
                    return hit;
                });

                if (hit) {
                    // Фоновое обновление без прерывания отдачи из кэша
                    network.catch(function () {});
                    return hit;
                }

                return network;
            })
        );
    }
});
