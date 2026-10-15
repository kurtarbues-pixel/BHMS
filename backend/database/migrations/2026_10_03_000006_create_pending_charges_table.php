<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['CANTEEN', 'APPLIANCE', 'OTHER'])->default('OTHER');
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'billed', 'voided'])->default('pending');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();

            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_charges');
    }
};
