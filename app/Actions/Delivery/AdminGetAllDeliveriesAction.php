<?php

namespace App\Actions\Delivery;

use App\Models\Delivery;
use Illuminate\Http\Request;

class AdminGetAllDeliveriesAction
{
    public function execute(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search'); 

        $query = Delivery::query()
            ->with(['customer', 'payment'])
            ->latest();

        //Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('customer_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driver = $q->getConnection()->getDriverName();

                if ($driver === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(pickup_location) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(dropoff_location) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(sender_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(sender_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(receiver_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(receiver_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(mode_of_transportation) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(package_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(other_package_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(package_description) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(tracking_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(waybill_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(delivery_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(external_reference) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(source_channel) LIKE ?', ["%{$search}%"]);
                } else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(pickup_location) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(dropoff_location) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(sender_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(sender_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(receiver_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(receiver_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(mode_of_transportation) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(package_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(other_package_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(package_description) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(tracking_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(waybill_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(customer_whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(delivery_type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(external_reference) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(source_channel) LIKE ?', ["%{$search}%"]);
                }

                //Include customer name/email from related user
                $q->orWhereHas('customer', function ($customer) use ($search) {
                    $customer->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                });
            });
        }

        return $query->paginate($perPage);
    }
}

