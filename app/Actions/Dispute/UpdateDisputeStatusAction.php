<?php
namespace App\Actions\Dispute;

use App\Models\Dispute;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\DTOs\Dispute\UpdateDisputeStatusDTO;
use App\Events\Dispute\DisputeStatusUpdatedEvent;

class UpdateDisputeStatusAction
{
    protected TwilioService $twilio;
    protected TermiiService $termii;

    public function __construct(TwilioService $twilio, TermiiService $termii)
    {
        $this->twilio = $twilio;
        $this->termii = $termii;
    }

    public function execute(UpdateDisputeStatusDTO $dto): Dispute
    {
        $dispute = Dispute::findOrFail($dto->dispute_id);
        $dispute->update(['status' => $dto->action]);

        new DisputeStatusUpdatedEvent($dispute, $dto->action,$this->twilio, $this->termii);

        return $dispute;
    }
}
