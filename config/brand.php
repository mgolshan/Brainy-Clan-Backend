<?php

/*
 * Brand files: the one source of truth for the Brainy Clan logo (the owl "Ollie").
 *
 * Every place that shows the logo — the website header and favicon, the legal / account /
 * password / subscribe pages, the Filament admin and every email — resolves it through
 * `App\Modules\Core\Support\Brand`, so a new logo is a file swap: replace both files below
 * (same names), nothing else. Paths are relative to public/.
 *
 * - `logo`     the vector mark used on the web (header, favicon, admin).
 * - `logo_png` a 192×192 transparent PNG render of the same mark, used by email (Gmail and
 *              Outlook do not display SVG) and as the touch icon.
 *
 * The SVG is a copy of the app repo's `assets/brand/owl.svg` (the app icon is rendered from that
 * file): when the owl changes there, copy it here and re-render the PNG (README "Brand and logo").
 * URLs carry `?v=` (a fingerprint of the file's modification time), so a replaced logo is fetched
 * at once instead of being served from a browser or mail-proxy cache.
 */

return [

    'logo' => 'images/brand/logo.svg',

    'logo_png' => 'images/brand/logo.png',

];
