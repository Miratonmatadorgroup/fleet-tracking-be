<?php
namespace App\DTOs\Payout;



use Illuminate\Http\Request;

class ListPayoutsDTO
{
    public const ALLOWED_PAYOUT_FILTERS = [
        'user_id', 'driver_id', 'partner_id', 'investor_id',
        'amount', 'bank_name', 'account_number',
        'currency', 'status', 'provider_reference',
        'created_at', 'updated_at',
    ];

    public const ALLOWED_USER_FILTERS = [
        'user_name',
        'user_email',
        'user_phone',
        'user_whatsapp_number',
    ];

    public function __construct(
        public array $filters = [],
        public array $userFilters = [],
        public ?string $role = null,
        public int $perPage = 10
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filters = $request->only(self::ALLOWED_PAYOUT_FILTERS);
        $userFilters = $request->only(self::ALLOWED_USER_FILTERS);

        return new self(
            filters: $filters,
            userFilters: $userFilters,
            role: $request->input('role'),
            perPage: (int) $request->input('per_page', 10),
        );
    }
}

