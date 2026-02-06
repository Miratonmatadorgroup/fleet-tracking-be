<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Completed</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f7fa; padding: 40px 0;">

<table align="center" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
    <tr style="background-color: #421d95;">
        <td align="center" style="padding: 30px;">
            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200" height="50">
        </td>
    </tr>

    <tr>
        <td style="padding: 40px;">
            <h2>
                @if($recipientType === 'customer')
                    Hello {{ $delivery->customer_name }},
                @elseif($recipientType === 'driver')
                    Hello {{ $delivery->driver?->name ?? 'Driver' }},
                @endif
            </h2>

            <p>
                @if($recipientType === 'customer')
                    Your delivery with tracking number <strong>{{ $delivery->tracking_number }}</strong>
                    has been successfully completed and confirmed.
                @elseif($recipientType === 'driver')
                    Delivery with tracking number <strong>{{ $delivery->tracking_number }}</strong>
                    has been confirmed as completed. Thank you for your service.
                @endif
            </p>

            <h3>Delivery Summary:</h3>
            <ul>
                <li><strong>Pickup:</strong> {{ $delivery->pickup_location }}</li>
                <li><strong>Drop-off:</strong> {{ $delivery->dropoff_location }}</li>
                <li><strong>Delivery Date:</strong> {{ $delivery->delivery_date }}</li>
                <li><strong>Time:</strong> {{ $delivery->delivery_time }}</li>
                <li><strong>Status:</strong> Completed</li>
            </ul>

            <h3>Driver Info:</h3>
            <ul>
                <li><strong>Name:</strong> {{ $delivery->driver?->name ?? 'N/A' }}</li>
                <li><strong>Phone:</strong> {{ $delivery->driver?->phone ?? 'N/A' }}</li>
                <li><strong>Email:</strong> {{ $delivery->driver?->email ?? 'N/A' }}</li>
                <li><strong>Whatsapp:</strong> {{ $delivery->driver?->whatsapp_number ?? 'N/A' }}</li>
            </ul>

            <p style="text-align:center; margin-top: 30px;">
                <a href="https://useLoopFreight.com/" style="padding: 12px 24px; background-color: #421d95;; color: #fff; text-decoration: none; border-radius: 6px;">
                    View Delivery History
                </a>
            </p>
        </td>
    </tr>

    <tr style="background-color: #f0f0f0;">
        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
            <a href="https://useLoopFreight.com/">https://useLoopFreight.com/</a>
        </td>
    </tr>
</table>

</body>
</html>
