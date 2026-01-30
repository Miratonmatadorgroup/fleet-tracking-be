<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Delivery;
use App\Models\Investor;
use App\Models\Commission;
use Illuminate\Support\Str;
use App\Enums\CurrencyEnums;
use App\Models\WalletTransaction;
use App\Enums\InvestorStatusEnums;
use Illuminate\Support\Facades\Log;
use App\Services\ExternalBankService;
use App\Enums\InvestorApplicationStatusEnums;

class WalletService
{
    public function __construct(
        protected ExternalBankService $externalBankService
    ) {}

    public function createForUser(
        int|string $userId,
        ?string $currency = null,
        bool $isVirtual = false,
        ?string $provider = null
    ): Wallet {
        $currencyEnum = CurrencyEnums::tryFrom($currency) ?? CurrencyEnums::NGN;

        do {
            $accountNumber = (string) mt_rand(1000000000, 9999999999);
        } while (Wallet::where('account_number', $accountNumber)->exists());

        $wallet = Wallet::create([
            'user_id'            => $userId,
            'account_number'     => $accountNumber,
            'bank_name'          => 'LoopFreight Wallet',
            'available_balance'  => 0,
            'total_balance'      => 0,
            'currency'           => $currencyEnum,
            'is_virtual_account' => $isVirtual,
            'provider'           => $provider,
        ]);

        $user = User::findOrFail($userId);

        // THIS MUST THROW IF IT FAILS
        $external = $this->externalBankService
            ->createExternalAccountForUser($user);

        $wallet->update([
            'external_account_id'        => $external['id'],
            'external_account_number' => $external['number'] ?? null,
            'external_account_name'   => $external['name'] ?? null,
            'external_available_balance'   => $external['available_balance'] ?? 0,
            'external_book_balance'   => $external['book_balance'] ?? 0,
            'external_bank'           => 'Shanono Bank',
            'external_reference'      => $external['id'],
        ]);

        return $wallet;
    }


    public static function creditCommissions(User $driverUser, Delivery $delivery)
    {
        $total = (float) $delivery->total_price;

        // Fetch latest commission settings
        $settings = Commission::latest()->first();

        $driverPercentage = Commission::where('role', 'driver')->latest()->value('percentage') ?? 10;
        $partnerPercentage = Commission::where('role', 'partner')->latest()->value('percentage') ?? 10;
        $investorPercentage = Commission::where('role', 'investor')->latest()->value('percentage') ?? 30;
        $platformPercentage = Commission::where('role', 'platform')->latest()->value('percentage') ?? 50;


        $driverCommission   = self::calculateCommission($total, $driverPercentage);
        $partnerCommission  = self::calculateCommission($total, $partnerPercentage);
        $investorCommission = self::calculateCommission($total, $investorPercentage);
        $platformCommission = self::calculateCommission($total, $platformPercentage);


        //  Credit Driver
        if ($driverUser->hasRole('driver')) {
            $wallet = $driverUser->wallet;
            $wallet->available_balance += $driverCommission;
            $wallet->total_balance += $driverCommission;

            $wallet->save();

            WalletTransaction::create([
                'wallet_id'  => $wallet->id,
                'user_id'    => $driverUser->id,
                'amount'     => $driverCommission,
                'type'       => 'credit',
                'role'       => 'driver',
                'reference'  => self::generateTransactionReference(),
                'description' => "Driver commission for delivery {$delivery->tracking_number}",
            ]);
        }

        /**
         * Credit Partner only if the driver belongs to that partner
         */
        // $partnerUser = $delivery->transport_partner ?? $delivery->driver?->partner?->user;
        $partnerUser = $delivery->getAttribute('transport_partner')
            ?? $delivery->driver?->partner?->user;


        if ($partnerUser && $partnerUser->hasRole('partner')) {
            $wallet = $partnerUser->wallet;
            $wallet->available_balance += $partnerCommission;
            $wallet->total_balance += $partnerCommission;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $partnerUser->id,
                'amount'      => $partnerCommission,
                'type'        => 'credit',
                'role'        => 'partner',
                'reference'   => self::generateTransactionReference(),
                'description' => "Partner commission for delivery {$delivery->tracking_number}",
            ]);
        }

        /**
         * Credit Investors — only if no partner is tied to the delivery
         */
        if (!$delivery->partner_id) {
            $activeInvestors = Investor::where('status', InvestorStatusEnums::ACTIVE)
                ->where('application_status', InvestorApplicationStatusEnums::APPROVED)
                ->get();

            if ($activeInvestors->count() > 0) {
                $totalInvestment = $activeInvestors->sum('investment_amount');

                foreach ($activeInvestors as $investorModel) {
                    $investorUser = $investorModel->user;

                    if ($investorUser && $investorUser->hasRole('investor') && $investorModel->investment_amount > 0) {
                        // Calculate proportional share
                        $shareRatio = $investorModel->investment_amount / $totalInvestment;
                        $individualCommission = $investorCommission * $shareRatio;

                        // Credit wallet
                        $wallet = $investorUser->wallet;
                        $wallet->available_balance += $individualCommission;
                        $wallet->total_balance += $individualCommission;
                        $wallet->save();

                        // Log wallet transaction
                        WalletTransaction::create([
                            'wallet_id'   => $wallet->id,
                            'user_id'     => $investorUser->id,
                            'amount'      => $individualCommission,
                            'type'        => 'credit',
                            'role'        => 'investor',
                            'reference'   => self::generateTransactionReference(),
                            'description' => "Investor profit share for delivery {$delivery->tracking_number}",
                            'meta'        => json_encode(['type' => 'shared_pool']),
                        ]);
                    }
                }
            }
        }


        /**
         * Credit Platform
         */

        $platform = $delivery->platform;
        if ($platform && $platform->hasRole('platform')) {
            $wallet = $platform->wallet;
            $wallet->available_balance += $platformCommission;
            $wallet->total_balance += $platformCommission;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id'  => $wallet->id,
                'user_id'    => $platform->id,
                'amount'     => $platformCommission,
                'type'       => 'credit',
                'role'       => 'platform',
                'reference'  => self::generateTransactionReference(),
                'description' => "Platform commission for delivery {$delivery->tracking_number}",
            ]);
        }
    }

    public static function generateTransactionReference(): string
    {
        return 'TRF-' . strtoupper(Str::random(10));
    }

    public static function creditReward(User $driverUser, float $amount, array $meta = []): array
    {
        $wallet = $driverUser->wallet;
        $wallet->available_balance += $amount;
        $wallet->total_balance += $amount;

        $wallet->save();

        $wt = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $driverUser->id,
            'amount' => $amount,
            'type' => 'credit',
            'role' => 'reward',
            'reference' => self::generateTransactionReference(),
            'description' => "Reward payment: " . ($meta['campaign_id'] ?? 'campaign'),
            'meta' => json_encode($meta),
        ]);

        return ['transaction_id' => $wt->id, 'reference' => $wt->reference];
    }

    private static function calculateCommission(float $total, float $percentage): float
    {
        return round(($percentage / 100) * $total, 2);
    }
}
