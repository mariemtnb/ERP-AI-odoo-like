<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TimesheetEntry;
use App\Models\User;

/**
 * Project time tracking. People log hours against a project (optionally a
 * task), billable or not; a summary rolls the hours up per task and against
 * the project's hour budget.
 */
class ProjectService
{
    public static function logTime(
        Project $project,
        ?int $taskId,
        User $user,
        string $date,
        float $hours,
        bool $billable,
        string $note,
    ): TimesheetEntry {
        if ($project->status !== Project::STATUS_ACTIVE) {
            throw new InvalidTransition('Cannot log time on a closed project.');
        }
        if ($hours <= 0) {
            throw new InvalidTransition('Hours must be positive.');
        }
        if ($taskId !== null && ! ProjectTask::where('id', $taskId)->where('project_id', $project->id)->exists()) {
            throw new InvalidTransition('That task does not belong to this project.');
        }

        return TimesheetEntry::create([
            'project_id' => $project->id,
            'task_id' => $taskId,
            'user_id' => $user->id,
            'work_date' => $date,
            'hours' => round($hours, 2),
            'billable' => $billable,
            'note' => $note,
        ]);
    }

    public static function summary(Project $project): array
    {
        $total = (float) $project->entries()->sum('hours');
        $billable = (float) $project->entries()->where('billable', true)->sum('hours');
        $budget = $project->budget_hours !== null ? (float) $project->budget_hours : null;

        return [
            'project' => $project->id,
            'name' => $project->name,
            'budget_hours' => $budget,
            'logged_hours' => round($total, 2),
            'billable_hours' => round($billable, 2),
            'non_billable_hours' => round($total - $billable, 2),
            'remaining_hours' => $budget === null ? null : round($budget - $total, 2),
            'over_budget' => $budget !== null && $total > $budget,
            'by_task' => $project->tasks()->get()->map(fn (ProjectTask $t) => [
                'task' => $t->id,
                'name' => $t->name,
                'estimate_hours' => $t->estimate_hours !== null ? (float) $t->estimate_hours : null,
                'logged_hours' => (float) TimesheetEntry::where('task_id', $t->id)->sum('hours'),
            ])->values()->all(),
        ];
    }

    public static function close(Project $project): Project
    {
        if ($project->status === Project::STATUS_CLOSED) {
            throw new InvalidTransition('Project is already closed.');
        }
        $project->update(['status' => Project::STATUS_CLOSED]);

        return $project;
    }
}
