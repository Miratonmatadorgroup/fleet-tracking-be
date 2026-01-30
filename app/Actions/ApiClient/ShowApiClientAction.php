<?php

namespace App\Actions\ApiClient;

use App\Models\ApiClient;
use App\DTOs\ApiClient\ShowApiClientDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowApiClientAction
{
    public function execute(ShowApiClientDTO $dto)
    {
        $query = ApiClient::with('customer');

        if (!empty($dto->search)) {
            $search = $dto->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('api_key', 'LIKE', "%{$search}%")
                    ->orWhere('environment', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($dto->perPage);
    }
}
