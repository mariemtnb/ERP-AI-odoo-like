<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR operations on top of the existing employee/payroll data: daily attendance
 * (clock in/out), leave requests with an approval workflow and an annual
 * balance, and employee expense claims with their own approval and
 * reimbursement states.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('hours', 5, 2)->default(0);
            $table->string('note')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 12); // annual | sick | unpaid
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1);
            $table->string('reason')->default('');
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('claim_date');
            $table->string('category', 40)->default('');
            $table->decimal('amount', 14, 2);
            $table->string('description')->default('');
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | reimbursed
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
    }
};
