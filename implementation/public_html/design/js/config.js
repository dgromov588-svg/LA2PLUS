const LA2PLUS_CONFIG = {
  server: {
    name: 'LA2PLUS',
    chronicle: 'Interlude',
    openingDate: '2026-12-11T20:00:00+03:00',
  },
  links: {
    discord: '', // Discord пока не создан — пустая строка скрывает кнопку
    telegram: 'https://t.me/la2pllus', // наша группа в Telegram
    downloads: {
      // Полный клиент (13,7 ГБ) — хостинг сайта, прямая ссылка
      'client.interlude': 'https://la2plus.com/downloads/clients/la2plus-client.zip',
      'client.classic': 'https://la2plus.com/downloads/clients/la2plus-client.zip',
      // Лаунчер Windows: установка, проверка файлов и обновления проекта
      'updater.interlude': 'https://la2plus.com/downloads/LA2PLUS-Setup-1.1.1.exe',
      'updater.classic': 'https://la2plus.com/downloads/LA2PLUS-Setup-1.1.1.exe',
      'launcher.windows': 'https://la2plus.com/downloads/LA2PLUS-Setup-1.1.1.exe',
      'launcher.windows.drive': 'https://drive.google.com/file/d/1-rBSOtQ7DasCraoN8hFunruLjJbCRQUf/view?usp=drive_link',
      'launcher.windows.patch': 'https://la2plus.com/downloads/LA2PLUS-Setup-1.1.1.exe',
      'launcher.macIntel': '/downloads/clients/L2PLUS-Client-1.1.1-mac-x64.dmg',
      'launcher.macArm': '/downloads/clients/L2PLUS-Client-1.1.1-mac-arm64.dmg',
    },
  },
  ratings: { pvp: [], pk: [], exp: [], clans: [], castles: [] },
  streams: [
    { platform: 'youtube', title: 'Coming soon', url: 'https://www.youtube.com/' },
    { platform: 'twitch', title: 'Coming soon', url: 'https://www.twitch.tv/' },
    { platform: 'kick', title: 'Coming soon', url: 'https://kick.com/' },
    { platform: 'tiktok', title: 'Coming soon', url: 'https://www.tiktok.com/' },
  ],
};