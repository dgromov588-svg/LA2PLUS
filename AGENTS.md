# LA2PLUS project conventions

## Branding for the new website and player cabinet

Use the owner's official gold-and-blue/purple LA2PLUS artwork for every visual logo placement. The canonical image is `implementation/public_html/design/assets/images/logo.png`.

Render logos through `BrandLogo::html(...)` from `implementation/public_html/cms-private/protected/components/BrandLogo.php` and include `BrandLogo::STYLES` in the page layout. Follow `implementation/BRAND.md` for variants, aspect ratio, animation and accessibility.

Keep all future headers, cabinet menus, login screens, loading screens and decorative brand placements consistent with this component. Preserve the artwork's transparency and 3:1 aspect ratio. Support reduced-motion preferences for the shimmer.

## Player cabinet sections

Optional player sections are managed through `CabinetSections` and rendered by `CabinetNavigation`. Register new optional pages and their mutating operations in the same policy so disabled sections are hidden in menus and blocked on the server, including alternate routes. Home, security, logout and owner management remain available. Section switches are independent from payment and game integration readiness flags.

The player dashboard has no personal-discount summary card or Android/iOS installation offers. Preserve these preferences in subsequent changes unless the owner requests otherwise.

The player cabinet does not use transaction PINs. Do not add PIN inputs, setup screens or PIN checks to site operations. Keep authentication, CSRF, verified-email checks, and current-password/email-code confirmation for password changes.

Site navigation links and buttons open destinations in the current browser tab. Do not introduce target="_blank" or window.open for site navigation.
