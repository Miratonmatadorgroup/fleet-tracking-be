<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">
<head>
    <meta charset="UTF-8">
    <title>Ride Booked Successfully</title>
</head>

<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" width="200" height="50" alt="Logo">
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #333;">Hello {{ $name }},</h2>

                            <p style="margin: 0 0 20px; color: #555;">
                                Your ride has been successfully booked! Below are the details of your reservation.
                                A driver will be assigned shortly.
                            </p>

                            <!-- Ride Details Card -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                   style="background:#fafafa; border:1px solid #eee; border-radius:6px; padding:20px;">
                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Reference ID:</strong> {{ $ride->id }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Transport Mode:</strong>
                                        {{ ucfirst($ride->transportMode?->mode ?? 'N/A') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Added Fare Category:</strong>
                                        {{ ucfirst($ride->ride_pool_category ?? 'None') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Duration:</strong>
                                        {{ $ride->duration ? $ride->duration.' minutes' : 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Ride Date:</strong>
                                        {{ $ride->ride_date->format('d M, Y h:i A') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Status:</strong>
                                        {{ ucfirst($ride->status->value) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Payment Status:</strong>
                                        {{ ucfirst($ride->payment_status->value) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Pickup:</strong>
                                        {{ is_array($ride->pickup_location) ? json_encode($ride->pickup_location) : $ride->pickup_location }}
                                    </td>
                                </tr>

                                @if($ride->dropoff_location)
                                <tr>
                                    <td style="padding-bottom:10px; color:#333;">
                                        <strong>Dropoff:</strong>
                                        {{ is_array($ride->dropoff_location) ? json_encode($ride->dropoff_location) : $ride->dropoff_location }}
                                    </td>
                                </tr>
                                @endif

                                <tr>
                                    <td style="color:#333; padding-bottom:10px;">
                                        <strong>Estimated Cost:</strong>
                                        ₦{{ number_format($ride->estimated_cost, 2) }}
                                    </td>
                                </tr>
                            </table>

                            <!-- Button -->
                            <p style="text-align: center; margin: 40px 0;">
                                <a href="https://useLoopFreight.com/"
                                    style="display: inline-block; padding: 12px 24px; background-color: #421d95; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">
                                    View Booking
                                </a>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No. 3 John Great Court, Chevron Alternative Route, Lekki, Lagos.<br>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
