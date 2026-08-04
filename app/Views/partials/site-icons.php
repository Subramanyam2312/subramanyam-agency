<?php

use App\Core\Branding;

/**
 * Browser icon links. Included from every layout's <head> — public site, admin
 * and sign-in — so the CMS tab carries the same icon as the site it edits.
 *
 * The uploaded file is served as-is rather than through a generated WebP variant.
 * The smallest rendition the media library makes is 320px, which is ten times what
 * an icon slot needs, and WebP favicon support is still uneven in older Safari —
 * so a variant would trade a negligible saving for a blank tab on some browsers.
 * Nothing is emitted when no icon is set, which lets the browser use its default.
 */
$icon = Branding::icon();

if ($icon === null) {
    return;
}

$href = Branding::url($icon);
$mime = (string) $icon['mime'];

/* Square icons report one number; anything else is reported honestly as-is. */
$sizes = '';

if (($icon['width'] ?? null) && ($icon['height'] ?? null)) {
    $sizes = (int) $icon['width'] . 'x' . (int) $icon['height'];
}
?>
<link rel="icon" href="<?= e($href) ?>" type="<?= e($mime) ?>"<?= $sizes !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>>
<link rel="apple-touch-icon" href="<?= e($href) ?>">
