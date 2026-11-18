(function () {
  'use strict';
  const root = document.querySelector('[data-portal-root]');
  if (!root) return;
  const status = root.querySelector('[data-portal-status]');
  const notify = (text, error) => { status.hidden = false; status.textContent = text; status.classList.toggle('is-error', Boolean(error)); status.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); };
  const dialog = root.querySelector('[data-portal-dialog]');
  const targetIds = form => {
    const select = form.querySelector('[data-portal-target]');
    if (!select) return {};
    const match = select.value.match(/^([1-9][0-9]*):([1-9][0-9]*)$/);
    if (!match) throw new Error('Выберите свой аккаунт и персонажа.');
    return { link_id: match[1], char_id: match[2] };
  };
  root.querySelectorAll('[data-portal-form]').forEach(form => {
    let pending = false;
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (pending || !form.reportValidity()) return;
      if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
      let ids;
      try { ids = targetIds(form); } catch (e) { notify(e.message, true); return; }
      const body = new FormData(form);
      Object.keys(ids).forEach(key => body.set(key, ids[key]));
      const button = form.querySelector('[type="submit"]');
      const old = button.textContent;
      pending = true; button.disabled = true; button.textContent = 'Сохраняем…';
      try {
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body });
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch (_) { throw new Error('Сервер временно недоступен. Повторите запрос с тем же номером операции.'); }
        if (!response.ok || !data.ok) throw new Error(data.error || 'Не удалось завершить операцию.');
        form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        if (data.result && data.result.url && form.hasAttribute('data-payment')) {
          const url = new URL(data.result.url);
          if (url.protocol !== 'https:') throw new Error('Неверная ссылка платёжного сервиса.');
          location.assign(url.href); return;
        }
        notify(data.result && data.result.message || 'Операция выполнена.', false);
        if (form.hasAttribute('data-reload')) { location.reload(); return; }
        // Sensitive code forms remain open; mutation keys are renewed after acknowledged success only.
        const key = form.querySelector('[name="request_key"]');
        if (key && window.crypto && crypto.getRandomValues) key.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
      } catch (error) { notify(error.message || 'Ошибка сети. Повторите запрос.', true); }
      finally { pending = false; button.disabled = false; button.textContent = old; }
    });
  });
  root.querySelectorAll('[data-portal-teleport]').forEach(button => button.addEventListener('click', () => {
    if (!dialog) return;
    dialog.querySelector('[name="link_id"]').value = button.dataset.link;
    dialog.querySelector('[name="char_id"]').value = button.dataset.char;
    dialog.querySelector('[name="pin"]').value = '';
    const key = dialog.querySelector('[name="request_key"]');
    key.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
    dialog.showModal();
  }));
  root.querySelectorAll('[data-load-inventory]').forEach(button => {
    const form = button.closest('form'); const select = form.querySelector('[data-inventory-items]');
    form.querySelector('[data-portal-target]').addEventListener('change', () => { select.replaceChildren(new Option('Загрузите инвентарь персонажа', '')); });
    button.addEventListener('click', async () => {
      let ids; try { ids = targetIds(form); } catch (e) { notify(e.message, true); return; }
      const body = new FormData(form); body.set('op', 'inventory');
      for (const [k, v] of Object.entries(ids)) body.set(k, v);
      button.disabled = true;
      try {
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body });
        const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error || 'Инвентарь недоступен.');
        select.replaceChildren(new Option('Выберите предмет', ''));
        for (const item of data.result.items) select.add(new Option(`${item.name} +${item.enchant} · ${item.grade} · ×${item.quantity}${item.augmented ? ' · Аугментация' : ''}`, String(item.object_id)));
        if (!data.result.items.length) notify('Предметов, доступных для продажи, нет.', false);
      } catch (e) { notify(e.message, true); }
      finally { button.disabled = false; }
    });
  });
  if (dialog) {
    dialog.querySelector('[data-portal-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { dialog.querySelector('[name="pin"]').value = ''; });
  }
  const withdrawal = root.querySelector('[data-withdraw-amount]');
  if (withdrawal) withdrawal.addEventListener('input', () => {
    const amount = Math.round(Number(withdrawal.value) * 100);
    const fee = 300 + Math.floor((amount * 25 + 50) / 100);
    const out = root.querySelector('[data-withdraw-preview]');
    out.textContent = amount >= 1000 ? `Комиссия: ${(fee / 100).toFixed(2)} $. Получите: ${((amount - fee) / 100).toFixed(2)} USDT.` : 'Минимальная сумма: 10 $.';
  });
  const transfer = root.querySelector('[data-transfer-amount]');
  if (transfer) transfer.addEventListener('input', () => {
    const out = root.querySelector('[data-transfer-preview]');
    const rate = Number(out.dataset.rate);
    const amount = Math.round(Number(transfer.value) * 100);
    const coins = rate > 0 ? Math.floor(amount * 10000 / Math.round(rate * 10000)) / 100 : 0;
    out.textContent = rate > 0 ? `Будет начислено: ${coins.toFixed(2)} CoL.` : 'Курс временно недоступен.';
  });
})();
