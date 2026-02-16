<?php
namespace App\Actions\Payout;

use App\Models\Payout;
use App\DTOs\Payout\ListPayoutsDTO;



class ListPayoutsAction
{
    public function execute(ListPayoutsDTO $dto)
    {
        $query = Payout::query()->with('user');

        foreach ($dto->filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        if (!empty($dto->userFilters)) {
            $query->whereHas('user', function ($q) use ($dto) {
                foreach ($dto->userFilters as $key => $value) {
                    if ($value === null) continue;

                    $column = str_replace('user_', '', $key);
                    $q->where($column, 'like', "%$value%");
                }
            });
        }

        if ($dto->role) {
            $query->whereHas('user.roles', function ($q) use ($dto) {
                $q->where('name', $dto->role);
            });
        }

        return $query->latest()->paginate($dto->perPage);
    }
}
