<?php
namespace App\DTOs\Investor;

use Illuminate\Http\Request;

class AdminApproveOrRejectInvestorDTO
{
    public string $investorId;
    public string $action;

    public function __construct(string $investorId, string $action)
    {
        $this->investorId = $investorId;
        $this->action = $action;
    }

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'investor_id' => 'required|exists:investors,id',
            'action' => 'required|in:approve,reject',
        ]);

        return new self(
            $validated['investor_id'],
            $validated['action']
        );
    }
}
