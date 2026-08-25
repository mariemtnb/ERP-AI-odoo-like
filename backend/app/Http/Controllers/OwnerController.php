<?php

namespace App\Http\Controllers;

use App\Services\ProfitabilityService;
use Illuminate\Http\Request;

/** The owner's money view: profit, expenses, and best-earning products. */
class OwnerController extends Controller
{
    public function profit(Request $request)
    {
        [$from, $to] = [$request->query('from'), $request->query('to')];

        return response()->json(ProfitabilityService::ownerView($from, $to));
    }
}
