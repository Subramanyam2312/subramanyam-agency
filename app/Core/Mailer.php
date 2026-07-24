<?php

declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/**
 * Outgoing mail over SMTP.
 *
 * PHP's mail() is never used: on shared hosting it sends without authentication
 * from an IP with no reputation, and the result is a spam folder.
 *
 * The From address must be on the site's own domain regardless of where the mail is
 * delivered. Sending "from" a gmail.com address through any non-Google server fails
 * Gmail's DMARC policy outright, so notifications would silently vanish.
 *
 * MAIL_DRIVER=log writes the rendered message to storage/logs/mail.log instead of
 * sending it, which is what local development uses.
 */
final class Mailer
{
    /**
     * @param array<string,mixed> $data    Data for the email template.
     * @param array<string,mixed> $options reply_to, reply_to_name, to_name, cc, bcc
     */
    public static function send(string $to, string $subject, string $view, array $data = [], array $options = []): bool
    {
        $html = View::render('emails/' . $view, array_merge($data, ['subject' => $subject]));
        $text = self::toPlainText($html);

        if (config('mail.driver') === 'log') {
            return self::writeToLog($to, $subject, $html, $options);
        }

        try {
            $mailer = new PHPMailer(true);

            $mailer->isSMTP();
            $mailer->Host       = (string) config('mail.host');
            $mailer->Port       = (int) config('mail.port', 587);
            $mailer->SMTPAuth   = true;
            $mailer->Username   = (string) config('mail.username');
            $mailer->Password   = (string) config('mail.password');
            $mailer->CharSet    = PHPMailer::CHARSET_UTF8;
            $mailer->Timeout    = 15;

            $encryption = (string) config('mail.encryption', 'tls');

            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mailer->SMTPSecure  = '';
                $mailer->SMTPAutoTLS = false;
            }

            $mailer->setFrom((string) config('mail.from.address'), (string) config('mail.from.name'));
            $mailer->addAddress($to, (string) ($options['to_name'] ?? ''));

            if (!empty($options['reply_to'])) {
                $mailer->addReplyTo((string) $options['reply_to'], (string) ($options['reply_to_name'] ?? ''));
            }

            foreach ((array) ($options['cc'] ?? []) as $cc) {
                $mailer->addCC((string) $cc);
            }

            foreach ((array) ($options['bcc'] ?? []) as $bcc) {
                $mailer->addBCC((string) $bcc);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body    = $html;
            $mailer->AltBody = $text;

            if (config('app.debug')) {
                $mailer->SMTPDebug   = SMTP::DEBUG_OFF;
            }

            return $mailer->send();
        } catch (Throwable $e) {
            // A failed notification must not surface as a 500 to a visitor who just
            // submitted the contact form — their message is already saved.
            error_log('Mail send failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function writeToLog(string $to, string $subject, string $html, array $options): bool
    {
        $path = STORAGE_PATH . '/logs';

        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }

        $entry = sprintf(
            "==================== %s ====================\nTo: %s\nReply-To: %s\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            (string) ($options['reply_to'] ?? '—'),
            $subject,
            self::toPlainText($html)
        );

        return file_put_contents($path . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function toPlainText(string $html): string
    {
        $text = preg_replace('/<(head|style|script)\b[^>]*>.*?<\/\1>/si', '', $html) ?? $html;
        $text = preg_replace('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/si', '$2 ($1)', $text) ?? $text;
        $text = preg_replace('/<\/(p|div|h[1-6]|tr|li)>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
