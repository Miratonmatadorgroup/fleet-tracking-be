<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Driver Assigned</title>
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
                            <h2 style="margin: 0 0 20px; color: #333;">Hello {{ $driver->name }},</h2>

                            <p style="margin: 0 0 20px; color: #555;">
                                You have been assigned to a transport mode with the following details:
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="font-size: 15px; color: #555; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Type:</strong></td>
                                    <td>{{ ucfirst($transport->type->value) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Manufacturer:</strong></td>
                                    <td>{{ $transport->manufacturer }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Model:</strong></td>
                                    <td>{{ $transport->model }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Registration Number:</strong></td>
                                    <td>{{ $transport->registration_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Year of Manufacture:</strong></td>
                                    <td>{{ $transport->year_of_manufacture }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Passenger Capacity:</strong></td>
                                    <td>{{ $transport->passenger_capacity }}</td>
                                </tr>


                            </table>
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
