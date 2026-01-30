<?php

namespace App\Http\Controllers\Api;

use App\Models\Discount;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DiscountController extends Controller
{
    public function index()
    {
        return successResponse("All discounts", Discount::with('users')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'percentage'  => 'required|integer|min:1|max:100',
            'applies_to_all' => 'boolean',
            'expires_at' => 'nullable|date'
        ]);

        $discount = Discount::create($request->only('name', 'percentage', 'applies_to_all', 'expires_at'));

        return successResponse("Discount created", $discount, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'string',
            'percentage'  => 'integer|min:1|max:100',
            'applies_to_all' => 'boolean',
            'expires_at' => 'nullable|date'
        ]);

        $discount = Discount::findOrFail($id);
        $discount->update($request->all());

        return successResponse("Discount updated", $discount);
    }

    public function destroy($id)
    {
        Discount::findOrFail($id)->delete();
        return successResponse("Discount deleted");
    }

    // ASSIGN DISCOUNT
    public function assignToUser(Request $request)
    {
        $request->validate([
            'discount_id' => 'required|uuid|exists:discounts,id',
            'user_id' => 'required|uuid|exists:users,id'
        ]);

        $discount = Discount::findOrFail($request->discount_id);
        $discount->users()->syncWithoutDetaching([$request->user_id]);

        return successResponse('Discount assigned successfully');
    }

    public function removeFromUser(Request $request)
    {
        $request->validate([
            'discount_id' => 'required|uuid|exists:discounts,id',
            'user_id' => 'required|uuid|exists:users,id'
        ]);

        $discount = Discount::findOrFail($request->discount_id);
        $discount->users()->detach($request->user_id);

        return successResponse('Discount removed successfully');
    }

    // ACTIVATE AND DE-ACTIVATE DISCOUNT
    public function activate($id)
    {
        $discount = Discount::findOrFail($id);
        $discount->update(['is_active' => true]);

        return successResponse('Discount activated');
    }

    public function deactivate($id)
    {
        $discount = Discount::findOrFail($id);
        $discount->update(['is_active' => false]);

        return successResponse('Discount deactivated');
    }

}
