/* LA2PLUS Landing — Main Application */

(function () {
  'use strict';

  const cfg = LA2PLUS_CONFIG;
  // Тексты, которые рисует JS, переводит сервер: window.T = { 'ключ': 'текст' } на текущем языке (Lang::jsMessages, категория js).
  const t = (key) => (window.T && window.T[key]) || key;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isMobile = window.innerWidth <= 1024;

  // ─── BACKGROUND IMAGES ───
  function initBackgrounds() {
    document.querySelectorAll('[data-bg]').forEach((el) => {
      const url = el.dataset.bg;
      if (url) el.style.backgroundImage = `url('${url}')`;
    });
  }

  // ─── PRELOADER ───
  function initPreloader() {
    const preloader = document.getElementById('preloader');
    if (!preloader) return;
    let hidden = false;
    const hide = () => {
      // hide() вызывается несколько раз (событие load и запасной таймер), анимации нужно запустить один раз
      if (hidden) return;
      hidden = true;
      preloader.classList.add('done');
      initHeroAnimations();
    };
    if (document.readyState === 'complete') {
      setTimeout(hide, 800);
    } else {
      window.addEventListener('load', () => setTimeout(hide, 600));
    }
    setTimeout(hide, 3000);
  }

  // ─── HEADER SCROLL ───
  function initHeader() {
    const header = document.getElementById('header');
    if (!header) return;
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 60);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    const burger = document.getElementById('burgerBtn');
    const nav = document.getElementById('mainNav');
    if (!burger || !nav) return;
    burger.addEventListener('click', () => {
      burger.classList.toggle('active');
      nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', nav.classList.contains('open'));
    });
    nav.querySelectorAll('.header__link').forEach((link) => {
      link.addEventListener('click', () => {
        burger.classList.remove('active');
        nav.classList.remove('open');
      });
    });
  }

  // ─── SMOOTH SCROLL ───
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener('click', (e) => {
        const id = anchor.getAttribute('href');
        if (id === '#') return;
        const target = document.querySelector(id);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // ─── HERO VIDEO ───
  function initHeroVideo() {
    const video = document.querySelector('.hero__video');
    if (!video) return;
    video.addEventListener('error', () => { video.style.display = 'none'; });
    if (isMobile) video.style.display = 'none';
  }

  // ─── PARTICLES ───
  function initParticles() {
    if (reducedMotion) return;
    const canvas = document.getElementById('heroParticles');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let particles = [];
    const count = isMobile ? 30 : 50;
    let w = 0;
    let h = 0;

    function createParticle() {
      return {
        x: Math.random() * w,
        y: Math.random() * h,
        r: Math.random() * 2 + 0.5,
        speedX: (Math.random() - 0.5) * 0.4,
        speedY: Math.random() * -0.6 - 0.1,
        opacity: Math.random() * 0.4 + 0.15,
        color: Math.random() > 0.3 ? '#E6C382' : '#875CF6',
      };
    }

    function resize() {
      w = window.innerWidth;
      h = window.innerHeight;
      if (w < 10 || h < 10) return;

      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(w * dpr);
      canvas.height = Math.floor(h * dpr);
      canvas.style.width = w + 'px';
      canvas.style.height = h + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      if (particles.length === 0) {
        for (let i = 0; i < count; i++) particles.push(createParticle());
      } else {
        particles.forEach((p) => {
          p.x = Math.random() * w;
          p.y = Math.random() * h;
        });
      }
    }

    function draw() {
      if (!w || !h) {
        requestAnimationFrame(draw);
        return;
      }
      ctx.clearRect(0, 0, w, h);
      particles.forEach((p) => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = p.color;
        ctx.globalAlpha = p.opacity;
        ctx.fill();
        p.x += p.speedX;
        p.y += p.speedY;
        if (p.y < -10) { p.y = h + 10; p.x = Math.random() * w; }
        if (p.x < -10) p.x = w + 10;
        if (p.x > w + 10) p.x = -10;
      });
      ctx.globalAlpha = 1;
      requestAnimationFrame(draw);
    }

    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('load', () => { resize(); setTimeout(resize, 200); });
    setTimeout(resize, 100);
    draw();
  }

  // ─── GSAP ANIMATIONS ───
  function initHeroAnimations() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
      // без GSAP блоки не должны остаться скрытыми
      document.documentElement.classList.add('no-gsap');
      return;
    }

    gsap.registerPlugin(ScrollTrigger);

    const heroTitle = document.getElementById('heroTitle');
    const heroLogo = document.querySelector('.hero__logo-img');

    if (heroTitle) {
      gsap.fromTo(heroTitle,
        { opacity: 0, y: 24 },
        { opacity: 1, y: 0, duration: 0.9, ease: 'power3.out' }
      );
    }
    if (heroLogo) {
      gsap.fromTo(heroLogo,
        { opacity: 0, scale: 0.9 },
        { opacity: 1, scale: 1, duration: 1.1, delay: 0.15, ease: 'power3.out' }
      );
    }

    const reveals = document.querySelectorAll('.hero .reveal');
    gsap.to(reveals, {
      opacity: 1,
      y: 0,
      duration: 0.8,
      stagger: 0.15,
      ease: 'power3.out',
    });

    gsap.utils.toArray('.section .reveal, .rate-card, .feature-card, .howto-step, .news-card, .gameplay-card').forEach((el) => {
      gsap.fromTo(el,
        { opacity: 0, y: 40 },
        {
          opacity: 1, y: 0, duration: 0.7, ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 95%', toggleActions: 'play none none none' },
        }
      );
    });

    const worldSection = document.getElementById('world');
    if (worldSection) {
      ScrollTrigger.create({
        trigger: worldSection,
        start: 'top 60%',
        onEnter: () => worldSection.classList.add('in-view'),
      });
    }

    if (!reducedMotion) {
      const heroLight = document.querySelector('.hero__light');
      if (heroLight) {
        gsap.to(heroLight, {
          opacity: 0.7,
          duration: 3,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });
      }
    }
  }

  // ─── COUNTDOWN ───
  function initCountdown() {
    const opening = new Date(cfg.server.openingDate);
    const countdownEl = document.getElementById('countdown');
    const onlineBadge = document.getElementById('serverOnlineBadge');
    if (!countdownEl) return;

    function update() {
      const now = new Date();
      const diff = opening - now;

      if (diff <= 0) {
        countdownEl.classList.add('hidden');
        onlineBadge.classList.remove('hidden');
        return;
      }

      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);

      document.getElementById('cdDays').textContent = String(d).padStart(2, '0');
      document.getElementById('cdHours').textContent = String(h).padStart(2, '0');
      document.getElementById('cdMinutes').textContent = String(m).padStart(2, '0');
      document.getElementById('cdSeconds').textContent = String(s).padStart(2, '0');
    }

    update();
    setInterval(update, 1000);
  }

  // ─── RENDER DYNAMIC CONTENT ───
  window.renderDynamicContent = function () {
    const ratesGrid = document.getElementById('ratesGrid');
    if (ratesGrid) {
      ratesGrid.innerHTML = cfg.rates.map((r) => `
        <div class="rate-card reveal">
          <div class="rate-card__icon">${r.icon}</div>
          <div class="rate-card__value">${r.value}</div>
          <div class="rate-card__label">${t('rates.' + r.key)}</div>
        </div>
      `).join('');
    }

    const featuresGrid = document.getElementById('featuresGrid');
    if (featuresGrid) {
      featuresGrid.innerHTML = cfg.features.map((f) => `
        <div class="feature-card reveal">
          <div class="feature-card__icon">${f.icon}</div>
          <h3>${t(f.titleKey)}</h3>
          <p>${t(f.descKey)}</p>
        </div>
      `).join('');
    }

    const newsGrid = document.getElementById('newsGrid');
    if (newsGrid) {
      newsGrid.innerHTML = cfg.news.map((n) => `
        <article class="news-card reveal">
          <div class="news-card__image" style="background-image: url('${n.image}')"></div>
          <div class="news-card__body">
            <div class="news-card__date">${n.date}</div>
            <h3>${t(n.titleKey)}</h3>
            <p>${t(n.descKey)}</p>
          </div>
        </article>
      `).join('');
    }

    document.querySelectorAll('[data-config]').forEach((el) => {
      const key = el.dataset.config;
      if (cfg.server[key]) el.textContent = cfg.server[key].toUpperCase?.() || cfg.server[key];
    });
  };

  // ─── LINKS ───
  function initLinks() {
    // Ссылка уже проставлена на сервере (шаблон), но конфиг может её переопределить.
    // Пустое значение в конфиге НЕ затирает рабочую ссылку и скрывает кнопку.
    const setHref = (id, url) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (url && url !== '#') {
        el.href = url;
        el.style.display = '';
      } else if (!el.getAttribute('href') || el.getAttribute('href') === '#') {
        el.style.display = 'none';
      }
    };
    setHref('discordBtn', cfg.links.discord);
    setHref('telegramBtn', cfg.links.telegram);
    setHref('youtubeBtn', cfg.links.youtube);
    setHref('headerDiscord', cfg.links.discord);
    setHref('headerTelegram', cfg.links.telegram);
    setHref('footerDiscord', cfg.links.discord);
    setHref('footerTelegram', cfg.links.telegram);

    document.querySelectorAll('[data-download]').forEach((el) => {
      const type = el.dataset.download;
      if (cfg.links.downloads[type]) el.href = cfg.links.downloads[type];
      el.addEventListener('click', () => trackEvent('download_' + type));
    });

    document.querySelectorAll('[data-mirror]').forEach((el) => {
      const mirror = el.dataset.mirror;
      if (cfg.links.downloads[mirror]) el.href = cfg.links.downloads[mirror];
    });



  }

  // ─── LANGUAGE ───
  function initLanguage() {
    const dd = document.querySelector('.lang-dd');
    if (!dd) return;
    const btn = dd.querySelector('.lang-dd__btn');
    const close = () => { dd.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); };
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      btn.setAttribute('aria-expanded', dd.classList.toggle('open'));
    });
    document.addEventListener('click', close);
    // язык хранится в cookie, страницу переводит сервер (Lang::apply)
    dd.querySelectorAll('.lang-dd__item').forEach((item) => {
      item.addEventListener('click', () => {
        document.cookie = 'lang=' + item.dataset.lang + '; path=/; max-age=31536000; samesite=lax';
        location.reload();
      });
    });
  }

  // Дата открытия из LA2PLUS_CONFIG.server.openingDate (Москва, GMT+3); язык — из <html lang>, который выставляет сервер.
  function renderOpeningDate() {
    const el = document.getElementById('openingDate');
    if (!el) return;
    const d = new Date(cfg.server.openingDate);
    const opts = { timeZone: 'Europe/Moscow' };
    const loc = { ru: 'ru-RU', en: 'en-GB', uk: 'uk-UA' }[document.documentElement.lang] || 'ru-RU';
    const date = d.toLocaleDateString(loc, { ...opts, day: 'numeric', month: 'long' });
    const time = d.toLocaleTimeString('ru-RU', { ...opts, hour: '2-digit', minute: '2-digit' });
    el.textContent = `${date} • ${time} (GMT+3)`.toUpperCase();
  }

  // ─── ANALYTICS STUB ───
  function trackEvent(name) {
    if (typeof gtag === 'function') gtag('event', name);
    if (typeof dataLayer !== 'undefined') dataLayer.push({ event: name });
    console.log('[Analytics]', name);
  }

  document.getElementById('discordBtn')?.addEventListener('click', () => trackEvent('discord_click'));
  document.getElementById('telegramBtn')?.addEventListener('click', () => trackEvent('telegram_click'));

  // ─── TILT EFFECT ───
  function initTilt() {
    if (reducedMotion || isMobile) return;
    document.querySelectorAll('[data-tilt]').forEach((card) => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        card.style.transform = `perspective(800px) rotateY(${x * 6}deg) rotateX(${-y * 6}deg)`;
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.transition = 'transform 0.5s ease';
      });
      card.addEventListener('mouseenter', () => { card.style.transition = ''; });
    });
  }

  // ─── POPUPS ───
  function openModal(name) {
    const modal = document.getElementById('modal-' + name);
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('modal-open');
  }
  function closeModals() {
    document.querySelectorAll('.modal').forEach((m) => { m.hidden = true; });
    document.body.classList.remove('modal-open');
  }
  function initModals() {
    document.querySelectorAll('[data-modal]').forEach((el) => {
      // preventDefault: пункт может быть ссылкой (например, «Выход» в кабинете), без JS она работает как обычно
      el.addEventListener('click', (e) => { e.preventDefault(); openModal(el.dataset.modal); });
    });
    document.querySelectorAll('[data-modal-close]').forEach((el) => el.addEventListener('click', closeModals));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModals(); });
  }

  // ─── TOAST ───
  function toast(text, type = 'success') {
    const box = document.getElementById('toastContainer');
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = text;
    box.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  }

  // ─── "ДОБАВИТЬ СТРИМ" FORM ───
  // shortcut: nothing is sent yet, only validated; wire to a Telegram-bot endpoint (with CSRF) when the token is available
  function initStreamForm() {
    const form = document.getElementById('streamForm');
    if (!form) return;
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      toast(t('feedback.sent'));
      form.reset();
      closeModals();
    });
  }

  // ─── STREAM CARDS ───
  function initStreams() {
    const grid = document.getElementById('streamsGrid');
    if (!grid) return;
    const ytId = (url) => new URL(url).searchParams.get('v');
    grid.innerHTML = cfg.streams.map((s) => {
      const thumb = s.platform === 'youtube'
        ? `style="background-image:url('https://img.youtube.com/vi/${ytId(s.url)}/hqdefault.jpg')"` : '';
      return `
        <a class="stream-card stream-card--${s.platform}" href="${s.url}" target="_blank" rel="noopener">
          <span class="stream-card__thumb" ${thumb}></span>
          <span class="stream-card__play" aria-hidden="true"></span>
          <span class="stream-card__title">${s.title}</span>
          <span class="stream-card__platform">${s.platform}</span>
        </a>`;
    }).join('');
  }

  // ─── RATING ───
  // Компонент «Рейтинг» (components/rating.php): корень [data-rating-root], число строк в data-limit.
  // Данные: cfg.ratings. Вкладки и таблицы рисуются здесь, заголовки переводятся через t().
  // Каждый корень переключается отдельно: лендинг и /rating/ не мешают друг другу.
  const RATING_TABS = ['pvp', 'pk', 'exp', 'clans', 'clanpvp', 'alliances', 'castles'];
  const RATING_HASHES = { pvp: 'tab-top_pvp', pk: 'tab-top_pk', exp: 'tab-top_exp', clans: 'tab-top_clan', clanpvp: 'tab-top_clan_pvp', alliances: 'tab-top_ally', castles: 'tab-top_castle' };
  const ratingLang = document.documentElement.lang.startsWith('en') ? 'en' : document.documentElement.lang.startsWith('uk') ? 'uk' : 'ru';
  const ratingWords = {
    ru: { clanpvp:'Клановый PvP',alliances:'Альянсы',alliance:'Альянс',hall:'Кланхолл',leader:'Лидер',attackers:'Атакующие',defenders:'Защитники',updated:'Обновлено',clanCount:'Кланы',picture:'Изображение',info:'Информация' },
    en: { clanpvp:'Clan PvP',alliances:'Alliances',alliance:'Alliance',hall:'Clan hall',leader:'Leader',attackers:'Attackers',defenders:'Defenders',updated:'Updated',clanCount:'Clans',picture:'Image',info:'Information' },
    uk: { clanpvp:'Клановий PvP',alliances:'Альянси',alliance:'Альянс',hall:'Кланхол',leader:'Лідер',attackers:'Атакувальники',defenders:'Захисники',updated:'Оновлено',clanCount:'Клани',picture:'Зображення',info:'Інформація' }
  };
  const rt = key => ratingWords[ratingLang][key] || t('rating.' + key);
  // по какому полю сортируется общий список игроков на вкладке
  const PLAYER_SORT = { pvp: 'pvp', pk: 'pk', exp: 'level' };

  const ratingHead = (keys) => '<thead><tr>' + keys.map((k) => `<th>${rt(k)}</th>`).join('') + '</tr></thead>';
  const ratingBody = (rows, cols) => '<tbody>' + (rows.length ? rows.join('') : `<tr><td colspan="${cols}">${t('rating.empty')}</td></tr>`) + '</tbody>';

  let ratingResult = null;
  let ratingGeneration = 0;
  const escapeRating = value => String(value ?? '—').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  function ratingTable(tab, limit, full = false) {
    if (!ratingResult || !ratingResult.ok || ratingResult.states[tab] !== 'ok') {
      const message = !ratingResult ? t('rating.loading') : !ratingResult.ok ? t('rating.unavailable') : ratingResult.states[tab] === 'disabled' ? t('rating.disabled') : t('rating.unavailable');
      return '<tbody><tr><td>' + message + '</td></tr></tbody>';
    }
    const r = ratingResult.data;
    if (PLAYER_SORT[tab]) {
      const rows = r[tab].slice(0, limit).map((p, i) => `<tr>
        <td><span class="rank rank--${Math.min(i + 1, 4)}" data-n="${i + 1}"></span></td>
        <td class="rating__char">${escapeRating(p.name)}</td><td>${escapeRating(p.class)}</td><td>${escapeRating(p.clan)}</td><td>${escapeRating(p.level)}</td>
        ${tab === 'exp' ? `<td class="rating__score">${escapeRating(p.exp)}</td>` : ''}<td class="rating__score">${escapeRating(p.pvp)} / ${escapeRating(p.pk)}</td><td>${escapeRating(p.time)}</td>
        <td><span class="dot ${p.online ? 'dot--on' : 'dot--off'}"></span></td></tr>`);
      return ratingHead(['id', 'name', 'class', 'clan', 'level', ...(tab === 'exp' ? ['experience'] : []), 'pvppk', 'time', 'online']) + ratingBody(rows, tab === 'exp' ? 9 : 8);
    }
    if (tab === 'clans' || tab === 'clanpvp') {
      const rows = r[tab].slice(0, limit).map((c, i) => `<tr>
        <td><span class="rank rank--${Math.min(i + 1, 4)}" data-n="${i + 1}"></span></td>
        <td class="rating__char">${escapeRating(c.name)}</td>${full ? `<td>${escapeRating(c.alliance)}</td><td>${escapeRating(c.hall)}</td>` : ''}<td>${escapeRating(c.level)}</td><td>${escapeRating(c.castle)}</td><td>${escapeRating(c.members)}</td>
        <td class="rating__score">${escapeRating(c.reputation)}</td>${full ? `<td>${escapeRating(c.pvp)}</td>` : ''}</tr>`);
      return ratingHead(['num', 'clanName', ...(full ? ['alliance','hall'] : []), 'level', 'castle', 'players', 'reputation', ...(full ? ['pvp'] : [])]) + ratingBody(rows, full ? 9 : 6);
    }
    if (tab === 'alliances') {
      const rows = r.alliances.slice(0, limit).map((a, i) => `<tr><td>${i+1}</td><td class="rating__char">${escapeRating(a.name)}</td><td>${escapeRating(a.clans)}</td><td>${escapeRating(a.members)}</td><td>${escapeRating(a.reputation)}</td><td>${escapeRating(a.pvp)}</td></tr>`);
      return ratingHead(['num','alliance','clanCount','players','reputation','pvp']) + ratingBody(rows,6);
    }
    if (full) {
      const rows = r.castles.map((c, i) => `<tr><td>${i+1}</td><td><img class="rating-castle-image" src="/images/castles/${Number(c.id)}.jpg" alt="${escapeRating(c.castle)}" loading="lazy"></td><td class="rating__char">${escapeRating(c.castle)}</td><td><div>${rt('tax')}: ${escapeRating(c.tax)}%</div><div>${rt('clanName')}: ${escapeRating(c.owner)}</div><div>${rt('leader')}: ${escapeRating(c.leader)}</div></td><td><span class="rating-siege-date">${escapeRating(c.siege)}</span><div>${rt('attackers')}: ${escapeRating((c.attackers || []).join(', ') || '—')}</div><div>${rt('defenders')}: ${escapeRating((c.defenders || []).join(', ') || '—')}</div></td></tr>`);
      return ratingHead(['num','picture','castle','info','siege']) + ratingBody(rows,5);
    }
    const rows = r.castles.slice(0, limit).map((c) => `<tr>
      <td class="rating__char">${escapeRating(c.castle)}</td><td>${escapeRating(c.owner)}</td><td>${escapeRating(c.tax)}%</td><td>${escapeRating(c.siege)}</td></tr>`);
    return ratingHead(['castle', 'owner', 'tax', 'siege']) + ratingBody(rows, 4);
  }

  async function loadLiveRatings() {
    const generation = ++ratingGeneration;
    const meta = document.querySelector('meta[name="la2plus-ratings-url"]');
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      if (!meta) throw new Error('No endpoint');
      const url = new URL(meta.content, location.origin);
      const server = document.querySelector('[data-rating-server]');
      if (server && server.value) url.searchParams.set('gs_id', server.value);
      const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('HTTP error');
      const payload = await response.json();
      if (generation !== ratingGeneration) return;
      if (payload.ok && (!payload.states || !payload.data || !RATING_TABS.every((key) => Array.isArray(payload.data[key])))) throw new Error('Invalid data');
      ratingResult = payload;
    } catch (_) { if (generation !== ratingGeneration) return; ratingResult = { ok: false }; }
    finally { clearTimeout(timer); }
    if (generation !== ratingGeneration) return;
    document.querySelectorAll('[data-rating-root]').forEach(root => {
      root.querySelectorAll('[data-rating-table]').forEach(table => {
        table.innerHTML = ratingTable(table.dataset.ratingTable, Number(root.dataset.limit) || 5, root.hasAttribute('data-rating-full'));
      });
      const stamp = root.querySelector('[data-rating-updated]');
      if (stamp) stamp.textContent = ratingResult && ratingResult.ok ? rt('updated') + ': ' + new Date(ratingResult.updatedAt).toLocaleString() : '';
    });
  }
  function initRating() {
    document.querySelectorAll('[data-rating-root]').forEach((root) => {
      const limit = Number(root.dataset.limit) || 5;
      const full = root.hasAttribute('data-rating-full');
      const keys = full ? RATING_TABS : ['pvp', 'pk', 'exp', 'clans', 'castles'];
      root.innerHTML = `
        <div class="rating__tabs" role="tablist">${keys.map((key, i) =>
          `<button type="button" class="rating__tab${i ? '' : ' active'}" role="tab" aria-selected="${!i}" tabindex="${i ? -1 : 0}" data-rating="${key}">${rt(key)}</button>`).join('')}</div>
        <div class="rating__panel">${keys.map((key, i) =>
          `<table class="rating__table${i ? ' hidden' : ''}" data-rating-table="${key}">${ratingTable(key, limit, full)}</table>`).join('')}</div><p class="rating-updated" data-rating-updated aria-live="polite"></p>`;

      const tabs = root.querySelectorAll('.rating__tab');
      const tables = root.querySelectorAll('[data-rating-table]');
      tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          tabs.forEach((x) => x.classList.toggle('active', x === tab));
          tabs.forEach(x => { x.setAttribute('aria-selected', String(x === tab)); x.tabIndex = x === tab ? 0 : -1; });
          tables.forEach((tbl) => {
            tbl.classList.toggle('hidden', tbl.dataset.ratingTable !== tab.dataset.rating);
          });
          if (full) history.replaceState(null, '', '#' + RATING_HASHES[tab.dataset.rating]);
        });
        tab.addEventListener('keydown', e => {
          if (!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return;
          e.preventDefault(); const list = Array.from(tabs); const i = list.indexOf(tab);
          const next = e.key === 'Home' ? 0 : e.key === 'End' ? list.length-1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + list.length) % list.length;
          list[next].click(); list[next].focus();
        });
      });
      if (full) {
        const selectHash = () => { const key = keys.find(k => '#' + RATING_HASHES[k] === location.hash); if (key) root.querySelector(`[data-rating="${key}"]`).click(); };
        selectHash(); window.addEventListener('hashchange', selectHash);
      }
    });
    const selector = document.querySelector('[data-rating-server]');
    if (selector) selector.addEventListener('change', loadLiveRatings);
  }

  // ─── SCROLL-SPY (вики и якоря главной) ───
  // mark — элемент, которому ставится .active (для пунктов меню это li, а не ссылка)
  function spy(links, getTarget, mark = (l) => l) {
    if (!links.length || !('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (!en.isIntersecting) return;
        links.forEach((l) => mark(l).classList.toggle('active', getTarget(l) === en.target));
      });
      syncMenuToggle();
    }, { rootMargin: '-40% 0px -55% 0px' });
    links.forEach((l) => { const t = getTarget(l); if (t) io.observe(t); });
  }
  function initSpy() {
    spy(document.querySelectorAll('.side-nav__link'), (l) => document.querySelector(l.getAttribute('href')));
    spy(document.querySelectorAll('.menu a[data-wiki]'), (l) => document.getElementById(l.dataset.wiki), (l) => l.closest('li'));
  }

  // ─── MENU TOGGLE ───
  // подпись переключателя — активный пункт: раздел кабинета (его отмечает CMenu) или раздел вики (scroll-spy)
  function syncMenuToggle() {
    const a = document.querySelector('.menu li.active > a');
    const label = document.querySelector('.menu__toggle');
    if (a && label) label.innerHTML = a.innerHTML;
  }
  function initMenuToggle() {
    syncMenuToggle();
    // переход по разделу вики — якорь на этой же странице, после клика список сворачиваем
    // на лендинге ни переключателя, ни ссылок .menu a[data-wiki] нет: цикл пустой
    const check = document.getElementById('cmsMenuToggle');
    document.querySelectorAll('.menu a[data-wiki]').forEach((l) => l.addEventListener('click', () => { check.checked = false; }));
  }

  // ─── INIT ───
  function init() {
    initBackgrounds();
    initPreloader();
    initHeader();
    initSmoothScroll();

    initParticles();
    initCountdown();


    renderDynamicContent();
    initLinks();



    initStreams();
    initRating();
    if (document.querySelector('[data-rating-root]')) loadLiveRatings();
    initSpy();
    initMenuToggle();
    initModals();
    initStreamForm();
    initLanguage();
    renderOpeningDate();
    initTilt();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
