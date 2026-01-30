<?php 
namespace App\Services;

use App\Models\User;

// class UserBankProfileResolver
// {
//     public function resolve(User $user): array
//     {
//         $accounts = [];

//         // USER account
//         if ($this->hasBankDetails($user)) {
//             $accounts['user'] = $this->map($user);
//         }

//         // ROLE-based account
//         foreach (['driver', 'partner', 'investor'] as $role) {
//             if ($user->$role && $this->hasBankDetails($user->$role)) {
//                 $accounts['role'] = $this->map($user->$role);
//                 break;
//             }
//         }

//         if (count($accounts) === 1) {
//             return [
//                 'requires_choice' => false,
//                 'selected' => array_values($accounts)[0],
//             ];
//         }

//         if (
//             isset($accounts['user'], $accounts['role']) &&
//             $accounts['user'] == $accounts['role']
//         ) {
//             return [
//                 'requires_choice' => false,
//                 'selected' => $accounts['user'],
//             ];
//         }

//         return [
//             'requires_choice' => true,
//             'accounts' => $accounts,
//         ];
//     }

//     private function hasBankDetails($model): bool
//     {
//         return filled(
//             $model->bank_name &&
//             $model->bank_code &&
//             $model->account_number &&
//             $model->account_name
//         );
//     }

//     private function map($model): array
//     {
//         return [
//             'bank_name'      => $model->bank_name,
//             'bank_code'      => $model->bank_code,
//             'account_number'=> $model->account_number,
//             'account_name'  => $model->account_name,
//         ];
//     }
// }

class UserBankProfileResolver
{
    public function resolve(User $user): array
    {
        if (! $this->hasBankDetails($user)) {
            throw new \RuntimeException('User bank details are incomplete');
        }

        return [
            'requires_choice' => false,
            'selected'        => $this->map($user),
        ];
    }

    private function hasBankDetails($model): bool
    {
        return filled(
            $model->bank_name &&
            $model->bank_code &&
            $model->account_number &&
            $model->account_name
        );
    }

    private function map($model): array
    {
        return [
            'bank_name'       => $model->bank_name,
            'bank_code'       => $model->bank_code,
            'account_number'  => $model->account_number,
            'account_name'    => $model->account_name,
        ];
    }
}

