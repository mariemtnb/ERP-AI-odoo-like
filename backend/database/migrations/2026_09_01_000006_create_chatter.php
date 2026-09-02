<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chatter: comments and scheduled activities that attach to any record.
 *
 * Both tables are polymorphic on (subject_type, subject_id) — "sales" + 42,
 * "customers" + 7 — so the same timeline and follow-up reminders work on a
 * sale, a customer, a ticket or anything else without a table per type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_messages', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('record_activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('title', 200);
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['assigned_to', 'done']);
        });

        if (! DB::table('feature_flags')->where('key', 'chatter')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'chatter', 'name' => 'Chatter & activities',
                'description' => 'Comments and follow-up reminders on records',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('record_activities');
        Schema::dropIfExists('record_messages');
        DB::table('feature_flags')->where('key', 'chatter')->delete();
    }
};
