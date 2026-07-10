<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- multi-warehouse ---
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('address')->default('');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('warehouses')->insert([
            'name' => 'Main warehouse',
            'address' => '',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Per-warehouse quantities; products.quantity_in_stock stays the global cache.
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
        });

        // Existing stock belongs to the default warehouse.
        $defaultId = DB::table('warehouses')->where('is_default', true)->value('id');
        DB::table('stock_movements')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultId]);
        $rows = DB::table('products')->where('quantity_in_stock', '>', 0)
            ->get(['id', 'quantity_in_stock']);
        foreach ($rows as $row) {
            DB::table('warehouse_stocks')->insert([
                'warehouse_id' => $defaultId,
                'product_id' => $row->id,
                'quantity' => $row->quantity_in_stock,
            ]);
        }

        // --- approval workflow ---
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
        });

        // --- CRM ---
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('company', 200)->default('');
            $table->string('email')->default('');
            $table->string('phone', 30)->default('');
            $table->string('source', 50)->default(''); // web, referral, phone, event…
            $table->string('status', 20)->default('new'); // new|contacted|qualified|won|lost
            $table->text('notes')->default('');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('type', 20); // call|email|meeting|note
            $table->text('summary');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
    }
};
