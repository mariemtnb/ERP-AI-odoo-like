<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A reusable, multi-level approval engine. Any model can be routed through the
 * workflow registered for its document type: the request walks an ordered
 * ladder of steps, each signed by a role and applying only from a threshold
 * amount up. Consumers implement `applyApprovalOutcome(string $status)` to react
 * when the request is finally approved or rejected. Nothing here is specific to
 * purchasing.
 */
class ApprovalService
{
    /**
     * Route a model into its workflow. If no workflow (or no step applies at
     * this amount) the request is created already approved, so a document is
     * never left waiting on an empty chain.
     */
    public static function start(Model $approvable, string $documentType, float $amount, User $user): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::where('document_type', $documentType)->where('is_active', true)->first();
        $steps = $workflow ? self::applicableSteps($workflow, $amount) : new Collection;
        $first = $steps->first();

        return $approvable->approvalRequest()->create([
            'workflow_id' => $workflow?->id,
            'amount' => $amount,
            'status' => $first ? ApprovalRequest::STATUS_PENDING : ApprovalRequest::STATUS_APPROVED,
            'current_sequence' => $first?->sequence,
            'created_by' => $user->id,
            'decided_at' => $first ? null : now(),
        ]);
    }

    /**
     * Record one decision on the current step. Approving advances to the next
     * applicable step, or finalises the request when there are none left.
     */
    public static function act(ApprovalRequest $request, User $user, string $decision, string $comment = ''): ApprovalRequest
    {
        if (! $request->isPending()) {
            throw new InvalidTransition('This request has already been decided.');
        }
        $step = self::currentStep($request);
        if (! $step) {
            throw new InvalidTransition('This request has no pending step.');
        }
        if (! self::userHoldsRole($user, $step->approver_role)) {
            throw new InvalidTransition("This step needs {$step->approver_role} approval.");
        }
        if (! in_array($decision, [ApprovalRequest::STATUS_APPROVED, ApprovalRequest::STATUS_REJECTED], true)) {
            throw new InvalidTransition('Decision must be approved or rejected.');
        }

        return DB::transaction(function () use ($request, $user, $decision, $comment, $step) {
            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'step_sequence' => $step->sequence,
                'approver_id' => $user->id,
                'decision' => $decision,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            if ($decision === ApprovalRequest::STATUS_REJECTED) {
                $request->update(['status' => ApprovalRequest::STATUS_REJECTED, 'decided_at' => now()]);
            } else {
                $next = self::applicableSteps($request->workflow, (float) $request->amount)
                    ->first(fn ($s) => $s->sequence > $step->sequence);
                $request->update($next
                    ? ['current_sequence' => $next->sequence]
                    : ['status' => ApprovalRequest::STATUS_APPROVED, 'current_sequence' => null, 'decided_at' => now()]);
            }

            $request->refresh();
            if (! $request->isPending()) {
                $model = $request->approvable;
                if ($model && method_exists($model, 'applyApprovalOutcome')) {
                    $model->applyApprovalOutcome($request->status);
                }
            }

            return $request;
        });
    }

    /** Pending requests whose current step this user is entitled to sign. */
    public static function pendingFor(User $user): Collection
    {
        return ApprovalRequest::where('status', ApprovalRequest::STATUS_PENDING)
            ->with(['workflow.steps', 'actions', 'approvable'])
            ->get()
            ->filter(function ($request) use ($user) {
                $step = self::currentStep($request);

                return $step && self::userHoldsRole($user, $step->approver_role);
            })
            ->values();
    }

    /** The step a pending request is currently waiting on. */
    public static function currentStep(ApprovalRequest $request): ?ApprovalStep
    {
        if ($request->current_sequence === null || ! $request->workflow) {
            return null;
        }

        return $request->workflow->steps->firstWhere('sequence', $request->current_sequence);
    }

    /** Steps that apply at this amount, in order. */
    private static function applicableSteps(ApprovalWorkflow $workflow, float $amount): Collection
    {
        return $workflow->steps->filter(fn ($s) => (float) $s->min_amount <= $amount)->values();
    }

    /** Admins/super-admins satisfy any step; otherwise the role must match. */
    private static function userHoldsRole(User $user, string $role): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return true;
        }

        return $user->role === $role;
    }
}
