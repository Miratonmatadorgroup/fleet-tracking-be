<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Fleet Application {{ ucfirst($status) }}</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f7fa;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200"
                                height="50" style="display: block;">

                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #333;">Hello
                                {{ $partner->full_name ?? ($partner->user->name ?? 'Partner') }},</h2>


                            @if ($status === 'approved')
                                <p style="margin: 0 0 20px; color: #555;">
                                    The driver application for <strong>{{ $driver->name }}</strong> <br>
                                    with Email:({{ $driver->email }}) and <br>
                                    Phone Number:({{ $driver->phone }}) <br>
                                    Whatsapp_Number:({{ $driver->whatsapp_number }}) <br>
                                    has been <strong style="color: #421d95;;">approved</strong>.
                                </p>
                            @else
                                <p style="margin: 0 0 20px; color: #555;">
                                    The driver application for <strong>{{ $driver->name }}</strong><br>
                                    with Email:({{ $driver->email }}) and <br>
                                    Phone Number:({{ $driver->phone }}) <br>
                                    Whatsapp_Number:({{ $driver->whatsapp_number }}) <br>
                                    has been <strong style="color: #e74c3c;">rejected</strong>.
                                </p>
                            @endif

                            <h3
                                style="margin: 25px 0 15px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                Transport Details</h3>
                            <table style="width: 100%; margin-bottom: 25px;">

                                <tr>
                                    <td width="120" style="padding: 5px 0; color: #777;">Manufacturer:</td>
                                    <td style="padding: 5px 0;"><strong>{{ $transport->manufacturer }}</strong></td>
                                </tr>

                                <tr>
                                    <td width="120" style="padding: 5px 0; color: #777;">Type:</td>
                                    <td style="padding: 5px 0;"><strong>{{ $transport->type }}</strong></td>
                                </tr>
                                <tr>
                                    <td width="120" style="padding: 5px 0; color: #777;">Color:</td>
                                    <td style="padding: 5px 0;"><strong>{{ $transport->color }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #777;">Model:</td>
                                    <td style="padding: 5px 0;"><strong>{{ $transport->model }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #777;">Registration:</td>
                                    <td style="padding: 5px 0;"><strong>{{ $transport->registration_number }}</strong>
                                    </td>
                                </tr>
                            </table>

                            @if ($status === 'approved')
                                <p style="margin: 0 0 25px; color: #555;">
                                    Your driver and vehicle are now active in the system.
                                </p>
                            @else
                                <p style="margin: 0 0 25px; color: #555;">
                                    Please review the application and submit a new one if needed.
                                </p>
                            @endif

                            <!-- Button -->
                            <p style="text-align: center; margin: 30px 0;">
                                <a href="https://useloopfreight.com/"
                                    style="display: inline-block; padding: 12px 24px; background-color: #421d95;; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">
                                    View Dashboard
                                </a>
                            </p>

                            <p
                                style="color: #999; font-size: 14px; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                                If you have any questions, please contact our support team.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
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
