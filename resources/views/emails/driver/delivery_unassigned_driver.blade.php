<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Delivery Unassigned</title>
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
                            <h2 style="color: #333;">Hello {{ $driver->name }},</h2>
                            <p style="color: #555;">
                                Please be informed that the delivery previously assigned to you has been reassigned.
                            </p>

                            <h3 style="color: #421d95;;">Unassigned Delivery:</h3>
                            <ul style="color: #555;">
                                <li><strong>Tracking Number:</strong> {{ $delivery->tracking_number }}</li>
                                <li><strong>Pickup Location:</strong> {{ $delivery->pickup_location }}</li>
                                <li><strong>Drop-off Location:</strong> {{ $delivery->dropoff_location }}</li>
                                <li><strong>Package:</strong> {{ $delivery->package_description }}</li>
                            </ul>

                            <p style="color: #999; font-size: 14px;">
                                If you have any questions, please contact the operations team.
                            </p>
                        </td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Lekki, Lagos.<br>
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
