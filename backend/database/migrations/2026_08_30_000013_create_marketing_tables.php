<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing campaigns over email or SMS. A campaign targets customers who have
 * the relevant contact detail (email address or phone). Sending resolves the
 * audience and records a recipient row per customer. The actual delivery goes
 * through a pluggable sender — stubbed here (no provider wired up), the same
 * shape the notifications layer uses, so a real channel drops in later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 8); // email | sms
            $table->string('subject')->default('');
            $table->text('body');
            $table->string('status', 8)->default('draft'); // draft | sent
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('contact');
            $table->string('status', 8)->default('sent'); // sent | skipped
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
