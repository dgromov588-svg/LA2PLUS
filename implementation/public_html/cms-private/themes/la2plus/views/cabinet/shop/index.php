<?php
/**
 * @var ShopController $this
 * @var ShopCategories[] $categories
 * @var ShopCategories $category
 * @var CActiveDataProvider $dataProvider
 * @var ShopCategories[] $data
 * @var Gs $gs
 * @var bool $utopayEnabled
 * @var bool $wfpEnabled
 * @var string $wfpCurrency
 * @var float $wfpRate
 * @var string $utopayProvider
 * @var string $utopayProviderName
 */

$title_ = Yii::t('cabinet', 'menu.shop');
$this->pageTitle = $title_;
$this->breadcrumbs = [$title_];
?>

<style>
    .shop-pay { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
    .shop-pay__card { background: linear-gradient(180deg, #3b5bdb, #2b45b0); border-color: #2b45b0; }
    .shop-pay__hint { margin: 8px 0 0; font-size: 12px; opacity: .65; }
</style>

<?php if ($categories) { ?>
    <ul class="tabs">
        <?php foreach ($categories as $item) { ?>
            <li<?php echo $item->id == $category->id ? ' class="active"' : '' ?>>
                <a href="<?php echo $this->createUrl('index', ['category_link' => $item->link]) ?>">
                    <?php echo CHtml::encode($item->name) ?>
                </a></li>
        <?php } ?>
    </ul>
<?php } ?>

<?php if ($data = $dataProvider->data) { ?>
    <?php $this->widget('app.widgets.FlashMessages.FlashMessages') ?>

    <div class="entry">
        <div class="scroll-pane">
            <div class="shop">
                <table class="table pack-info-table">
                    <tbody>
                    <?php foreach ($data as $i => $pack) { ?>
                        <tr>
                            <td>
                                <div class="pack-info clearfix">
                                    <?php if ($pack->imgIsExists('original')) { ?>
                                        <figure>
                                            <?php echo CHtml::image($pack->getImgUrl('original')) ?>
                                        </figure>
                                    <?php } ?>
                                    <div class="info">
                                        <h3><?php echo e($pack->title) ?></h3>
                                        <p class="desc"><?php echo $pack->description ?></p>
                                    </div>
                                </div>
                                <?php echo CHtml::beginForm(['/cabinet/shop/buy', 'category_link' => $category->link], 'post', ['class' => 'form-inline']) ?>
                                <?php if (class_exists('PortalRuntime') && PortalRuntime::enabled()) { ?>
                                <input type="hidden" name="portal_request_key" value="<?php echo CHtml::encode(PortalRuntime::requestKey()); ?>">
                                <label>PIN операций сайта <input type="password" name="portal_pin" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" required></label>
                                <?php } ?>
                                <table class="table shop-info-table">
                                    <colgroup>
                                        <col style="width: 10%">
                                        <col style="">
                                        <col style="width: 20%">
                                        <col style="width: 10%">
                                        <col style="width: 20%">
                                        <col style="width: 5%">
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th><?php echo Yii::t('cabinet', 'common.name') ?></th>
                                        <th><?php echo Yii::t('cabinet', 'common.qty') ?></th>
                                        <th><?php echo Yii::t('cabinet', 'common.enchant') ?></th>
                                        <th><?php echo Yii::t('cabinet', 'common.price') ?></th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pack->items as $ii => $item) { ?>
                                        <?php if (class_exists('PortalRuntime') && PortalRuntime::enabled()) $item->discount=max((float)$item->discount,PortalRuntime::discount((int)user()->getId())); ?>
                                        <tr>
                                            <td><?php echo $item->itemInfo->getIcon() ?></td>
                                            <td>
                                                <?php echo e($item->itemInfo->getFullName()) ?>
                                                <?php echo $item->itemInfo->getGrade() ?>
                                                <?php if ($item->description) { ?>
                                                    <span class="glyphicon glyphicon-question-sign"
                                                          title="<?php echo e($item->description) ?>"
                                                          rel="tooltip"></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center">
                                                    <input type="text" name="items[<?php echo $ii ?>][count]" value="1" class="form-control js-amount" style="width: 55px;padding: 0 5px; text-align: center">
                                                    <span style="margin-left:3px;display: flex">x<?php echo $item->count ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item->enchant > 0) { ?>
                                                    <span style="color: green"><?php echo $item->enchant ?></span>
                                                <?php } else { ?>
                                                    <?php echo $item->enchant ?>
                                                <?php } ?>
                                            </td>
                                            <td
                                                    class="js-cost"
                                                    data-cost="<?php echo (int) $item->cost ?>"
                                                    data-discount="<?php echo (float) $item->discount ?>"
                                            >
                                                <?php if ($item->discount > 0) { ?>
                                                    <?php echo Yii::t('cabinet', 'shop.discount', ['{0}' => $item->discount]) ?>
                                                    <br>
                                                    <b><span class="js-discount-h"><?php echo ShopItems::costAtDiscount($item->cost, $item->discount) ?></span> <?php echo $gs->currency_name ?></b>
                                                    <br>
                                                    <strike><span class="js-cost-h"><?php echo (float) $item->cost ?></span></strike>
                                                    <br>
                                                <?php } else { ?>
                                                    <span class="js-cost-h"><?php echo (float) $item->cost ?></span> <?php echo $gs->currency_name ?>
                                                <?php } ?>
                                                <?php echo config('server.curryncy_name') ?>
                                            </td>
                                            <td>
                                                <?php if ($characters) { ?>
                                                    <label for="<?php echo $item->id ?>" class="control">
                                                        <input id="<?php echo $item->id ?>" type="checkbox"
                                                               name="items[<?php echo $ii ?>][id]"
                                                               value="<?php echo $item->id ?>">
                                                        <span class="switch"></span>
                                                    </label>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                        <tfoot>
                                        <tr>
                                            <td colspan="6">
                                                <?php if ($characters) { ?>
                                                    <input type="hidden" name="pack_id" value="<?php echo $pack->id ?>">
                                                    <?php echo CHtml::dropDownList('char_id', '', CHtml::listData($characters, 'char_id', 'char_name'), ['class' => 'form-control']) ?>

                                                    <div class="shop-pay">
                                                        <?php if (class_exists('PortalRuntime') && PortalRuntime::enabled()) { ?><a class="btn" href="/cabinet/deposit/">Пополнить баланс</a><?php } ?>
                                                        <button type="submit" class="btn btn--gold">
                                                            <span><?php echo Yii::t('cabinet', 'shop.buySelected') ?></span>
                                                        </button>

                                                        <?php if ($utopayEnabled && $utopayProvider !== '') { ?>
                                                            <button type="submit" class="btn btn--gold shop-pay__card"
                                                                    formaction="<?php echo $this->createUrl('/cabinet/shop-utopay/buy', ['category_link' => $category->link]) ?>">
                                                                <span><?php echo Yii::t('cabinet', 'shop.utopayPayButton', ['{0}' => $utopayProviderName]) ?></span>
                                                            </button>
                                                        <?php } elseif ($utopayEnabled) { ?>
                                                            <button type="submit" class="btn btn--gold shop-pay__card"
                                                                    formaction="<?php echo $this->createUrl('/cabinet/shop-utopay/buy', ['category_link' => $category->link]) ?>">
                                                                <span><?php echo Yii::t('cabinet', 'shop.utopayPayCard') ?></span>
                                                            </button>
                                                        <?php } ?>

                                                        <?php if (!empty($wfpEnabled)) { ?>
                                                            <button type="submit" class="btn btn--gold shop-pay__card"
                                                                    formaction="<?php echo $this->createUrl('/cabinet/wayforpay-shop/buy', ['category_link' => $category->link]) ?>">
                                                                <span><?php echo Yii::t('cabinet', 'shop.wayforpayPayCard', ['{0}' => $wfpCurrency]) ?></span>
                                                            </button>
                                                        <?php } ?>
                                                    </div>

                                                    <?php if ($utopayEnabled || !empty($wfpEnabled)) { ?>
                                                        <p class="shop-pay__hint">
                                                            <?php echo Yii::t('cabinet', 'shop.utopayHint') ?>
                                                            <?php if (!empty($wfpEnabled)) { ?>
                                                                <br><?php echo Yii::t('cabinet', 'shop.wayforpayHint', ['{0}' => $wfpCurrency, '{1}' => number_format((float) $wfpRate, 2, '.', '')]) ?>
                                                            <?php } ?>
                                                        </p>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="alert alert-info">
                                                        <?php echo Yii::t('cabinet', 'shop.needCharacter') ?>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        </tfoot>
                                </table>
                                <?php echo CHtml::endForm() ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php $this->widget('CLinkPager', [
        'pages' => $dataProvider->getPagination(),
    ]) ?>
<?php } else { ?>
    <div class="alert alert-danger">
        <?php echo Yii::t('cabinet', 'common.noData') ?>
    </div>
<?php } ?>
