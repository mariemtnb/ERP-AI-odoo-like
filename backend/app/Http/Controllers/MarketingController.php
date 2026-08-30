<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Campaign;
use App\Services\MarketingService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::with('creator')->orderByDesc('id');

        return response()->json(DrfPagination::paginate($query, $request, fn (Campaign $c) => $c->toApi()));
    }

    public function show(Campaign $campaign)
    {
        return response()->json($campaign->toApi() + [
            'audience_size' => MarketingService::audience($campaign)->count(),
            'recipients' => $campaign->recipients()->with('customer')->get()->map(fn ($r) => $r->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', 'in:email,sms'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $campaign = MarketingService::create(
                $data['name'], $data['channel'], $data['subject'] ?? '', $data['body'], $request->user()
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($campaign->toApi() + [
            'audience_size' => MarketingService::audience($campaign)->count(),
        ], 201);
    }

    public function send(Request $request, Campaign $campaign)
    {
        try {
            $count = MarketingService::send($campaign);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($campaign->fresh()->toApi() + ['sent' => $count]);
    }
}
