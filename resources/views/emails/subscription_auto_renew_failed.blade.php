<!DOCTYPE html>
<html lang="en" style="margin:0; padding:0;">
<head>
    <meta charset="UTF-8">
    <title>Subscription Payment Failed</title>
</head>

<body style="margin:0; padding:0; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color:#f5f7fa;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f7fa; padding:40px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0"
                style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05);">

                {{-- Header --}}
                <tr style="background-color:#421d95;">
                    <td align="center" style="padding:30px;">
                        <img src="{{ asset('assets/images/white-logo.png') }}"
                             alt="{{ config('app.name') }}" width="200" height="50" style="display:block;">
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:40px;">
                        <h2 style="color:#333;">
                            Hello {{ $payment->user->name ?? 'Customer' }},
                        </h2>

                        <p style="color:#555; font-size:16px; line-height:1.6;">
                            Your subscription renewal payment of
                            <strong>₦{{ number_format($payment->amount, 2) }}</strong>
                            failed
                        </p>


                        {{-- Subscription Details --}}
                        <h3 style="color:#421d95; margin-top:30px;">Subscription Details</h3>
                        <ul style="color:#555; padding-left:18px; line-height:1.8;">
                            <li>
                                <strong>Plan:</strong> {{ $subPlan->name }}
                            </li>
                            <li>
                                <strong>Billing Cycle:</strong>
                                {{ ucfirst($subPlan->billing_cycle->value) }}
                            </li>
                            <li>
                                <strong>Renewal Amount:</strong>
                                ₦{{ number_format($payment->amount, 2) }}
                            </li>
                            <li>
                                <strong>Payment Reference:</strong>
                                {{ $payment->reference }}
                            </li>
                            <li>
                                <strong>Payment Date:</strong>
                                {{ $payment->created_at->format('d M, Y • h:i A') }}
                            </li>
                            <li>
                                <strong>Expires On:</strong>
                                {{ optional($payment->subscription?->end_date)->format('d M, Y') ?? '—' }}
                            </li>
                        </ul>

                        {{-- Features --}}
                        @if(!empty($subPlan->features))
                            <h3 style="color:#421d95; margin-top:30px;">Plan Features</h3>
                            <ul style="color:#555; padding-left:18px; line-height:1.8;">
                                @foreach($subPlan->features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <p style="margin-top:30px; color:#777; font-size:15px;">
                            You will need to renew your subscription to start enjoying benefits immediately.
                        </p>

                        <p style="color:#999; font-size:14px;">
                            If you did not authorize this payment, please contact our support team immediately.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr style="background-color:#f0f0f0;">
                    <td style="padding:20px; text-align:center; font-size:12px; color:#888;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                        No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.<br>
                        <a href="{{ config('app.url') }}"
                           style="color:#421d95; text-decoration:none;">
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
