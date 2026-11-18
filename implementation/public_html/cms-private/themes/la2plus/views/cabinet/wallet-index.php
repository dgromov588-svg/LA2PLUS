<?php
/**
 * Кошелёк: баланс, переводы между своими аккаунтами, история операций.
 *
 * @var $this CController
 * @var float $balance
 * @var array $accounts
 * @var array $group
 * @var int $ownerId
 * @var array $targets аккаунты, доступные для перевода
 * @var array $history
 * @var array $types
 */

$title_ = 'Кошелёк';
$this->pageTitle = $title_;
$this->breadcrumbs = [$title_];

$canTransfer = count($targets) > 0;
?>

<?php $this->widget('app.widgets.FlashMessages.FlashMessages') ?>

<div class="panel-head">
    <div>
        <p class="cms-eyebrow">LA2PLUS • WALLET</p>
        <h2 class="section__title">Кошелёк</h2>
    </div>
    <div class="panel-role">
        <span class="panel-role__label">Доступно</span>
        <b><?php echo formatCurrency($balance) ?></b>
    </div>
</div>

<div class="panel-columns">
    <section class="panel-box">
        <h3 class="panel-box__title">Перевод между своими аккаунтами</h3>

        <?php if (!$canTransfer) { ?>
            <p class="panel-hint">
                Чтобы переводить средства, привяжите второй игровой аккаунт в разделе
                <a href="<?php echo CHtml::normalizeUrl(['/cabinet/accounts/index']) ?>">«Мои аккаунты»</a>.
            </p>
        <?php } else { ?>
            <form class="panel-form" method="post">
                <?php echo CHtml::hiddenField(request()->csrfTokenName, request()->csrfToken) ?>
                <?php echo class_exists('PortalRuntime')?PortalRuntime::pinField():''; ?>
                <input type="hidden" name="act" value="transfer">
                <label>Кому
                    <select name="to_user_id" required>
                        <?php foreach ($targets as $account) { ?>
                            <option value="<?php echo (int) $account['user_id'] ?>"><?php echo e($account['game_login']) ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>Сумма
                    <input type="text" name="amount" required placeholder="100">
                </label>
                <label>Комментарий
                    <input type="text" name="comment" maxlength="255" placeholder="например, на расходники">
                </label>
                <button class="btn btn--gold btn--sm" type="submit"><span>Перевести</span></button>
                <p class="panel-hint">
                    <?php if ($group['shared_wallet']) { ?>
                        Общий кошелёк включён: средства списываются с кошелька группы и зачисляются на личный баланс выбранного аккаунта.
                    <?php } else { ?>
                        Общий кошелёк выключен: перевод идёт с вашего личного баланса на баланс выбранного аккаунта.
                    <?php } ?>
                </p>
            </form>
        <?php } ?>
    </section>

    <section class="panel-box">
        <h3 class="panel-box__title">Ваши аккаунты</h3>
        <table class="table">
            <thead>
            <tr><th>Логин</th><th>Роль</th><th>Баланс</th></tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account) { ?>
                <tr<?php echo (int) $account['user_id'] === (int) $ownerId ? ' style="background:rgba(200,160,82,.08)"' : '' ?>>
                    <td><?php echo e($account['game_login']) ?><?php echo (int) $account['user_id'] === (int) user()->getId() ? ' <small>(вы)</small>' : '' ?></td>
                    <td><?php echo (int) $account['user_id'] === (int) $ownerId ? 'кошелёк группы' : 'личный' ?></td>
                    <td><?php echo formatCurrency((int) $account['user_id'] === (int) $ownerId ? $balance : (float) $account['balance'], false) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </section>
</div>

<section class="panel-box" style="margin-top:16px">
    <h3 class="panel-box__title">История операций</h3>
    <table class="table">
        <thead>
        <tr><th>Дата</th><th>Операция</th><th>Аккаунт</th><th>Сумма</th><th>Остаток</th><th>Комментарий</th></tr>
        </thead>
        <tbody>
        <?php if ($history) { ?>
            <?php foreach ($history as $row) { ?>
                <tr>
                    <td><?php echo e($row['created_at']) ?></td>
                    <td><?php echo e(isset($types[$row['type']]) ? $types[$row['type']] : $row['type']) ?></td>
                    <td><?php echo e($row['login']) ?></td>
                    <td><?php echo(($row['type'] === 'debit' || $row['type'] === 'transfer_out') ? '−' : '+') . formatCurrency($row['amount'], false) ?></td>
                    <td><?php echo formatCurrency($row['balance_after'], false) ?></td>
                    <td><?php echo e($row['comment']) ?></td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr><td colspan="6">Операций пока не было</td></tr>
        <?php } ?>
        </tbody>
    </table>
</section>
