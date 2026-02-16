<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'driver_id' => $this->driver_id,
            'partner_id' => $this->partner_id,
            'investor_id' => $this->investor_id,
            'amount' => $this->amount,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'currency' => $this->currency,
            'status' => $this->status->value ?? $this->status,
            'provider_reference' => $this->provider_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone ?? null,
                    'whatsapp_number' => $this->user->whatsapp_number ?? null,
                ];
            }),
        ];
    }
}
