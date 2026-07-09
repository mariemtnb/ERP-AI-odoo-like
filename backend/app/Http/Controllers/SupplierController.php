<?php

namespace App\Http\Controllers;

use App\Models\Supplier;

class SupplierController extends PartnerController
{
    protected string $model = Supplier::class;

    public function history(int $id)
    {
        $supplier = Supplier::findOrFail($id);
        $orders = $supplier->purchaseOrders()
            ->with(['supplier', 'creator', 'lines.product'])
            ->orderByDesc('created_at')->limit(50)->get();

        return response()->json(['results' => $orders->map(fn ($o) => $o->toApi())->values()->all()]);
    }
}
