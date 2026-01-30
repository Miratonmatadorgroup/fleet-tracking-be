<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Wallet Debited</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200"
                                height="50" style="display: block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #333;">Hello {{ $user->name ?? 'Customer' }},</h2>

                            <p style="color: #555;">
                                {{ $summary }}
                            </p>
                            
                            <p style="color: #555;">
                                <strong>Transaction Reference:</strong> {{ $transaction->reference }}<br>
                                <strong>Method:</strong> {{ $transaction->method }}
                                <strong>Description:</strong> {{ $transaction->description }}

                            </p>

                            <p style="text-align: center; margin: 40px 0;">
                                <a href="https://useloopfreight.com/"
                                    style="padding: 12px 24px; background-color: #421d95;; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">Go
                                    to Dashboard</a>
                            </p>

                            <p style="color: #999; font-size: 14px;">
                                If you did not initiate this transaction or suspect an issue, please contact our support
                                team immediately.
                            </p>

                            <p style="color: #555; margin-top: 40px;">
                                Thanks,<br>
                                {{ config('app.name') }} Team
                            </p>
                        </td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                            <a href="https://useloopfreight.com/"
                                style="color: #421d95;; text-decoration: none;">https://useloopfreight.com/</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
