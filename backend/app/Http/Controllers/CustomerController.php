<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class CustomerController extends PartnerController
{
    protected string $model = Customer::class;

    public function history(int $id)
    {
        $customer = Customer::findOrFail($id);
        $sales = $customer->sales()
            ->with(['customer', 'creator', 'lines.product'])
            ->orderByDesc('created_at')->limit(50)->get();

        return response()->json(['results' => $sales->map(fn ($s) => $s->toApi())->values()->all()]);
    }
}
