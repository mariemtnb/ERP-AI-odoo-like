<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requisitions on a reusable, multi-level approval engine.
 *
 * The engine is generic: a workflow for a document type has an ordered ladder
 * of steps, each naming the role that signs it and the amount from which it
 * applies. Any model can be sent through it (approval_requests is polymorphic),
 * and each decision is recorded (approval_actions). Purchase requisitions are
 * the first consumer — once fully approved a requisition converts into a
 * purchase order — but nothing here is purchase-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type', 40)->unique();  // e.g. purchase_requisition
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('approver_role', 40);            // role that signs this step
            $table->decimal('min_amount', 14, 3)->default(0); // step applies from this amount up
            $table->timestamps();
            $table->unique(['workflow_id', 'sequence']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->decimal('amount', 14, 3)->default(0);
            $table->string('status', 10)->default('pending');   // pending|approved|rejected
            $table->unsignedSmallInteger('current_sequence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_sequence');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 10);                 // approved|rejected
            $table->string('comment', 500)->default('');
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // draft|pending|approved|rejected|converted
            $table->string('status', 10)->default('draft');
            $table->decimal('total_estimate', 14, 3)->default(0);
            $table->string('notes', 500)->default('');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('estimated_price', 14, 2)->default(0);
            $table->string('notes', 255)->default('');
            $table->timestamps();
        });

        $now = now();
        $workflowId = DB::table('approval_workflows')->insertGetId([
            'name' => 'Purchase requisition approval', 'document_type' => 'purchase_requisition',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('approval_steps')->insert([
            ['workflow_id' => $workflowId, 'sequence' => 1, 'name' => 'Manager approval',
                'approver_role' => 'manager', 'min_amount' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['workflow_id' => $workflowId, 'sequence' => 2, 'name' => 'Admin sign-off (large)',
                'approver_role' => 'admin', 'min_amount' => 5000, 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! DB::table('feature_flags')->where('key', 'requisitions')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'requisitions', 'name' => 'Purchase requisitions',
                'description' => 'Request purchases and route them through an approval chain',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
        DB::table('feature_flags')->where('key', 'requisitions')->delete();
    }
};
