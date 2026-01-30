<?php
namespace App\DTOs\CommissionSettings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateCommissionSettingsDTO
{
    public array $commissions;

    public function __construct(array $commissions)
    {
        $this->commissions = $commissions;
    }
    
    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'commissions' => 'required|array|min:1',
            'commissions.*.role' => 'required|string|distinct',
            'commissions.*.percentage' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self($request->input('commissions'));
    }
}
