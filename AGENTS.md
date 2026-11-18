# LA2PLUS project conventions

## Branding for the new website and player cabinet

Use the owner's official gold-and-blue/purple LA2PLUS artwork for every visual logo placement. The canonical image is `implementation/public_html/design/assets/images/logo.png`.

Render logos through `BrandLogo::html(...)` from `implementation/public_html/cms-private/protected/components/BrandLogo.php` and include `BrandLogo::STYLES` in the page layout. Follow `implementation/BRAND.md` for variants, aspect ratio, animation and accessibility.

Keep all future headers, cabinet menus, login screens, loading screens and decorative brand placements consistent with this component. Preserve the artwork's transparency and 3:1 aspect ratio. Support reduced-motion preferences for the shimmer.
