<?php
/**
 * Large line-art motif for an About section's heading column.
 *
 * Those sections put the label and heading in a 5-column well beside a 6-column
 * body, which leaves most of the left column empty on desktop — the flattest
 * space on the site. This fills it with a drawing rather than more words.
 *
 * Same language as the case-study covers and the service glyphs, but bigger and
 * quieter: at this size the accent has to sit lower or the mark starts competing
 * with the display type it is meant to support. Hidden below `lg`, where the
 * columns stack and the drawing would only push the body text down.
 *
 * Geometry is generated rather than hand-typed — a ring of ticks written out by
 * hand is twelve chances to fat-finger a coordinate.
 *
 * @var string $motif
 */

/** Point on a circle, in SVG coordinates (y grows downward), rounded for a tidy path. */
$pt = static function (float $angleDeg, float $radius, float $cx = 100.0, float $cy = 100.0): array {
    $r = deg2rad($angleDeg);

    return [
        round($cx + $radius * cos($r), 2),
        round($cy + $radius * sin($r), 2),
    ];
};

$parts = [];

switch ($motif) {
    // Philosophy — "evidence over elegance": a measuring diagram, not a flourish.
    case 'philosophy':
        $parts[] = '<circle cx="100" cy="96" r="58"/>';
        $parts[] = '<path d="M20 154h160" opacity=".55"/>';

        // Ticks along the baseline: the claim is checked against a scale.
        for ($x = 32; $x <= 168; $x += 17) {
            $len = ($x === 100) ? 12 : 6;
            $parts[] = sprintf('<path d="M%d 154v-%d" opacity=".4"/>', $x, $len);
        }

        $parts[] = '<path d="M100 38v116" opacity=".45"/>';
        $parts[] = '<circle cx="100" cy="96" r="5"/>';

        // A chord, and the arc it subtends — the measured thing and its bound.
        [$ax, $ay] = $pt(200, 58, 100, 96);
        [$bx, $by] = $pt(340, 58, 100, 96);
        $parts[] = sprintf('<path d="M%s %s L%s %s" opacity=".7"/>', $ax, $ay, $bx, $by);
        break;

    // Creative advantage — an aperture: the craft is choosing what to let in.
    case 'creative':
        $parts[] = '<circle cx="100" cy="100" r="68"/>';

        $outer = [];
        $inner = [];

        for ($i = 0; $i < 6; $i++) {
            $outer[$i] = $pt($i * 60, 68);
            $inner[$i] = $pt($i * 60 + 30, 27);
        }

        // Inner opening.
        $hex = [];
        foreach ($inner as [$x, $y]) {
            $hex[] = $x . ' ' . $y;
        }
        $parts[] = '<path d="M' . implode(' L', $hex) . ' Z"/>';

        // Blades, each running from an opening vertex out to the rim.
        for ($i = 0; $i < 6; $i++) {
            [$ix, $iy] = $inner[$i];
            [$ox, $oy] = $outer[($i + 1) % 6];
            $parts[] = sprintf('<path d="M%s %s L%s %s" opacity=".7"/>', $ix, $iy, $ox, $oy);
        }
        break;

    // Credentials — a seal, drawn rather than stamped.
    case 'credentials':
        $parts[] = '<circle cx="100" cy="100" r="70"/>';
        $parts[] = '<circle cx="100" cy="100" r="56" opacity=".6"/>';

        for ($i = 0; $i < 12; $i++) {
            [$x1, $y1] = $pt($i * 30, 58);
            [$x2, $y2] = $pt($i * 30, 68);
            $parts[] = sprintf('<path d="M%s %s L%s %s" opacity=".5"/>', $x1, $y1, $x2, $y2);
        }

        // Inner mark: a check built from two straight strokes, kept geometric.
        $parts[] = '<path d="M78 100 L94 116 L124 84"/>';
        break;

    default:
        return;
}
?>
<svg class="about-motif" viewBox="0 0 200 200" fill="none" stroke="currentColor"
     stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false"><?= implode('', $parts) ?></svg>
