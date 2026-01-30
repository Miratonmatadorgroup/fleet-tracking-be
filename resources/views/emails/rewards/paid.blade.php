<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>You've Received a Reward</title>
</head>

<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">

                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200" height="50">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #333;">Hello {{ $name ?? 'Driver' }},</h2>
                            <p style="color: #555;">
                                Congratulations! You've received a reward from the <strong>{{ $campaign_name }}</strong> campaign.
                            </p>

                            <div style="background-color: #f0f0f0; padding: 20px; border-radius: 6px; text-align: center; font-size: 24px; font-weight: bold; color: #421d95; margin: 20px 0;">
                                Reward Credited: ₦{{ number_format($reward_amount, 2) }}
                            </div>

                            <p style="color: #555;">You can view this in your wallet from the dashboard.</p>

                            <p style="text-align: center; margin: 40px 0;">
                                <a href="https://useloopfreight.com/" style="padding: 12px 24px; background-color: #421d95; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">
                                    Go to Dashboard
                                </a>
                            </p>

                            <p style="color: #999; font-size: 14px;">If you think this was a mistake, kindly contact support.</p>
                        </td>
                    </tr>

                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Lekki, Lagos.<br>
                            <a href="https://useloopfreight.com/" style="color: #421d95;">useloopfreight.com</a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
