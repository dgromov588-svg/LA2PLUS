<?php
/** @var PortalController $this @var PortalStore $store @var array $accounts @var string $page */
$this->pageTitle='LA2PLUS — '.PortalRuntime::translate('Личный кабинет','Player account','Особистий кабінет');
$sections=PortalRuntime::sections();
$uid=PortalRuntime::uid(); $gs=PortalRuntime::gs(); $tr=['PortalRuntime','translate'];
$e=function ($value) { return CHtml::encode((string)$value); };
$literal=function ($value) use ($e) { return '<span data-portal-literal>'.$e($value).'</span>'; };
$url=function ($action) { return $this->createUrl('/cabinet/portal/'.$action); };
$csrf='<input type="hidden" name="'.$e(request()->csrfTokenName).'" value="'.$e(request()->getCsrfToken()).'">';
$hidden=function ($name,$value) use ($e) { return '<input type="hidden" name="'.$e($name).'" value="'.$e($value).'">'; };
$field=function ($name,$label,$type='text',$extra='') use ($e) { return '<label class="portal-field"><span>'.$e($label).'</span><input name="'.$e($name).'" type="'.$e($type).'" required '.$extra.'></label>'; };
$form=function ($op,$body,$label,$attributes='') use ($e,$url,$csrf,$hidden) {
    return '<form class="portal-form" data-portal-form '.$attributes.' method="post" action="'.$e($url('request')).'">'.$csrf.$hidden('op',$op).$hidden('request_key',PortalRuntime::requestKey()).$body.'<button class="btn btn--gold" type="submit">'.$e($label).'</button></form>';
};
$wallet=$store->wallet($uid); $security=new PortalSecurity($store);
$verified=$security->isVerified($uid);
$section=function ($title) use ($e) { return '<h2 class="portal-heading">'.$e($title).'</h2>'; };
ob_start();
?>
<div class="portal portal--atelier" data-portal-root>

  <header class="portal-hero">
    <div class="portal-hero-copy"><span class="portal-eyebrow">LA2PLUS · <?php echo $e($tr('ИГРОВОЙ ЦЕНТР','PLAYER HUB','ІГРОВИЙ ЦЕНТР')); ?></span>
    <?php $titles=['home'=>['Твой мир. Твои правила.','Your world. Your rules.','Твій світ. Твої правила.'],'warehouse'=>['Твой личный арсенал','Your personal arsenal','Твій особистий арсенал'],'exchange'=>['Место выгодных сделок','A place for great trades','Місце вигідних угод'],'security'=>['Всё под твоим контролем','Stay in control','Усе під твоїм контролем'],'admin'=>['Управляй своим миром','Manage your world','Керуй своїм світом']]; ?>
    <h1><?php echo $e($tr(...($titles[$page]??(isset(CabinetSections::catalog()[$page])?CabinetSections::catalog()[$page]['label']:($page==='createAccount'?['Новый игровой аккаунт','New game account','Новий ігровий акаунт']:$titles['home']))))); ?></h1>
    <p><?php echo $e($tr('Персонажи, достижения и возможности — в одном месте.','Characters, progress and possibilities — all in one place.','Персонажі, досягнення та можливості — в одному місці.')); ?></p>
    <div class="portal-hero-meta"><span class="portal-server-pill"><i aria-hidden="true"></i> Interlude <b>×20</b></span><span><?php echo $e($tr('С возвращением','Welcome back','З поверненням')); ?>, <?php echo $literal(user()->get('login')); ?></span></div></div>
    <div class="portal-hero-sigil" aria-hidden="true"><?php echo BrandLogo::html('display',true); ?></div>
  </header>
  <div class="portal-status" data-portal-status role="status" aria-live="polite" hidden></div>
  <?php if (!$verified) { ?>
  <aside class="portal-notice"><strong><?php echo $e($tr('Подтвердите почту','Verify your email','Підтвердьте пошту')); ?></strong><p><?php echo $e($tr('Для защиты операций подтвердите адрес электронной почты.','Verify your email to protect account operations.','Для захисту операцій підтвердьте пошту.')); ?></p><a class="btn btn--gold btn--sm" href="<?php echo $e($url('security')); ?>"> <?php echo $e($tr('Подтвердить почту','Verify email','Підтвердити пошту')); ?></a></aside>
  <?php } ?>
  <div class="portal-overview">
    <section class="portal-card" data-portal-card="profile"><small><?php echo $e($tr('Профиль','Profile','Профіль')); ?></small><strong><?php echo $literal(user()->get('email')); ?></strong><span><?php echo $literal(user()->get('login')); ?></span><span><?php echo $e($tr('Последний вход','Last login','Останній вхід')); ?>: <?php echo $e(PortalRuntime::lastLogin()); ?></span></section>
    <section class="portal-card" data-portal-card="balance"><small>Баланс кабинета</small><strong data-balance-value><?php echo $e(formatCurrency(WalletService::getBalance($uid),false)); ?> CoL</strong><div class="portal-balance-actions">
    <?php if ($sections->enabled('deposit') && $sections->enabled('warehouse') && PortalRuntime::gameReady()) { ?><button type="button" data-open-coins>⇄ Перевести в игру</button><?php } ?>
    <?php if ($sections->enabled('deposit')) { ?><a href="/cabinet/deposit/">⊕ Пополнить</a><?php } ?>
    <button type="button" data-refresh-balance data-csrf-name="<?php echo $e(request()->csrfTokenName); ?>" data-csrf-value="<?php echo $e(request()->getCsrfToken()); ?>">↻ Обновить</button></div></section>
    <?php if ($sections->enabled('exchange')) { ?>
    <section class="portal-card" data-portal-card="exchange-wallet"><small><?php echo $e($tr('Кошелёк биржи','Exchange wallet','Гаманець біржі')); ?></small><strong><?php echo $e(PortalMoney::format($wallet['available'])); ?> $</strong><span><?php echo $e(PortalMoney::format($wallet['reserved'])); ?> $ <?php echo $e($tr('в резерве','reserved','у резерві')); ?></span></section>
    <?php } ?>
  </div>

