<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Partner Application Status</title>
</head>

<body style="margin:0;padding:0;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200"
                                height="50" style="display: block;">
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 20px;color:#333;">Hello {{ $partner->full_name ?? 'Partner' }},</h2>
                            @if ($approved)
                                <p style="margin:0 0 20px;color:#555;">
                                    Congratulations! Your partner application has been <strong>approved</strong>. You
                                    may now begin receiving tasks and benefit from the partnership.
                                </p>
                            @else
                                <p style="margin:0 0 20px;color:#555;">
                                    We’re sorry to inform you that your partner application has been
                                    <strong>rejected</strong>. Please feel free to contact support if you’d like more
                                    details.
                                </p>
                            @endif
                            <p style="text-align:center;margin:40px 0;">
                                <a href="https://useLoopFreight.com/"
                                    style="display:inline-block;padding:12px 24px;background:#421d95;;color:#fff;text-decoration:none;border-radius:6px;font-size:16px;">
                                    Visit {{ config('app.name') }}
                                </a>
                            </p>
                            <p style="color:#999;font-size:14px;">
                                If you did not submit this application, you can safely ignore this message.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr style="background:#f0f0f0;">
                        <td style="padding:20px;text-align:center;font-size:12px;color:#888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No. 3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                            <a href="https://useLoopFreight.com/"
                                style="color:#421d95;;text-decoration:none;">https://useLoopFreight.com/</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
