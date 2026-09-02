<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The approval inbox: decide the requests waiting on you. */
class ApprovalController extends Controller
{
    /** Requests whose current step the caller is entitled to sign. */
    public function pending(Request $request)
    {
        return response()->json(
            ApprovalService::pendingFor($request->user())->map(fn ($r) => $r->toApi())->all()
        );
    }

    /** Approve or reject the current step of a request. */
    public function act(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([ApprovalRequest::STATUS_APPROVED, ApprovalRequest::STATUS_REJECTED])],
            'comment' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        try {
            $approval = ApprovalService::act($approval, $request->user(), $data['decision'], $data['comment'] ?? '');
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($approval->load('actions')->toApi());
    }
}
