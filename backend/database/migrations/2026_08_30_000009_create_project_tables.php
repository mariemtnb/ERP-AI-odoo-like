<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project management with timesheets. A project has tasks and a budget of
 * hours; people log time entries against a project (optionally a task), flagged
 * billable or not. A summary rolls the entries up against the budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->string('status', 12)->default('active'); // active | closed
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('estimate_hours', 10, 2)->nullable();
            $table->string('status', 12)->default('open'); // open | done
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->boolean('billable')->default(true);
            $table->string('note')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['project_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
    }
};
