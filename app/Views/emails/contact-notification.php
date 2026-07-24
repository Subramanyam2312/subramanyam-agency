<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#0a0b0d;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0a0b0d;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background:#111215;border:1px solid #2c2f35;border-radius:14px;">
                    <tr>
                        <td style="padding:30px 30px 6px;">
                            <p style="margin:0;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#8e9097;">
                                New enquiry
                            </p>
                            <h1 style="margin:10px 0 0;font-size:20px;color:#ece7e1;font-weight:600;">
                                <?= e($name) ?>
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 30px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                                <?php foreach ([
                                    'Email'   => $email,
                                    'Phone'   => $phone,
                                    'Company' => $company,
                                    'Service' => $service,
                                    'Budget'  => $budget,
                                ] as $label => $value): ?>
                                    <?php if ((string) $value === '') { continue; } ?>
                                    <tr>
                                        <td style="padding:6px 0;color:#8e9097;width:90px;"><?= e($label) ?></td>
                                        <td style="padding:6px 0;color:#ece7e1;"><?= e($value) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 30px 0;">
                            <div style="border:1px solid #2c2f35;border-radius:10px;padding:16px;background:#181a1e;">
                                <p style="margin:0 0 8px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#8e9097;">
                                    Message
                                </p>
                                <p style="margin:0;font-size:14px;line-height:1.65;color:#d6d1c9;white-space:pre-line;"><?= e($message) ?></p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 30px 30px;">
                            <a href="<?= e($link) ?>"
                               style="display:inline-block;background:#e6dfd4;color:#0a0b0d;text-decoration:none;
                                      font-size:14px;font-weight:600;padding:11px 20px;border-radius:999px;">
                                Open in the portal
                            </a>
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#6b6f77;">
                                Reply directly to this email to answer <?= e($name) ?> — the reply-to address is set to theirs.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