<?php if ($page==='home') { ?>
  <?php $target=user()->getState('portal.depositTarget'); if ($sections->enabled('deposit') && $sections->enabled('warehouse') && is_array($target) && (int)$target['gs_id']===$gs && time()-(int)$target['created_at']<86400) {
    try { $targetChar=PortalRuntime::character($target['link_id'],$target['char_id']); } catch (Throwable $error) { $targetChar=null; }
    if ($targetChar) { ?>
    <section class="portal-card"><h2>Перевести CoL: <?php echo $literal($targetChar['char_name']); ?></h2><p>Баланс кабинета: <?php echo $e(formatCurrency(WalletService::getBalance($uid),false)); ?> CoL. Перевод выполняется только после вашего подтверждения.</p>
    <?php if ($store->enabled('warehouse') && PortalRuntime::gameReady() && (int)$store->setting('col_item_id')>0) echo $form('coins',$hidden('link_id',$target['link_id']).$hidden('char_id',$target['char_id']).$field('coins','Количество CoL','number','min="1" max="1000000" step="1"'),'Перевести в игру','data-confirm="Подтвердить списание CoL с баланса кабинета и перевод выбранному персонажу?" data-reload');
    else { ?><p class="portal-muted">Перевод в игру временно недоступен. Монеты остаются на вашем балансе кабинета.</p><?php } ?>
    </section>
  <?php } } ?>
  <div class="portal-grid">
  <?php if ($sections->enabled('accounts')) { ?>
  <main class="portal-card portal-accounts">
    <header class="portal-card-header"><div><small>LA2PLUS · INTERLUDE</small><h2><?php echo $e($tr('Игровые аккаунты','Game accounts','Ігрові акаунти')); ?></h2></div><a class="btn btn--gold btn--sm" href="/cabinet/portal/createAccount/">+ <?php echo $e($tr('Создать аккаунт','Create account','Створити акаунт')); ?> (<?php echo count($accounts); ?>/10)</a></header>
    <?php $hiddenIds=array_map('intval',$store->query('SELECT link_id FROM {{portal_hidden_accounts}} WHERE user_id=?',[$uid])->fetchAll(PDO::FETCH_COLUMN)); $showHidden=request()->getQuery('hidden')==='1'; ?>
    <a class="portal-muted" href="<?php echo $e($url('index').($showHidden?'':'?hidden=1')); ?>"><?php echo $showHidden?'Скрыть скрытые аккаунты':'Показать скрытые аккаунты'; ?></a>
    <?php if (!$accounts) { ?><p class="portal-empty">Игровых аккаунтов пока нет. Создайте аккаунт, чтобы ваши персонажи появились здесь.</p><a href="/cabinet/portal/createAccount/">Создать аккаунт</a><?php } ?>
    <?php foreach ($accounts as $account) { $isHidden=in_array((int)$account['id'],$hiddenIds,true); if ($isHidden && !$showHidden) continue; ?>
    <details class="portal-account" open>
      <summary><?php echo $literal($account['game_login']); ?><?php if ($isHidden) { ?> <small>скрыт</small><?php } ?></summary>
      <div class="portal-account-body">
      <?php try { $characters=PortalRuntime::characters($account['id']); } catch (Throwable $error) { $characters=null; } ?>
      <?php if ($characters===null) { ?><p class="portal-empty">Не удалось загрузить персонажей. Попробуйте обновить страницу позже.</p><?php } else { ?>
      <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Персонаж</th><th>Уровень</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
      <?php foreach ($characters as $char) { ?>
      <tr><td><?php echo $literal($char['char_name']); ?></td><td><?php echo (int)$char['level']; ?></td><td><span class="portal-dot <?php echo $char['online']?'is-online':''; ?>"></span><?php echo $char['online']?'Онлайн':'Офлайн'; ?></td><td class="portal-actions">
        <button type="button" class="btn btn--sm" data-portal-teleport data-link="<?php echo (int)$account['id']; ?>" data-char="<?php echo (int)$char['char_id']; ?>" <?php if ($char['online']) echo 'disabled'; ?>>В город</button>
        <a class="btn btn--sm" href="<?php echo $e($url('security').'?link_id='.(int)$account['id']); ?>">Пароль аккаунта</a>
        <?php if ($sections->enabled('deposit')) { ?>
        <a class="btn btn--sm" href="/cabinet/deposit/?target_link_id=<?php echo (int)$account['id']; ?>&amp;target_char_id=<?php echo (int)$char['char_id']; ?>">Купить CoL</a>
        <?php } ?>
      </td></tr>
      <?php } ?>
      <?php if (!$characters) { ?><tr><td colspan="4">Персонажей пока нет</td></tr><?php } ?>
      </tbody></table></div>
      <?php } ?>
      <div class="portal-account-footer"><a class="btn btn--sm" href="<?php echo $e($url('security').'?link_id='.(int)$account['id']); ?>">Сменить / восстановить пароль</a>
      <?php echo $form('hide',$hidden('link_id',$account['id']).$hidden('hidden',$isHidden?0:1),$isHidden?'Показать аккаунт':'Скрыть аккаунт','data-reload'); ?>
      </div></div>
    </details>
    <?php } ?>
    <a class="portal-refresh" href="<?php echo $e($url('index')); ?>">↻ Обновить список персонажей</a>
  </main>
  <?php } else { ?><section class="portal-card"><h2>Личный кабинет</h2><p>Доступные разделы находятся в меню. Безопасность аккаунта всегда доступна.</p></section><?php } ?>
  <aside class="portal-sidebar">
    <?php if ($sections->enabled('referrals')) { ?>
    <section class="portal-card portal-referrals"><span class="portal-symbol">♧</span><h2>Пригласить друга</h2><?php if (config('referral_program.allow')) { ?><strong><?php echo count(Referals::getByUserId($uid)); ?> реферал(ов)</strong><p>Приглашайте друзей по вашей ссылке и получайте награды по правилам реферальной программы LA2PLUS.</p><a class="btn" href="/cabinet/portal/referrals/">Мои рефералы</a><?php } else { ?><p>Реферальная программа пока недоступна.</p><?php } ?></section>
    <?php } ?>
    <?php if ($sections->enabled('bonuses')) { ?>
    <section class="portal-card"><h2>Бонус-код</h2><p>Активируйте промокод проекта.</p><a class="btn" href="/cabinet/portal/bonuses/">Ввести код</a></section>
    <?php } ?>
    <?php if ($sections->enabled('calendar') || $sections->enabled('support') || $sections->enabled('history')) { ?><section class="portal-card"><h2>Полезные разделы</h2>
    <?php if ($sections->enabled('calendar')) { ?><a href="/cabinet/portal/calendar/">Календарь наград</a><?php } ?>
    <?php if ($sections->enabled('support')) { ?><a href="/cabinet/portal/support/">Обратиться в поддержку</a><?php } ?>
    <?php if ($sections->enabled('history')) { ?><a href="/cabinet/portal/history/">История пополнений</a><?php } ?>
    </section><?php } ?>

  </aside></div>
<?php } elseif ($page==='security') { ?>
  <?php echo $section('Настройки безопасности'); ?><div class="portal-actions"><a class="btn" href="/cabinet/portal/logins/">История входов</a></div>
  <section class="portal-card"><h2>Подтверждение почты</h2><p>Код действует 10 минут. Повторная отправка — через минуту, до 5 писем в час.</p>
    <?php echo $form('code',$hidden('purpose','verify_email').$field('current_password','Текущий пароль','password','autocomplete="current-password"'),'Получить код'); ?>
    <?php echo $form('verifyEmail',$field('code','Код из письма','text','inputmode="numeric" pattern="[0-9]{6}"'),'Подтвердить почту','data-reload'); ?>
  </section>
  <section class="portal-card"><h2>Смена и восстановление пароля игрового аккаунта</h2>
    <?php $requested=(int)request()->getQuery('link_id'); foreach ($accounts as $a) { if ($requested && $requested!==(int)$a['id']) continue; ?>
    <details class="portal-account" <?php if ($requested) echo 'open'; ?>><summary><?php echo $e($a['game_login']); ?></summary><div class="portal-account-body">
    <?php echo $form('code',$hidden('purpose','password:'.$a['id']).$field('current_password','Текущий пароль профиля','password','autocomplete="current-password"'),'Получить код'); ?>
    <?php echo $form('password',$hidden('link_id',$a['id']).$field('current_password','Текущий пароль профиля','password','autocomplete="current-password"').$field('code','Код из письма','text','pattern="[0-9]{6}" inputmode="numeric"').$field('new_password','Новый пароль (6–16 символов)','password','minlength="6" maxlength="16" autocomplete="new-password"').$field('repeat_password','Повтор пароля','password','minlength="6" maxlength="16" autocomplete="new-password"'),'Сохранить пароль'); ?>
    </div></details><?php } ?>
  </section>
<?php } elseif (in_array($page,['createAccount','calendar','support','bonuses','referrals','messages','history','logins','shop'],true)) {
    $this->renderPartial('//cabinet/portal-pages',compact('page','pageData','e','form','field','hidden','store','uid','gs','sections'));
} elseif ($page==='warehouse') { ?>
  <?php echo $section('Склад покупок и наград'); ?>
  <p class="portal-muted">Выберите предмет, свой игровой аккаунт и персонажа. История доставки сохраняется.</p>
  <?php $items=$store->query('SELECT * FROM {{portal_items}} WHERE user_id=? AND gs_id=? ORDER BY id DESC LIMIT 100',[$uid,$gs])->fetchAll(PDO::FETCH_ASSOC); ?>
  <?php if (!$items) { ?><section class="portal-card portal-empty">На складе пока нет предметов.</section><?php } ?>
  <div class="portal-item-grid"><?php foreach ($items as $item) { ?>
  <section class="portal-card"><small><?php echo $e($item['grade']); ?> · <?php echo $e($item['state']); ?></small><h2><?php echo $literal($item['name']); ?><?php if ($item['enchant']) echo ' +'.(int)$item['enchant']; ?></h2><p>Количество: <?php echo (int)$item['quantity']; ?><?php if ($item['augmented']) echo ' · С аугментацией'; ?></p>
  <?php if ($item['state']==='available' && $store->enabled('warehouse')) {
    $select='<label class="portal-field"><span>Аккаунт и персонаж</span><select name="target" data-portal-target required><option value="">Выберите персонажа</option>';
    foreach ($accounts as $a) { try { foreach (PortalRuntime::characters($a['id']) as $char) $select.='<option value="'.(int)$a['id'].':'.(int)$char['char_id'].'">'.$e($a['game_login'].' · '.$char['char_name'].' · '.$char['level']).'</option>'; } catch (Throwable $error) {} }
    $select.='</select></label>';
    echo $form('deliver',$hidden('item_id',$item['id']).$select,'Получить в игре','data-confirm="Подтвердить выдачу предмета выбранному персонажу?" data-reload');
  } ?></section><?php } ?></div>
<?php } elseif ($page==='exchange') { ?>
  <?php echo $section('Биржа LA2PLUS'); $tab=(string)request()->getQuery('tab','catalog'); if (!in_array($tab,['catalog','sell','lots','purchases','wallet','history'],true)) $tab='catalog'; ?>
  <nav class="portal-tabs"><?php foreach (['catalog'=>'В продаже','sell'=>'Продать','lots'=>'Мои лоты','purchases'=>'Мои покупки','wallet'=>'Кошелёк биржи','history'=>'История'] as $id=>$label) { ?><a <?php if ($tab===$id) echo 'aria-current="page"'; ?> href="<?php echo $e($url('exchange').'?tab='.$id); ?>"><?php echo $e($label); ?></a><?php } ?></nav>
  <?php if (PortalRuntime::isDemo()) { ?><p class="portal-notice">Демонстрационная биржа: средства виртуальные, предметы существуют только в тестовом инвентаре. Заявки вывода не приводят к реальной выплате.</p><?php } ?>
  <?php if (!$store->enabled('market')) { ?><p class="portal-notice">Торговля пока недоступна. Ваши средства и предметы сохранены.</p><?php } ?>
  <?php if ($tab==='wallet') { ?>
  <?php if (PortalRuntime::isDemo()) { ?><section class="portal-card portal-demo-topup"><h2>Демонстрационное пополнение</h2><p>Добавьте виртуальные средства для проверки биржи. Реальная оплата не выполняется, все действия остаются в тестовой базе.</p><?php echo $form('demoTopup',$field('amount','Виртуальная сумма, $ (5–1000)','number','min="5" max="1000" step="0.01"'),'Добавить тестовые средства','data-reload'); ?></section><?php } ?>
  <div class="portal-grid portal-grid--equal"><section class="portal-card"><h2>Пополнение кошелька биржи</h2>
    <div class="portal-payment-method"><b>Криптовалюта</b><span>USDT, BTC, ETH и другие · комиссия биржи 0%</span><?php if ($store->enabled('topup')) echo '<small>Выбрано</small>'; ?></div>
    <?php foreach (['СБП'=>'15%','Карты Украины'=>'25%','Международные карты · Stripe'=>'30%'] as $method=>$fee) { ?><div class="portal-payment-method is-disabled"><b><?php echo $e($method); ?></b><span>Комиссия <?php echo $e($fee); ?></span><small>Скоро</small></div><?php } ?>
    <?php if ($store->enabled('topup')) { ?><p>Комиссию сети и Trybit вы платите сверх суммы. На кошелёк поступит указанная сумма.</p><?php echo $form('payment',$field('amount','Сумма, $ (5–1000)','number','min="5" max="1000" step="0.01"'),'Перейти к оплате','data-payment'); } else { ?><p class="portal-muted">Пополнение биржи пока недоступно.</p><?php } ?>
  </section><section class="portal-card"><h2>Перевод на баланс кабинета</h2><p>По базовому курсу LA2PLUS, без бонусов и скидок. Обратный перевод не предусмотрен.</p><p>Курс: <?php echo $e(config('utopay.rate')); ?> $ за 1 CoL.</p>
    <?php echo $form('transfer',$field('amount','Списать с биржи, $','number','min="0.01" step="0.01" data-transfer-amount').'<p data-transfer-preview data-rate="'.$e(config('utopay.rate')).'"></p>','Перевести в кабинет','data-confirm="Подтвердить перевод по показанному курсу?" data-reload'); ?>
    <h2>Вывод USDT TRC20</h2><p>Минимум 10 $. Комиссия: 3 $ + 25% от суммы. Одна активная заявка, обработка администрацией вручную.</p>
    <?php if ($store->enabled('withdrawals')) echo $form('withdraw',$field('amount','Списать с кошелька, $','number','min="10" step="0.01" data-withdraw-amount').$field('address','USDT-адрес TRON (TRC20)','text','pattern="T[1-9A-HJ-NP-Za-km-z]{33}"').'<p data-withdraw-preview></p>','Создать заявку','data-confirm="Подтвердить заявку и резервирование всей указанной суммы?" data-reload'); ?>
  </section></div>
  <?php $payments=$store->query('SELECT * FROM {{portal_payments}} WHERE user_id=? ORDER BY id DESC LIMIT 20',[$uid])->fetchAll(PDO::FETCH_ASSOC); ?>
  <section class="portal-card"><h2>Пополнения биржи</h2><?php foreach ($payments as $p) { ?><div class="portal-history-row"><span>#<?php echo (int)$p['id']; ?> · <?php echo $e(PortalMoney::format($p['amount'])); ?> $ · <?php echo $e($p['state']); ?></span><?php if ($p['state']==='pending') echo $form('paymentStatus',$hidden('payment_id',$p['id']),'Проверить оплату','data-reload'); ?></div><?php } ?></section>
  <?php } elseif ($tab==='sell') { ?>
  <section class="portal-card"><h2>Выставить предмет со склада</h2><p>Комиссия продажи — 10% с продавца. Предмет резервируется до продажи или отмены лота.</p>
  <?php if ($store->enabled('market')) {
    $items=$store->query("SELECT id,name,enchant FROM {{portal_items}} WHERE user_id=? AND gs_id=? AND state='available' AND kind IN ('WEAPON','ARMOR','JEWELRY') ORDER BY id DESC",[$uid,$gs])->fetchAll(PDO::FETCH_ASSOC);
    $select='<label class="portal-field"><span>Предмет</span><select name="item_id" required><option value="">Выберите предмет</option>';
    foreach ($items as $item) $select.='<option value="'.(int)$item['id'].'">'.$e($item['name'].' +'.$item['enchant']).'</option>';
    echo $form('sell',$select.'</select></label>'.$field('amount','Цена лота, $','number','min="0.01" max="1000000" step="0.01"'),'Выставить','data-reload');
  } ?><p><a href="<?php echo $e($url('warehouse')); ?>">Открыть склад</a></p>
  </section>
  <section class="portal-card"><h2>Перенести предмет из игры</h2><p>Выйдите из игры. Доступные предметы резервируются игровым сервером с сохранением заточки и аугментации.</p>
  <?php if ($sections->enabled('warehouse') && $store->enabled('market') && PortalRuntime::gameReady()) {
    $targets='<label class="portal-field"><span>Персонаж</span><select name="target" data-portal-target required>';
    foreach ($accounts as $a) { try { foreach (PortalRuntime::characters($a['id']) as $char) $targets.='<option value="'.(int)$a['id'].':'.(int)$char['char_id'].'">'.$e($a['game_login'].' · '.$char['char_name']).'</option>'; } catch (Throwable $error) {} }
    echo $form('import',$targets.'</select></label><button type="button" class="btn" data-load-inventory>Загрузить предметы персонажа</button><label class="portal-field"><span>Предмет из инвентаря</span><select name="object_id" required data-inventory-items><option value="">Сначала загрузите инвентарь</option></select></label>','Перенести на склад','data-reload');
  } ?></section>
  <?php } elseif ($tab==='history') { ?>
  <section class="portal-card"><h2>Операции кошелька</h2><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Дата</th><th>Операция</th><th>Изменение, $</th><th>Резерв, $</th></tr></thead><tbody>
  <?php foreach ($store->query('SELECT * FROM {{portal_ledger}} WHERE user_id=? ORDER BY id DESC LIMIT 100',[$uid])->fetchAll(PDO::FETCH_ASSOC) as $row) { ?><tr><td><?php echo $e(gmdate('d.m.Y H:i',$row['created_at'])); ?> UTC</td><td><?php echo $e($row['operation']); ?></td><td><?php echo $e(PortalMoney::format($row['available_delta'])); ?></td><td><?php echo $e(PortalMoney::format($row['reserved_delta'])); ?></td></tr><?php } ?></tbody></table></div>
  <h2>Заявки на вывод</h2><?php foreach ($store->query('SELECT * FROM {{portal_withdrawals}} WHERE user_id=? ORDER BY id DESC LIMIT 50',[$uid])->fetchAll(PDO::FETCH_ASSOC) as $r) { ?><p>#<?php echo (int)$r['id']; ?> · <?php echo $e(PortalMoney::format($r['amount'])); ?> $ → <?php echo $e(PortalMoney::format($r['net'])); ?> USDT · <?php echo $e($r['state']); ?> <?php echo $e($r['txid']); ?></p><?php } ?></section>
  <?php } else {
    $params=[$gs]; $where='l.gs_id=?';
    if ($tab==='lots') { $where.=' AND l.seller_id=?'; $params[]=$uid; }
    elseif ($tab==='purchases') { $where.=" AND l.buyer_id=? AND l.state='sold'"; $params[]=$uid; }
    else $where.=" AND l.state='active'";
    $kind=(string)request()->getQuery('kind',''); $grade=(string)request()->getQuery('grade',''); $aug=(string)request()->getQuery('aug','');
    if (in_array($kind,['WEAPON','ARMOR','JEWELRY'],true)) { $where.=' AND i.kind=?'; $params[]=$kind; }
    if (in_array($grade,['NG','D','C','B','A','S'],true)) { $where.=' AND i.grade=?'; $params[]=$grade; }
    if ($aug==='0'||$aug==='1') { $where.=' AND i.augmented=?'; $params[]=(int)$aug; }
    foreach (['min_enchant'=>'>=','max_enchant'=>'<='] as $query=>$operator) { $v=request()->getQuery($query,''); if ($v!=='' && ctype_digit((string)$v) && (int)$v<=65535) { $where.=' AND i.enchant'.$operator.'?'; $params[]=(int)$v; } }
    $rows=$store->query('SELECT l.*,i.name,i.enchant,i.grade,i.kind,i.augmented,i.quantity FROM {{portal_lots}} l JOIN {{portal_items}} i ON i.id=l.item_id WHERE '.$where.' ORDER BY l.id DESC LIMIT 100',$params)->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <form method="get" class="portal-filters"><input type="hidden" name="tab" value="<?php echo $e($tab); ?>"><select name="kind"><option value="">Все категории</option><?php foreach (['WEAPON'=>'Оружие','ARMOR'=>'Броня','JEWELRY'=>'Бижутерия'] as $k=>$v) { ?><option value="<?php echo $e($k); ?>" <?php if ($kind===$k) echo 'selected'; ?>><?php echo $e($v); ?></option><?php } ?></select><select name="grade"><option value="">Все грейды</option><?php foreach (['NG','D','C','B','A','S'] as $g) { ?><option <?php if ($grade===$g) echo 'selected'; ?>><?php echo $e($g); ?></option><?php } ?></select><input name="min_enchant" type="number" min="0" placeholder="Заточка от" value="<?php echo $e(request()->getQuery('min_enchant','')); ?>"><input name="max_enchant" type="number" min="0" placeholder="до" value="<?php echo $e(request()->getQuery('max_enchant','')); ?>"><select name="aug"><option value="">Любая аугментация</option><option value="1" <?php if ($aug==='1') echo 'selected'; ?>>С аугментацией</option><option value="0" <?php if ($aug==='0') echo 'selected'; ?>>Без аугментации</option></select><button class="btn" type="submit">Показать</button></form>
  <div class="portal-item-grid"><?php foreach ($rows as $lot) { ?><section class="portal-card"><small>#<?php echo (int)$lot['id']; ?> · <?php echo $e($lot['grade']); ?> · <?php echo $e($lot['state']); ?></small><h2><?php echo $e($lot['name']); ?> +<?php echo (int)$lot['enchant']; ?></h2><p><?php echo (int)$lot['quantity']; ?> шт.<?php if ($lot['augmented']) echo ' · С аугментацией'; ?></p><strong class="portal-price"><?php echo $e(PortalMoney::format($lot['price'])); ?> $</strong>
    <?php if ($lot['state']==='active') {
      if ((int)$lot['seller_id']===$uid) echo $form('cancel',$hidden('lot_id',$lot['id']),'Снять с продажи','data-reload');
      elseif ($store->enabled('market')) echo $form('buy',$hidden('lot_id',$lot['id']),'Купить','data-confirm="Подтвердить покупку лота за '.$e(PortalMoney::format($lot['price'])).' $?" data-reload');
    } ?></section><?php } ?></div><?php if (!$rows) { ?><section class="portal-card portal-empty">Лотов по выбранным условиям нет.</section><?php } ?>
  <?php } ?>
<?php } elseif ($page==='admin') { ?>
  <?php echo $section('Платежи и инструменты владельца'); ?>
  <section class="portal-card"><h2>Настройки кабинета игроков</h2><p>Включение и выключение разделов перенесено в общую админ-панель.</p><a class="btn btn--gold" href="/admin/settings/">Открыть настройки в админке</a></section>
  <div class="portal-item-grid"><?php foreach(PortalProduction::readiness() as $check){ ?><article class="portal-card"><h3><?php echo $e($check['name']); ?></h3><strong><?php echo $check['ready']?'Готово':'Требуется подключение'; ?></strong><p class="portal-muted"><?php echo $e($check['detail']); ?></p></article><?php } ?></div>
  <section class="portal-card"><h2>Платежи Coin of Luck</h2><p>Подтверждение проверяется через API провайдера. Для заказов «Требует проверки» повторный счёт автоматически не создаётся.</p><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Заказ</th><th>Игрок</th><th>USD</th><th>CoL</th><th>Способ</th><th>Состояние</th><th></th></tr></thead><tbody>
  <?php $coinOrders=$store->query('SELECT * FROM {{portal_coin_orders}} ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);foreach($coinOrders as $order){ ?><tr><td><?php echo (int)$order['id']; ?></td><td><?php echo (int)$order['user_id']; ?></td><td><?php echo $e(PortalMoney::format($order['amount'])); ?></td><td><?php echo (int)$order['total']; ?></td><td><?php echo $e($order['provider']); ?></td><td><?php echo $e(['creating'=>'Создаётся','sending'=>'Создаётся','pending'=>'Ожидает оплаты','paid'=>'Оплачен','unknown'=>'Требует проверки','review'=>'Возврат: проверка','refunded'=>'Возвращён'][$order['state']]??'Требует проверки'); ?></td><td><?php if($order['state']==='pending')echo $form('coinOrderSync',$hidden('order_id',$order['id']),'Проверить оплату','data-reload');if($order['state']==='review')foreach(['keep'=>'Оставить начисление','reverse'=>'Отменить начисление'] as $choice=>$caption)echo $form('coinReviewResolve',$hidden('order_id',$order['id']).$hidden('action',$choice).$field('reason','Причина решения','text','minlength="4" maxlength="500"'),$caption,'data-confirm="Сохранить решение по возвращённому платежу?" data-reload'); ?></td></tr><?php }if(!$coinOrders){ ?><tr><td colspan="7">Новых заказов пока нет.</td></tr><?php } ?></tbody></table></div></section>
  <section class="portal-card"><h2>Сброс пароля аккаунта</h2><p>Только владелец проекта. Требуются ваш текущий пароль и одноразовый код на вашу подтверждённую почту. Все изменения журналируются.</p>
  <?php echo $form('code',$hidden('purpose','admin_password').$field('user_id','ID игрока','number','min="1"').$field('current_password','Ваш текущий пароль','password','autocomplete="current-password"'),'Получить код'); ?>
  <?php echo $form('adminPassword',$field('user_id','ID игрока','number','min="1"').$field('current_password','Ваш текущий пароль','password','autocomplete="current-password"').$field('code','Код из письма','text','pattern="[0-9]{6}" inputmode="numeric"').$field('new_password','Новый пароль игрока','password','minlength="6" maxlength="16" autocomplete="new-password"').$field('repeat_password','Повтор нового пароля','password','minlength="6" maxlength="16" autocomplete="new-password"'),'Сбросить пароль','data-confirm="Изменить пароль указанного аккаунта?"'); ?></section>
  <section class="portal-card"><h2>Персональная скидка</h2><p>Скидка действует только на услуги и магазин, не суммируется со скидкой товара. Время окончания — UTC.</p>
  <?php echo $form('discount',$field('user_id','ID профиля','number','min="1"').$field('percent','Процент','number','min="0" max="100"').$field('expires','Действует до (UTC)','datetime-local'),'Назначить скидку','data-reload'); ?></section>
  <section class="portal-card"><h2>Выводы USDT TRC20</h2><p>Выплата выполняется вручную. Сначала возьмите заявку в обработку; отметку «Выплачено» сохраняйте только после перевода, с TXID.</p>
  <?php foreach ($store->query("SELECT * FROM {{portal_withdrawals}} WHERE state IN ('pending','processing') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) { ?>
    <article class="portal-account"><h3>Заявка #<?php echo (int)$r['id']; ?> · профиль <?php echo (int)$r['user_id']; ?></h3><p>Списание <?php echo $e(PortalMoney::format($r['amount'])); ?> $ · Комиссия <?php echo $e(PortalMoney::format($r['fee'])); ?> $ · Выплатить <?php echo $e(PortalMoney::format($r['net'])); ?> USDT</p><p class="portal-address"><?php echo $e($r['address']); ?></p>
    <?php if ($r['state']==='pending') echo $form('withdrawAdmin',$hidden('withdrawal_id',$r['id']).$hidden('action','processing'),'Взять в обработку','data-reload');
    else echo $form('withdrawAdmin',$hidden('withdrawal_id',$r['id']).$hidden('action','paid').$field('txid','TXID','text','pattern="[a-fA-F0-9]{64}"'),'Отметить выплаченной','data-confirm="Подтвердите, что перевод уже выполнен и TXID проверен." data-reload');
    echo $form('withdrawAdmin',$hidden('withdrawal_id',$r['id']).$hidden('action','reject'),'Отклонить и вернуть резерв','data-reload'); ?>
    </article>
  <?php } ?></section>
<?php } ?>
  <?php if ($sections->enabled('accounts')) { ?>
  <dialog class="portal-dialog" data-portal-dialog><button type="button" data-portal-close aria-label="Закрыть">×</button><h2>Телепорт в город</h2><?php echo $form('teleport',$hidden('link_id','').$hidden('char_id',''),'В город','data-confirm="Переместить офлайн-персонажа в город?" data-reload'); ?></dialog>
  <?php } ?>
  <?php if ($sections->enabled('deposit') && $sections->enabled('warehouse') && PortalRuntime::gameReady()) {
    $coinTargets=[];foreach ($accounts as $a) { try { $coinTargets[(int)$a['id']]=array_map(function($char){return array_intersect_key($char,array_flip(['char_id','char_name','level','online']));},PortalRuntime::characters($a['id'])); } catch (Throwable $error) { $coinTargets[(int)$a['id']]=[]; } }
    $accountSelect='<label class="portal-field"><span>Игровой аккаунт</span><select name="link_id" data-coin-account required><option value="">Выберите аккаунт</option>';
    foreach ($accounts as $a) $accountSelect.='<option value="'.(int)$a['id'].'">'.$e($a['game_login']).'</option>';
    $accountSelect.='</select></label>';
  ?>
  <dialog class="portal-dialog" data-coins-dialog data-target-link="<?php echo (int)($target['link_id']??0); ?>" data-target-char="<?php echo (int)($target['char_id']??0); ?>" aria-label="Перевод Coin of Luck"><button type="button" data-coins-close aria-label="Закрыть">×</button><h2>Перевести Coin of Luck в игру</h2><p data-dialog-message role="status" aria-live="polite"></p><p>LA2PLUS · Interlude ×20</p>
  <?php if (PortalRuntime::isDemo()) { ?><p class="portal-muted">Тестовый сервер: монеты и доставка демонстрационные.</p><?php } ?>
  <?php echo $form('coins',$accountSelect.'<label class="portal-field"><span>Персонаж</span><select name="char_id" data-coin-character required><option value="">Сначала выберите аккаунт</option></select></label>'.$field('coins','Количество CoL','number','min="1" max="1000000" step="1" data-coin-amount').'<p data-coin-cost>К списанию: 0 CoL</p><p class="portal-muted">Для получения выйдите из игры. Один CoL на балансе = один Coin of Luck в игре.</p>','Перевести в игру','data-reload'); ?>
  <script type="application/json" data-coin-targets><?php echo json_encode($coinTargets,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
  </dialog><?php } ?>

</div>
<script src="/design/js/portal.js?v=no-pin-20261118-1" defer></script>
<?php echo PortalI18n::html(ob_get_clean(),app()->language); ?>
