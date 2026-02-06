<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Payment Received</title>
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
                            <h2 style="color: #333;">Hello {{ $delivery->customer->name }},</h2>
                            <p style="color: #555;">Your payment of
                                <strong>₦{{ number_format($payment->amount, 2) }}</strong> is successful and your
                                delivery has been booked. We
                                are currently assigning a driver to your request. You will be notified once a driver is
                                assigned.</p>

                            <h3 style="color: #421d95;;">Delivery Details:</h3>
                            <ul style="color: #555;">
                                <li><strong>Tracking Number:</strong> {{ $delivery->tracking_number }}</li>
                                <li><strong>Waybill Number:</strong> {{ $delivery->waybill_number }}</li>
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

                            <p style="margin-top: 30px; color: #777; font-size: 15px;">We appreciate your patience and
                                will ensure your delivery is assigned promptly.</p>

                            <p style="text-align: center; margin: 40px 0;">
                                <a href="https://useLoopFreight.com/"
                                    style="padding: 12px 24px; background-color: #421d95;; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">Track
                                    Your Delivery</a>
                            </p>

                            <p style="color: #999; font-size: 14px;">If you did not initiate this request, you can
                                safely ignore this message.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                            <a href="https://useLoopFreight.com/"
                                style="color: #421d95;; text-decoration: none;">https://useLoopFreight.com/</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
