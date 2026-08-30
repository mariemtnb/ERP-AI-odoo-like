<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderByDesc('is_base')->orderBy('code')->get();

        return response()->json(['results' => $currencies->map(fn (Currency $c) => $c->toApi())->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:64'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimals' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        $currency = Currency::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? '',
            'decimals' => $data['decimals'] ?? 2,
            'is_base' => false,
            'is_active' => true,
        ]);

        return response()->json($currency->toApi(), 201);
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:64'],
            'symbol' => ['sometimes', 'string', 'max:8'],
            'decimals' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $currency->update($data);

        return response()->json($currency->toApi());
    }

    public function rates(Request $request, Currency $currency)
    {
        $rates = ExchangeRate::where('currency_code', $currency->code)
            ->orderByDesc('as_of')->orderByDesc('id')->limit(50)->get();

        return response()->json(['results' => $rates->map(fn (ExchangeRate $r) => $r->toApi())->values()]);
    }

    public function setRate(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'as_of' => ['nullable', 'date'],
        ]);

        try {
            $rate = CurrencyService::setRate($currency->code, (float) $data['rate'], $data['as_of'] ?? null, $request->user());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($rate->toApi(), 201);
    }

    public function convert(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $result = CurrencyService::convert(
                (float) $data['amount'],
                strtoupper($data['from']),
                strtoupper($data['to']),
                $data['date'] ?? null,
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json([
            'amount' => (float) $data['amount'],
            'from' => strtoupper($data['from']),
            'to' => strtoupper($data['to']),
            'result' => $result,
        ]);
    }
}
