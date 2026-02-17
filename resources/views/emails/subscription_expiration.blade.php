<!DOCTYPE html>
<html lang="en" style="margin:0; padding:0;">

<head>
    <meta charset="UTF-8">
    <title>Subscription Expired</title>
</head>

<body
    style="margin:0; padding:0; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color:#f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f7fa; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05);">

                    {{-- Header --}}
                    <tr style="background-color:#421d95;">
                        <td align="center" style="padding:30px;">
                            <img src="{{ asset('assets/images/white-logo.png') }}" alt="{{ config('app.name') }}"
                                width="200" height="50" style="display:block;">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:40px;">
                            @php
                                $user = $subscription->user;
                                $subPlan = $subscription->plan;
                            @endphp

                            <h2 style="color:#333;">Hello {{ $user->name ?? 'Customer' }},</h2>

                            <p style="color:#555; font-size:16px; line-height:1.6;">
                                Your subscription has <strong>Expired</strong>.
                            </p>

                            {{-- Subscription Details --}}
                            <h3 style="color:#421d95; margin-top:30px;">Subscription Details</h3>
                            <ul style="color:#555; padding-left:18px; line-height:1.8;">
                                <li><strong>Plan:</strong> {{ $subPlan->name }}</li>
                                <li><strong>Billing Cycle:</strong> {{ ucfirst($subPlan->billing_cycle->value) }}</li>
                                <li><strong>Renewal Amount:</strong> ₦{{ number_format($subPlan->price, 2) }}</li>
                                <li><strong>Expires On:</strong>
                                    {{ optional($subscription->end_date)->format('d M, Y') ?? '—' }}</li>
                            </ul>

                            {{-- Features --}}
                            @if (!empty($subPlan->features))
                                <h3 style="color:#421d95; margin-top:30px;">Plan Features</h3>
                                <ul style="color:#555; padding-left:18px; line-height:1.8;">
                                    @foreach ($subPlan->features as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <p style="margin-top:30px; color:#777; font-size:15px;">
                                Please renew your subscription to continue enjoying premium features.
                            </p>

                            <p style="margin-top:20px; color:#555; font-size:16px;">
                                <a href="{{ route('subscription.plans') }}"
                                    style="background:#421d95; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none;">
                                    Renew Now
                                </a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr style="background-color:#f0f0f0;">
                        <td style="padding:20px; text-align:center; font-size:12px; color:#888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                            <a href="{{ config('app.url') }}" style="color:#421d95; text-decoration:none;">
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
