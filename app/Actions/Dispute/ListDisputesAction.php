<?php

namespace App\Actions\Dispute;


use App\Models\Dispute;
use Illuminate\Support\Facades\DB;
use App\DTOs\Dispute\ListDisputesDTO;
use App\Events\Dispute\DisputesListedEvent;
use Illuminate\Pagination\LengthAwarePaginator;

class ListDisputesAction
{
    public function execute(ListDisputesDTO $dto): LengthAwarePaginator
    {
        $query = Dispute::query();

        // Filter by status
        if ($dto->status) {
            $query->where('status', $dto->status);
        }

        // Apply search if present
        if (!empty($dto->search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($dto, $likeOperator) {
                $search = $dto->search;

                // Search main dispute fields
                $q->where('title', $likeOperator, "%{$search}%")
                    ->orWhere('description', $likeOperator, "%{$search}%")
                    ->orWhere('tracking_number', $likeOperator, "%{$search}%")
                    ->orWhere('driver_contact', $likeOperator, "%{$search}%")
                    ->orWhere('id', $likeOperator, "%{$search}%");

                // Search related user
                $q->orWhereHas('user', function ($uq) use ($search, $likeOperator) {
                    $uq->where('name', $likeOperator, "%{$search}%")
                        ->orWhere('email', $likeOperator, "%{$search}%")
                        ->orWhere('phone', $likeOperator, "%{$search}%");
                });
            });
        }

        $disputes = $query
            ->with('user')
            ->latest()
            ->paginate(
                perPage: $dto->perPage,
                page: $dto->page
            );

        new DisputesListedEvent($disputes);

        return $disputes;
    }
}
