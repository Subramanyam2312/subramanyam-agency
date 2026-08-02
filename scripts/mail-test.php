<?php

declare(strict_types=1);

/**
 * Sends one test email using the current .env mail settings, so you can confirm
 * SMTP works on the live server before relying on the contact form.
 *
 *   php scripts/mail-test.php                    # sends to MAIL_TO_ADDRESS
 *   php scripts/mail-test.php you@example.com    # sends to a specific address
 *
 * On success the recipient gets a "SMTP test" email. On failure the SMTP error
 * is printed. With MAIL_DRIVER=log it writes to storage/logs/mail.log instead.
 */

use App\Core\Mailer;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

require BASE_PATH . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$to = $argv[1] ?? (string) config('mail.to.address');

if ($to === '') {
    exit("No recipient. Set MAIL_TO_ADDRESS in .env or pass one: php scripts/mail-test.php you@example.com\n");
}

echo 'Driver:  ' . config('mail.driver') . PHP_EOL;
echo 'Host:    ' . config('mail.host') . ':' . config('mail.port')
    . ' (' . (config('mail.encryption') ?: 'none') . ')' . PHP_EOL;
echo 'From:    ' . config('mail.from.address') . PHP_EOL;
echo 'To:      ' . $to . PHP_EOL;
echo str_repeat('-', 48) . PHP_EOL;

// Reuse the contact-notification template so this exercises the real send path.
$ok = Mailer::send(
    $to,
    'SMTP test — ' . config('app.name'),
    'contact-notification',
    [
        'name'    => 'SMTP Test',
        'email'   => (string) config('mail.from.address'),
        'phone'   => '',
        'company' => '',
        'service' => '',
        'budget'  => '',
        'message' => 'If you are reading this in your inbox, outgoing email is working.',
        'link'    => (string) config('app.url') . '/admin',
    ]
);

if ($ok) {
    echo config('mail.driver') === 'log'
        ? "Logged to storage/logs/mail.log (driver is 'log', nothing was actually sent)." . PHP_EOL
        : "Sent. Check the {$to} inbox (and spam) for \"SMTP test\"." . PHP_EOL;
    exit(0);
}

echo 'FAILED to send. Check MAIL_* values in .env and the mailbox password.' . PHP_EOL;
echo 'Common Hostinger fixes: use port 465 with MAIL_ENCRYPTION=ssl, and make sure' . PHP_EOL;
echo 'MAIL_USERNAME is the full email address of a mailbox that actually exists.' . PHP_EOL;
exit(1);
