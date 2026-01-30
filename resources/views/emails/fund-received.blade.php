<!DOCTYPE html>
<html lang="en" style="margin: 0; padding: 0;">

<head>
    <meta charset="UTF-8">
    <title>Payment Logged</title>
</head>

<body style="margin:0;padding:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;background-color:#f5f7fa;">

    <!DOCTYPE html>

    <html lang="en" style="margin: 0; padding: 0;">

    <head>
        <meta charset="UTF-8">
        <title>Funds Received</title>
    </head>

    <body style="margin:0;padding:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;background-color:#f5f7fa;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f7fa;padding:40px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0"
                        style="background-color:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,0.05);">

                        <!-- Header -->
                        <tr style="background-color:#421d95;">
                            <td align="center" style="padding:30px;">
                                <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200"
                                    height="50">
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding:40px;">
                                <h2 style="margin:0 0 20px;color:#333;">
                                    Funds Received From {{ $payload['api_client'] ?? 'External Client' }}
                                </h2>
                                <p style="margin:0 0 20px;color:#555;">
                                    This is to acknowledge receipt of payment from
                                    {{ $payload['api_client'] ?? 'External Client' }}.
                                </p>

                                <table width="100%" cellpadding="10" cellspacing="0"
                                    style="border-collapse:collapse;margin:20px 0;">
                                    <tr style="background-color:#f9f9f9;">
                                        <td style="border:1px solid #ddd;">Fund ID</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;">
                                            {{ $payload['fund_id'] ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="border:1px solid #ddd;">Received Amount</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;color:#421d95;">
                                            ₦{{ number_format((float) ($payload['received_amount'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border:1px solid #ddd;">Paid Amount</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;color:#421d95;">
                                            ₦{{ number_format((float) ($payload['paid_amount'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    <tr style="background-color:#f9f9f9;">
                                        <td style="border:1px solid #ddd;">Total Amount Owed</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;">
                                            ₦{{ number_format((float) ($payload['total_amount_owed'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border:1px solid #ddd;">Balance Owed</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;">
                                            ₦{{ number_format((float) ($payload['balance_owed'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    <tr style="background-color:#f9f9f9;">
                                        <td style="border:1px solid #ddd;">Status</td>
                                        <td style="border:1px solid #ddd;font-weight:bold;color:#0a7;">
                                            {{ strtoupper($payload['status']->value ?? 'N/A') }}
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:20px 0;color:#555;">
                                    Please log in to the LoopFreight Admin dashboard for more details.
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr style="background-color:#f0f0f0;">
                            <td style="padding:20px;text-align:center;font-size:12px;color:#888;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                                No.3 John Great Court, Chevron, Lekki, Lagos.<br>
                                <a href="https://useloopfreight.com/"
                                    style="color:#421d95;text-decoration:none;">https://useloopfreight.com/</a>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>

    </html>


</body>

</html>
