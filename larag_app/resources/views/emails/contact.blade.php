<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New contact message</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(15,23,42,.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="background:linear-gradient(120deg,#4f46e5,#7c3aed); padding:28px 32px;" align="left">
                            <img src="{{ $message->embed(public_path('images/email-logo.png')) }}" alt="{{ config('app.name') }}" height="34" style="height:34px; width:auto; display:block; background:#ffffff; padding:6px 10px; border-radius:8px;">
                        </td>
                    </tr>

                    {{-- Title --}}
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <div style="font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6366f1;">New contact message</div>
                            <h1 style="margin:8px 0 0; font-size:20px; font-weight:800; color:#0f172a;">{{ $subjectText }}</h1>
                        </td>
                    </tr>

                    {{-- Details --}}
                    <tr>
                        <td style="padding:16px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px; border-bottom:1px solid #eef2f7; font-size:13px; color:#64748b; width:110px;">Topic</td>
                                    <td style="padding:14px 16px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $topicLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; border-bottom:1px solid #eef2f7; font-size:13px; color:#64748b;">From</td>
                                    <td style="padding:14px 16px; border-bottom:1px solid #eef2f7; font-size:14px; color:#0f172a;">{{ $senderName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; font-size:13px; color:#64748b;">Email</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#0f172a;">
                                        <a href="mailto:{{ $senderEmail }}" style="color:#4f46e5; text-decoration:none;">{{ $senderEmail }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Message --}}
                    <tr>
                        <td style="padding:16px 32px 28px;">
                            <div style="font-size:13px; font-weight:600; color:#64748b; margin-bottom:8px;">Message</div>
                            <div style="font-size:14px; line-height:1.7; color:#334155; white-space:pre-wrap; word-break:break-word;">{{ $messageBody }}</div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:12px; color:#94a3b8;">
                                Reply directly to this email to respond to {{ $senderName }}.
                                <br>Sent from the {{ config('app.name') }} contact form.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
