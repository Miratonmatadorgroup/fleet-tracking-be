<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Refund Processed Successfully</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f5f7fa; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
        <tr style="background-color: #421d95;">
            <td align="center" style="padding: 20px;">
                <img src="{{ asset('assets/images/white-logo.png') }}" width="180"
                    alt="{{ config('app.name') }} Logo">
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <h2 style="color: #333;">Hello {{ $name ?? 'Investor' }},</h2>
                <p style="color: #555;">We’re pleased to inform you that your withdrawal refund has been processed
                    successfully.</p>
                <p style="color: #555;">The refunded amount should reflect in your designated account shortly.</p>
                <p style="color: #999; font-size: 14px;">Thank you for investing with
                    <strong>{{ config('app.name') }}</strong>.</p>
            </td>
        </tr>

        <!-- Footer -->
        <tr style="background-color: #f0f0f0;">
            <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                <a href="https://useLoopFreight.com/" style="color: #421d95; text-decoration: none;">
                    https://useLoopFreight.com/
                </a>
            </td>
        </tr>
    </table>
</body>

</html>
