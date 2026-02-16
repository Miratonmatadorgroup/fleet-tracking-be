<?php

namespace App\DTOs\Delivery;

use Illuminate\Http\Request;

class DeliveryAssignmentLogsDTO
{
    public int $page;
    public int $perPage;
    public ?string $status;
    public ?string $deliveryId;

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 10),
            status: $request->query('status'),
            deliveryId: $request->query('delivery_id')  
        );
    }

    public function __construct(int $page, int $perPage, ?string $status = null, ?string $deliveryId = null)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->status = $status;
        $this->deliveryId = $deliveryId;
    }
}
