(() => {
  'use strict';
  const root=document.querySelector('[data-coin-shop]');if(!root)return;
  const settings=JSON.parse(document.getElementById('coin-shop-settings').textContent);
  const amount=root.querySelector('#coin-amount'),slider=root.querySelector('#coin-slider'),pay=root.querySelector('[data-coin-pay]'),message=root.querySelector('[data-coin-message]');
  const country=root.querySelector('[data-country]'),currency=root.querySelector('select[data-currency]');
  for(const code of ['EUR','UAH','GBP','PLN','KZT','TRY','BRL','GEL','MDL','AMD','AZN','CAD','AUD','CHF','AED','INR','MXN','RON','CZK'])currency.add(new Option(code,code));
  let quote=null, sequence=0, timer, busy=false, rates={USD:1}, lastAttempt=null;
  const tell=(text,error=false)=>{message.textContent=text;message.classList.toggle('is-error',error);};
  async function request(op,coins,key) {
    const body=new URLSearchParams({op,coins,gs_id:String(settings.gs),request_key:key||settings.key,[settings.csrfName]:settings.csrfToken});
    const response=await fetch('/cabinet/portal/request/',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest',Accept:'application/json'},body});
    const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Не удалось завершить запрос.');return data.result;
  }
  function localPrice(){root.querySelector('[data-local-price]').textContent=quote&&rates[currency.value]?`Примерно ${new Intl.NumberFormat('ru-RU',{style:'currency',currency:currency.value}).format(Number(quote.usd)*rates[currency.value])}. Счёт в USD; курс банка может отличаться.`:`Курс ${currency.value} временно недоступен. Счёт рассчитывается в USD.`;}
  async function update(){
    const id=++sequence;quote=null;if(pay)pay.disabled=true;
    root.querySelector('[data-coin-total]').textContent='…';root.querySelector('[data-coin-usd]').textContent='…';
    if(!amount.checkValidity()){tell('Укажите целое количество CoL от 1 до 100000.',true);return;}
    try{
      const data=await request('coinQuote',amount.value);if(id!==sequence)return;quote=data;
      root.querySelector('[data-coin-total]').textContent=String(data.total);root.querySelector('[data-coin-usd]').textContent=data.usd;
      root.querySelector('[data-coin-bonus]').textContent=data.bonus?`В том числе бонус: ${data.bonus} CoL (+${data.percent}%)`:'Без бонуса';
      root.querySelectorAll('[data-tier]').forEach(button=>button.setAttribute('aria-pressed',Number(button.dataset.tier)<=data.coins?'true':'false'));
      tell('');localPrice();if(pay)pay.disabled=busy;
    }catch(error){if(id===sequence)tell(error.message,true);}
  }
  amount.addEventListener('input',()=>{slider.value=String(Math.min(Number(amount.value)||1,1000));++sequence;quote=null;if(pay)pay.disabled=true;clearTimeout(timer);timer=setTimeout(update,220);});
  slider.addEventListener('input',()=>{amount.value=slider.value;amount.dispatchEvent(new Event('input'));});
  root.querySelectorAll('[data-tier]').forEach(button=>button.addEventListener('click',()=>{amount.value=button.dataset.tier;amount.dispatchEvent(new Event('input'));}));
  if(pay)pay.addEventListener('click',async()=>{
    if(busy||!quote||!amount.reportValidity())return;
    // Preserve the same key and payload after an ambiguous network failure.
    const coins=String(quote.coins);if(!lastAttempt||lastAttempt.coins!==coins)lastAttempt={coins,key:Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('')};
    busy=true;pay.disabled=true;amount.disabled=true;slider.disabled=true;root.querySelectorAll('[data-tier]').forEach(b=>b.disabled=true);
    try{const result=await request('demoCoinTopup',lastAttempt.coins,lastAttempt.key);tell(result.message);lastAttempt=null;}
    catch(error){tell(error.message+' При ошибке сети повторите запрос с той же суммой.',true);}
    finally{busy=false;pay.disabled=!quote;amount.disabled=false;slider.disabled=false;root.querySelectorAll('[data-tier]').forEach(b=>b.disabled=false);}
  });
  country.addEventListener('change',()=>{const code=country.selectedOptions[0]?.dataset.currency||'USD';currency.value=code;localPrice();try{localStorage.setItem('la2plus.coin.country',country.value);}catch(_){} });
  currency.addEventListener('change',()=>{localPrice();try{localStorage.setItem('la2plus.coin.currency',currency.value);}catch(_){} });
  try{country.value=localStorage.getItem('la2plus.coin.country')||'UA';}catch(_){}
  fetch('/cabinet/deposit/currency-rates/',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.json()).then(data=>{
    const available=data.rates||data.data?.rates||{};for(const[code,rate]of Object.entries(available)){if(/^[A-Z]{3}$/.test(code)&&Number(rate)>0){rates[code]=Number(rate);if(!Array.from(currency.options).some(option=>option.value===code))currency.add(new Option(code,code));}}
    try{const saved=localStorage.getItem('la2plus.coin.currency');if(Array.from(currency.options).some(option=>option.value===saved))currency.value=saved;}catch(_){}localPrice();
  }).catch(()=>localPrice());
  update();
})();
