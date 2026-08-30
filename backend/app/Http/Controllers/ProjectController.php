<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TimesheetEntry;
use App\Services\ProjectService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with('customer')->orderByDesc('id')->get();

        return response()->json(['results' => $projects->map(fn (Project $p) => $p->toApi())->values()]);
    }

    public function show(Project $project)
    {
        return response()->json($project->toApi() + [
            'tasks' => $project->tasks()->get()->map(fn ($t) => $t->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'budget_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::create([
            'name' => $data['name'],
            'customer_id' => $data['customer'] ?? null,
            'budget_hours' => $data['budget_hours'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($project->toApi(), 201);
    }

    public function close(Project $project)
    {
        try {
            ProjectService::close($project);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($project->fresh()->toApi());
    }

    public function summary(Project $project)
    {
        return response()->json(ProjectService::summary($project));
    }

    // ---------- tasks ----------

    public function addTask(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'estimate_hours' => $data['estimate_hours'] ?? null,
        ]);

        return response()->json($task->toApi(), 201);
    }

    // ---------- timesheets ----------

    public function timesheets(Request $request, Project $project)
    {
        $query = TimesheetEntry::with(['task', 'user'])->where('project_id', $project->id)
            ->orderByDesc('work_date')->orderByDesc('id');

        return response()->json(DrfPagination::paginate($query, $request, fn (TimesheetEntry $e) => $e->toApi()));
    }

    public function logTime(Request $request, Project $project)
    {
        $data = $request->validate([
            'task' => ['nullable', 'integer', 'exists:project_tasks,id'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0'],
            'billable' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = ProjectService::logTime(
                $project, $data['task'] ?? null, $request->user(),
                $data['work_date'], (float) $data['hours'], $data['billable'] ?? true, $data['note'] ?? ''
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($entry->toApi(), 201);
    }
}
