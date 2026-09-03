<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Units of measure and conversion between them. */
class UomController extends Controller
{
    public function index()
    {
        return response()->json(
            UnitOfMeasure::orderBy('category')->orderByDesc('is_reference')->orderBy('code')
                ->get()->map(fn ($u) => $u->toApi())->all()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:units_of_measure,code'],
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::in(UnitOfMeasure::CATEGORIES)],
            'factor' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json(UnitOfMeasure::create($data)->toApi(), 201);
    }

    /** Convert a quantity from one unit to another. */
    public function convert(Request $request)
    {
        $data = $request->validate([
            'qty' => ['required', 'numeric'],
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        try {
            $result = UomService::convert((float) $data['qty'], $data['from'], $data['to']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json(['qty' => $data['qty'], 'from' => $data['from'], 'to' => $data['to'], 'result' => $result]);
    }
}
