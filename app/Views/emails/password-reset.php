<?php
/**
 * Password reset email.
 *
 * Styles are inline because email clients strip <style> blocks. The site's CSP does
 * not apply here — this markup never renders in a browser tab.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#0c0e12;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0c0e12;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:520px;background:#12151a;border:1px solid #2a303a;border-radius:14px;">
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <p style="margin:0;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#8c98aa;">
                                <?= e(config('app.name')) ?>
                            </p>
                            <h1 style="margin:12px 0 0;font-size:20px;line-height:1.3;color:#e2e8f0;font-weight:600;">
                                Reset your password
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#c7d0dd;">
                                Hi <?= e($name) ?>,
                            </p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#c7d0dd;">
                                Someone asked to reset the password for this account. The link below
                                works once and expires in <?= e((string) $minutes) ?> minutes.
                            </p>
                            <p style="margin:0 0 24px;">
                                <a href="<?= e($link) ?>"
                                   style="display:inline-block;background:#7aa2ff;color:#0c0e12;text-decoration:none;
                                          font-size:15px;font-weight:600;padding:12px 22px;border-radius:8px;">
                                    Choose a new password
                                </a>
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#8c98aa;">
                                If the button does not work, paste this into your browser:<br>
                                <span style="color:#7aa2ff;word-break:break-all;"><?= e($link) ?></span>
                            </p>
                            <p style="margin:0 0 32px;font-size:13px;line-height:1.6;color:#8c98aa;">
                                Didn't request this? You can ignore this email — your password stays as it is.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;border-top:1px solid #2a303a;">
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#6b7688;">
                                Sent by <?= e(config('app.name')) ?>. This is an automated message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
