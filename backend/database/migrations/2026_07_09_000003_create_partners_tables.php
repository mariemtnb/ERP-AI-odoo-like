<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function partnerTable(Blueprint $table): void
    {
        $table->id();
        $table->string('name', 200)->index();
        $table->string('email')->default('');
        $table->string('phone', 30)->default('');
        $table->text('address')->default('');
        $table->text('notes')->default('');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::create('customers', fn (Blueprint $t) => $this->partnerTable($t));
        Schema::create('suppliers', fn (Blueprint $t) => $this->partnerTable($t));
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
