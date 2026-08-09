<?php
/**
 * Line-art glyph for a service card.
 *
 * Drawn in the same language as the case-study covers on /work: thin strokes, no
 * fills, compass-and-ruler geometry, plenty of air. Inline rather than an <img>
 * so it inherits currentColor and costs no request — and so the strict CSP has
 * nothing to allow.
 *
 * Keyed off `services.icon`, which has held these six values since the table was
 * seeded and was simply never rendered. Adding a service with an unknown icon
 * draws nothing rather than a broken box.
 *
 * @var string $icon
 */

$glyphs = [
    // Strategy: a plan is a path with decisions on it, not a straight line.
    'chart' => '<path d="M8 48 L22 34 L32 40 L52 18"/>
                <circle cx="22" cy="34" r="3"/><circle cx="32" cy="40" r="3"/>
                <circle cx="52" cy="18" r="4.5"/>
                <path d="M8 56h48" opacity=".45"/>',

    // Creative and video: play form held inside a viewfinder.
    'pen' => '<circle cx="32" cy="32" r="15"/>
              <path d="M28 25.5 L41 32 L28 38.5 Z"/>
              <path d="M10 14h8M10 14v8M54 14h-8M54 14v8M10 50h8M10 50v-8M54 50h-8M54 50v-8" opacity=".55"/>',

    // Search: the lens, and beneath it results that compound.
    'search' => '<circle cx="27" cy="27" r="13"/><path d="M36.5 36.5 L52 52"/>
                 <path d="M21 30 L26 25 L31 29" opacity=".7"/>
                 <path d="M12 56h18M36 56h16" opacity=".4"/>',

    // Social: one idea, distributed. Deliberately echoes the drone motif on /work.
    'share' => '<circle cx="16" cy="44" r="6"/><circle cx="48" cy="44" r="6"/>
                <circle cx="32" cy="16" r="6"/>
                <path d="M32 22 L18.5 38.5M32 22 L45.5 38.5M22 44h20" opacity=".7"/>',

    // Paid media: concentric rings, and the shot that has to land in the middle.
    'target' => '<circle cx="32" cy="32" r="18"/><circle cx="32" cy="32" r="11" opacity=".65"/>
                 <circle cx="32" cy="32" r="4"/>
                 <path d="M32 8v6M32 50v6M8 32h6M50 32h6" opacity=".5"/>',

    // Websites: a frame, and the structure inside it doing the work.
    'layout' => '<rect x="9" y="13" width="46" height="38" rx="3"/>
                 <path d="M9 23h46"/>
                 <circle cx="15" cy="18" r="1.4"/><circle cx="20" cy="18" r="1.4"/>
                 <path d="M17 31h14v14H17z" opacity=".6"/>
                 <path d="M37 31h11M37 37h11M37 43h7" opacity=".6"/>',
];

$d = $glyphs[$icon] ?? null;

if ($d === null) {
    return;
}
?>
<svg class="service-glyph" viewBox="0 0 64 64" fill="none" stroke="currentColor"
     stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false"><?= $d ?></svg>
