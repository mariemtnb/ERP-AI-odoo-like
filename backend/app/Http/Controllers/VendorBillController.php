<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Services\VendorBillService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class VendorBillController extends Controller
{
    private const WITH = ['supplier', 'purchaseOrder', 'creator', 'lines.product'];

    public function index(Request $request)
    {
        $query = VendorBill::with(self::WITH)->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplier = $request->query('supplier')) {
            $query->where('supplier_id', $supplier);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (VendorBill $b) => $b->toApi())
        );
    }

    public function show(VendorBill $vendorBill)
    {
        return response()->json($vendorBill->load(self::WITH)->toApi(withLines: true, withMatch: true));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order' => ['sometimes', 'nullable', 'integer', 'exists:purchase_orders,id'],
            'bill_date' => ['required', 'date'],
            'supplier_ref' => ['sometimes', 'nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $po = ! empty($data['purchase_order']) ? PurchaseOrder::find($data['purchase_order']) : null;
        if ($po && $po->supplier_id !== (int) $data['supplier']) {
            return response()->json(['purchase_order' => ['That purchase order belongs to a different supplier.']], 422);
        }

        try {
            $bill = VendorBillService::record(
                Supplier::findOrFail($data['supplier']),
                $po,
                $data['lines'],
                $data['bill_date'],
                $request->user(),
                $data['supplier_ref'] ?? '',
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($bill->load(self::WITH)->toApi(withLines: true, withMatch: true), 201);
    }

    public function approve(Request $request, VendorBill $vendorBill)
    {
        try {
            $bill = VendorBillService::approve($vendorBill, $request->user());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($bill->load(self::WITH)->toApi(withLines: true, withMatch: true));
    }
}
