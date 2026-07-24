<?php

use App\Core\Auth;
use App\Models\Setting;

/**
 * Analytics, rendered only when an ID has actually been configured.
 *
 * Three deliberate decisions:
 *
 *  - Nothing loads unless an ID is set, so a fresh install ships no third-party
 *    request at all and the privacy policy stays accurate by default.
 *  - Signed-in staff are excluded. Nothing distorts a small site's numbers like
 *    its own team refreshing pages all day.
 *  - The inline config carries the CSP nonce, and SecurityHeaders widens the
 *    policy to Google's domains ONLY when an ID is present — the strict policy
 *    is the default, not the exception.
 */
$ga4 = trim((string) Setting::get('ga_measurement_id', ''));
$gtm = trim((string) Setting::get('gtm_id', ''));

// Never measure our own sessions.
if (Auth::check()) {
    return;
}

// Guard against a mistyped value widening the CSP for nothing.
if ($ga4 !== '' && preg_match('/^G-[A-Z0-9]{6,}$/i', $ga4) !== 1) {
    $ga4 = '';
}

if ($gtm !== '' && preg_match('/^GTM-[A-Z0-9]{4,}$/i', $gtm) !== 1) {
    $gtm = '';
}
?>
<?php if ($gtm !== ''): ?>
    <script nonce="<?= e(csp_nonce()) ?>">
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
        j.setAttribute('nonce','<?= e(csp_nonce()) ?>');
        f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($gtm) ?>');
    </script>
<?php elseif ($ga4 !== ''): ?>
    <!-- GA4 directly. Loaded async and after the rest of the head, so it never
         sits on the critical path. -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"
            nonce="<?= e(csp_nonce()) ?>"></script>
    <script nonce="<?= e(csp_nonce()) ?>">
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= e($ga4) ?>', { anonymize_ip: true });
    </script>
<?php endif; ?>
