<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debt Summary</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 10px;">
                    <tr style="background-color: #421d95;">
                        <td align="center" style="padding: 30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="Company Logo" width="200">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #333;">Hello {{ $summary['partner_name'] }},</h2>

                            <p>Your updated debt summary is shown below:</p>

                            <ul>
                                <li><strong>Total Deliveries:</strong> {{ $summary['data']['total_deliveries'] }}</li>
                                <li><strong>Logistics Entitled:</strong> ₦{{ $summary['data']['logistics_entitled'] }}</li>
                                <li><strong>Customer Paid:</strong> ₦{{ $summary['data']['customer_paid'] }}</li>
                                <li><strong>Subsidy Covered:</strong> ₦{{ $summary['data']['subsidy_covered'] }}</li>
                                <li><strong>Total Amount Owed:</strong> ₦{{ number_format($summary['data']['fund_reconciliation']['total_amount_owed'], 2) }}</li>
                                <li><strong>Paid Amount:</strong> ₦{{ number_format($summary['data']['fund_reconciliation']['paid_amount'], 2) }}</li>
                                <li><strong>Balance Owed:</strong> ₦{{ number_format($summary['data']['fund_reconciliation']['balance_owed'], 2) }}</li>
                                <li><strong>Status:</strong> {{ $summary['data']['fund_reconciliation']['status'] }}</li>
                            </ul>

                            <p>Thanks for partnering with us!</p>
                        </td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <td style="padding: 20px; text-align: center; font-size: 12px; color: #888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                            <a href="https://useloopfreight.com/" style="color: #421d95; text-decoration: none;">
                                https://useloopfreight.com/
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
