<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $toolName }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#4f46e5,#0ea5e9);padding:28px 32px;">
                            <span style="display:inline-block;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#c7d2fe;font-weight:600;">Hiremium&nbsp;&middot;&nbsp;Free tools</span>
                            <h1 style="margin:8px 0 0;color:#ffffff;font-size:23px;line-height:1.3;">Your result is ready to open</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 8px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Thanks for using the <strong>{{ $toolName }}</strong>. Open your report with the button
                                below &mdash; it starts the analysis and takes about a minute.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px auto 24px;">
                                <tr>
                                    <td align="center" style="border-radius:10px;background:#4f46e5;">
                                        <a href="{{ $resultUrl }}" target="_blank"
                                           style="display:inline-block;padding:14px 30px;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">
                                            Open my result
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            @if ($expiryHours > 0)
                                <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#64748b;">
                                    Please open it within <strong>{{ $expiryHours }} hours</strong>. Once opened, the link is yours to keep and share &mdash; it stops expiring.
                                </p>
                            @endif

                            <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#64748b;">
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                <span style="word-break:break-all;color:#4f46e5;">{{ $resultUrl }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px 28px;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                You're receiving this because this address was entered on {{ config('app.name', 'Hiremium') }} to run a free check.
                                If that wasn't you, you can safely ignore this email &mdash; nothing is processed until the link is opened.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
