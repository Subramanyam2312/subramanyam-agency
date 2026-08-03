<?php

declare(strict_types=1);

/**
 * Type pairings offered under Settings -> Appearance.
 *
 * Every family here is self-hosted in public/assets/fonts and declared in
 * app.css, so switching pairing adds no external request and the site keeps its
 * font-src 'self' policy. A pairing is two stacks: `display` drives headings and
 * the wordmark, `body` drives everything else.
 *
 * Adding a pairing means dropping the woff2 in, declaring the @font-face, and
 * adding a row here — nothing else in the application needs to change.
 */
return [
    'default' => 'instrument',

    'pairings' => [
        'instrument' => [
            'label'   => 'Instrument — the brand default',
            'note'    => 'Editorial serif with a quiet grotesque. What the brand kit specifies.',
            'display' => "'Instrument Serif', ui-serif, Georgia, serif",
            'body'    => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        ],
        'fraunces' => [
            'label'   => 'Fraunces & Inter',
            'note'    => 'Warmer, softer headline with the most neutral body text available.',
            'display' => "'Fraunces', ui-serif, Georgia, serif",
            'body'    => "'Inter', ui-sans-serif, system-ui, sans-serif",
        ],
        'playfair' => [
            'label'   => 'Playfair Display & Jost',
            'note'    => 'High-contrast classical luxury; geometric body keeps it modern.',
            'display' => "'Playfair Display', ui-serif, Georgia, serif",
            'body'    => "'Jost', ui-sans-serif, system-ui, sans-serif",
        ],
        'cormorant' => [
            'label'   => 'Cormorant Garamond & Montserrat',
            'note'    => 'The lightest, most couture option — fashion and jewellery territory.',
            'display' => "'Cormorant Garamond', ui-serif, Garamond, serif",
            'body'    => "'Montserrat', ui-sans-serif, system-ui, sans-serif",
        ],
        'dmserif' => [
            'label'   => 'DM Serif Display & DM Sans',
            'note'    => 'Sturdier, more contemporary. Reads confident rather than delicate.',
            'display' => "'DM Serif Display', ui-serif, Georgia, serif",
            'body'    => "'DM Sans', ui-sans-serif, system-ui, sans-serif",
        ],
    ],
];
