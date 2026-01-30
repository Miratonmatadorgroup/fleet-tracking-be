<?php
namespace App\Actions\TransportMode;

use App\Models\User;
use App\Models\TransportMode;
use Illuminate\Support\Facades\Auth;
use App\DTOs\TransportMode\DeleteTransportModeDTO;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteTransportModeAction
{
    public function execute(DeleteTransportModeDTO $dto): void
    {
        /** @var User $admin */
        $admin = Auth::user();

        if (!$admin || $admin->id !== $dto->adminId || !$admin->hasRole('admin')) {
            throw new HttpException(403, 'Forbidden. Only admins can perform this action.');
        }

        $transport = TransportMode::find($dto->transportModeId);

        if (!$transport) {
            throw new HttpException(404, 'Transport mode not found.');
        }

        $transport->delete();
    }
}
