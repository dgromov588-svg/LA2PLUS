<?php
/** Canonical LA2PLUS brand mark: use this instead of typographic logo substitutes. */
class BrandLogo
{
    const ASSET='/design/assets/images/logo.png?v=brand-20261118';
    const STYLES='/design/css/components/brand-logo.css?v=brand-20261118';
    public static function assetUrl() { return self::ASSET; }
    public static function html($variant='nav',$decorative=false)
    {
        if (!in_array($variant,['nav','display','header','hero','preloader'],true)) $variant='nav';
        $asset=htmlspecialchars(self::ASSET,ENT_QUOTES,'UTF-8');
        $classes=['header'=>'header__logo-img','hero'=>'hero__logo-img','preloader'=>'preloader__logo-img'];
        $imageClass='brand-logo__image'.(isset($classes[$variant])?' '.$classes[$variant]:'');
        return '<span class="brand-logo brand-logo--'.$variant.'" style="--brand-logo-mask:url(\''. $asset .'\')"'.($decorative?' aria-hidden="true"':'').'>'
            .'<img class="'.$imageClass.'" src="'.$asset.'" alt="'.($decorative?'':'LA2PLUS').'" width="2172" height="724" decoding="async" draggable="false">'
            .'<span class="brand-logo__sheen" aria-hidden="true"></span></span>';
    }
}
