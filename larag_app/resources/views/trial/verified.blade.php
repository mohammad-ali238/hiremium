<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Free trial activated</title>
</head>
<body style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;color:#1e293b;">
    <div style="max-width:520px;margin:64px auto;background:#fff;border-radius:14px;padding:40px 36px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.08);">
        <div style="width:64px;height:64px;margin:0 auto 20px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;">
            <span style="font-size:32px;color:#16a34a;">&#10003;</span>
        </div>
        <h1 style="margin:0 0 10px;font-size:22px;">Your free AI credits are active</h1>
        <p style="margin:0 0 6px;font-size:15px;color:#475569;line-height:1.6;">
            {{ (int) $trial->credits_total }} credits have been added and are valid until
            {{ optional($trial->expires_at)->format('F j, Y') }}.
        </p>
        <p style="margin:18px 0 0;font-size:13px;color:#94a3b8;">
            You can now return to your WordPress dashboard &mdash; the Workeera plugin will detect your activation automatically.
        </p>
    </div>
</body>
</html>
