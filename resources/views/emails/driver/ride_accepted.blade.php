<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Your Ride Has Been Accepted</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" width="200" height="50"
                                alt="Logo">
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px; color:#333;">

                            <h2 style="margin: 0 0 20px;">Hello {{ $user->name }},</h2>

                            <p style="margin: 0 0 15px;">
                                Great news! A driver has accepted your ride request.
                            </p>

                            <p style="margin:0 0 10px;">
                                <strong>Driver:</strong> {{ $driver->name }} ({{ $driver->phone }})
                            </p>

                            <p style="margin:0 0 20px;">
                                <strong>Ride Reference:</strong> {{ $ride->id }} <br>
                                <strong>Status:</strong> In Transit <br>
                                The driver is now heading to your pickup location.
                            </p>

                            <!-- Transport Mode Section -->
                            <h3 style="margin:30px 0 10px; color:#421d95;">Transport Mode Details</h3>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background:#fafafa; border:1px solid #eee; border-radius:6px; padding:20px;">

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Type:</strong>
                                        {{ ucfirst($ride->transportMode?->type?->value ?? 'N/A') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Manufacturer:</strong>
                                        {{ $ride->transportMode?->manufacturer ?? 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Model:</strong>
                                        {{ $ride->transportMode?->model ?? 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Color:</strong>
                                        {{ $ride->transportMode?->color ?? 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Plate Number:</strong>
                                        {{ $ride->transportMode?->registration_number ?? 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0; color:#333;"><strong>Passenger Capacity:</strong>
                                        {{ $ride->transportMode?->passenger_capacity ?? 'N/A' }}
                                    </td>
                                </tr>


                                @if ($ride->transportMode?->photo_path)
                                    <tr>
                                        <td style="padding:10px 0; text-align:center;">
                                            <img src="{{ Storage::disk('public')->url($ride->transportMode->photo_path) }}"
                                                alt="Transport Photo"
                                                style="max-width:100%; border-radius:6px; border:1px solid #ddd;">
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <!-- Ride Details -->
                            <h3 style="margin:40px 0 10px; color:#421d95;">Ride Details</h3>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background:#fafafa; border:1px solid #eee; border-radius:6px; padding:20px;">

                                <tr>
                                    <td style="padding:8px 0;"><strong>Pickup:</strong>
                                        {{ is_array($ride->pickup_location) ? json_encode($ride->pickup_location) : $ride->pickup_location }}
                                    </td>
                                </tr>

                                @if ($ride->dropoff_location)
                                    <tr>
                                        <td style="padding:8px 0;"><strong>Dropoff:</strong>
                                            {{ is_array($ride->dropoff_location) ? json_encode($ride->dropoff_location) : $ride->dropoff_location }}
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="padding:8px 0;"><strong>Category:</strong>
                                        {{ ucfirst($ride->ride_pool_category ?? 'None') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0;"><strong>Duration:</strong>
                                        {{ $ride->duration ? $ride->duration . ' minutes' : 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0;"><strong>Ride Date:</strong>
                                        {{ $ride->ride_date->format('d M, Y h:i A') }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 0;"><strong>Total Cost:</strong>
                                        ₦{{ number_format($ride->estimated_cost, 2) }}
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <p style="text-align:center; margin: 40px 0;">
                                <a href="https://useLoopFreight.com/"
                                    style="display:inline-block; padding:12px 24px; background-color:#421d95; color:#fff;
                                          text-decoration:none; border-radius:6px; font-size:16px;">
                                    View Booking
                                </a>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr style="background-color:#f0f0f0;">
                        <td style="padding:20px; text-align:center; font-size:12px; color:#888;">
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
