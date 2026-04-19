<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">
<head>
    <meta charset="UTF-8">
    <title>New Delivery Assignment</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200" height="50" style="display: block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #333;">Hello {{ $driver->name }},</h2>

                            <p style="color: #555;">
                                You have been <strong>assigned</strong> a new delivery.
                                Please review the details below.
                            </p>

                            <h3 style="color: #421d95;;">Delivery Details:</h3>
                            <ul style="color: #555;">
                                <li><strong>Pickup:</strong> {{ $delivery->pickup_location }}</li>
                                <li><strong>Drop-off:</strong> {{ $delivery->dropoff_location }}</li>
                                <li><strong>Delivery Date:</strong> {{ $delivery->delivery_date }}</li>
                                <li><strong>Time:</strong> {{ $delivery->delivery_time }}</li>
                            </ul>

                            <h3 style="color: #421d95;;">Receiver Info:</h3>
                            <ul style="color: #555;">
                                <li><strong>Name:</strong> {{ $delivery->receiver_name }}</li>
                                <li><strong>Phone:</strong> {{ $delivery->receiver_phone }}</li>
                            </ul>

                            <p style="color: #999; font-size: 14px;">
                                Please ensure timely pickup and safe delivery of the package.
                            </p>
                        </td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                            <a href="https://useLoopFreight.com/" style="color: #421d95;; text-decoration: none;">https://useLoopFreight.com/</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
