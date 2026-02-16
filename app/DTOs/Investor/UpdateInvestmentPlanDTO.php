<?php
namespace App\DTOs\Investor;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateInvestmentPlanDTO
{
    public ?string $name;
    public ?float $amount;
    public ?string $label;

    public function __construct(array $data)
    {
        $this->validate($data);

        $this->name = $data['name'] ?? null;
        $this->amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $this->label = $data['label'] ?? null;
    }

    public static function fromRequest(array $data): self
    {
        return new self($data);
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'name'   => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:1',
            'label'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
