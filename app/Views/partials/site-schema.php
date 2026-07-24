<?php

use App\Models\Setting;

/**
 * Organization and WebSite structured data, emitted on every public page.
 *
 * Built as PHP arrays and json_encode'd rather than written as literal JSON in
 * the template: settings values are author-controlled, and a stray quote in the
 * site name would otherwise break out of the script block.
 *
 * JSON_HEX_TAG additionally escapes < and > so a "</script>" inside any value
 * cannot terminate the block early.
 */
$siteName = (string) Setting::get('site_name', config('app.name'));

$sameAs = array_values(array_filter([
    Setting::get('social_instagram'),
    Setting::get('social_linkedin'),
    Setting::get('social_x'),
    Setting::get('social_youtube'),
]));

$organisation = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $siteName,
    'url'      => url('/'),
    'description' => (string) Setting::get('seo_default_description', ''),
];

if ($address = Setting::get('address')) {
    $organisation['address'] = [
        '@type'          => 'PostalAddress',
        'addressLocality' => $address,
    ];
}

if ($email = Setting::get('contact_email')) {
    $organisation['contactPoint'] = [
        '@type'       => 'ContactPoint',
        'contactType' => 'sales',
        'email'       => $email,
    ];

    if ($phone = Setting::get('contact_phone')) {
        $organisation['contactPoint']['telephone'] = $phone;
    }
}

if ($sameAs !== []) {
    $organisation['sameAs'] = $sameAs;
}

$website = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => $siteName,
    'url'      => url('/'),
    // Tells search engines the blog is searchable, and how.
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => [
            '@type'       => 'EntryPoint',
            'urlTemplate' => url('/blog') . '?q={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
];

$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
<?= json_encode($organisation, $flags) ?>
</script>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
<?= json_encode($website, $flags) ?>
</script>
