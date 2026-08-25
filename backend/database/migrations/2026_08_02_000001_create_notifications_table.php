<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications.
 *
 * One row per recipient. The generator (NotificationScanner) is idempotent:
 * it uses a stable `dedupe_key` so re-scanning does not create the same
 * "cheque X is due" notice twice. Email/SMS are deliberately NOT here yet —
 * they need a provider this project has not wired up; the service is shaped so
 * a mail/SMS channel can be added later without changing callers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);                 // e.g. instrument.due, stock.low
            $table->string('category', 20)->default('system'); // treasury|inventory|…
            $table->string('severity', 10)->default('info');   // info|warning|critical
            $table->string('title', 200);
            $table->text('body')->default('');
            $table->string('link', 200)->default('');   // frontend route to open
            $table->string('subject_type', 40)->default('');
            $table->unsignedBigInteger('subject_id')->nullable();
            // Stops the scanner re-creating the same notice; empty = not deduped.
            $table->string('dedupe_key', 120)->default('');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'dedupe_key']);
            $table->index('created_at');
        });

        // Feature flag so the whole area can be switched off.
        DB::table('feature_flags')->insert([
            'key' => 'notifications', 'name' => 'Notifications', 'description' => '',
            'enabled' => true, 'is_locked' => false, 'company_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        DB::table('feature_flags')->where('key', 'notifications')->delete();
    }
};
