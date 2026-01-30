<?php
namespace App\DTOs\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class ShowTrackingDTO extends FormRequest
{
    public string $tracking_number;

    public function rules(): array
    {
        return [
            'tracking_number' => 'required|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->tracking_number = $this->input('tracking_number');
    }

    public function authorize(): bool
    {
        return true;
    }
}
