<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geofence Alert</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">

        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">

                    {{-- Header --}}
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">

                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="{{ config('app.name') }}"
                                width="200" style="display: block;">

                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px;">

                            <h2 style="margin: 0 0 20px; color: #333;">
                                Geofence Alert
                            </h2>

                            <p style="margin: 0 0 20px; color: #555; font-size: 15px;">
                                Hello {{ $user->name ?? 'User' }},
                            </p>

                            @if ($eventType === 'entry')

                                <p style="margin: 0 0 20px; color: #555; font-size: 15px;">
                                    Your vehicle has
                                    <strong style="color: #218838;">
                                        entered
                                    </strong>
                                    the geofenced area.
                                </p>

                            @else

                                <p style="margin: 0 0 20px; color: #555; font-size: 15px;">
                                    Your vehicle has
                                    <strong style="color: #c82333;">
                                        exited
                                    </strong>
                                    the geofenced area.
                                </p>

                            @endif

                            {{-- Alert Panel --}}
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="margin: 25px 0; background-color: #f8f7fc; border-left: 4px solid #421d95;">

                                <tr>
                                    <td style="padding: 20px;">

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Vehicle:</strong>
                                            {{ $asset->license_plate }}
                                        </p>

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Geofence:</strong>
                                            {{ $geofence->name }}
                                        </p>

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Event:</strong>
                                            {{ ucfirst($eventType) }}
                                        </p>

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Radius:</strong>
                                            {{ number_format($geofence->radius_meters) }} meters
                                        </p>

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Latitude:</strong>
                                            {{ $asset->last_known_lat }}
                                        </p>

                                        <p style="margin: 0 0 10px; color: #555;">
                                            <strong>Longitude:</strong>
                                            {{ $asset->last_known_lng }}
                                        </p>

                                        <p style="margin: 0; color: #555;">
                                            <strong>Time:</strong>
                                            {{ now()->format('d M Y, h:i A') }}
                                        </p>

                                    </td>
                                </tr>

                            </table>

                            {{-- Location Button --}}
                            <p style="text-align: center; margin: 35px 0;">

                                <a href="{{ config('app.url') }}" style="display: inline-block;
                                           padding: 12px 24px;
                                           background-color: #421d95;
                                           color: #ffffff;
                                           text-decoration: none;
                                           border-radius: 6px;
                                           font-size: 16px;">

                                    View Vehicle Location

                                </a>

                            </p>

                            <p style="margin: 0; color: #999; font-size: 14px;">
                                This is an automated geofence notification from
                                {{ config('app.name') }}.
                            </p>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">

                            &copy; {{ date('Y') }}
                            {{ config('app.name') }}.
                            All rights reserved.

                            <br><br>

                            No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.

                            <br><br>

                            <a href="{{ config('app.url') }}" style="color: #421d95; text-decoration: none;">
                                {{ config('app.url') }}
                            </a>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>

    </table>

</body>

</html>