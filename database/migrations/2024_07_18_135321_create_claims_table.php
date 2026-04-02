<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurer_id')->constrained('insurers')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('provider_name');
            $table->date('encounter_date');
            $table->date('submission_date')->default(now());
            $table->string('specialty');
            $table->integer('priority_level')->default(3);
            $table->decimal('total_amount', 12, 2); // in Naira
            $table->decimal('processing_cost', 12, 2)->default(0); // in Naira
            $table->enum('status', ['pending', 'batched', 'processed'])->default('pending');
            $table->timestamps();
            $table->index(['insurer_id', 'batch_id']);
            $table->index(['insurer_id', 'status']);
        });

        Schema::create('claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('unit_price', 12, 2); // in Naira
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurer_id')->constrained('insurers')->cascadeOnDelete();
            $table->string('provider_name');
            $table->date('batch_date');
            $table->enum('status', ['pending', 'sent', 'processed'])->default('pending');
            $table->integer('total_claims')->default(0);
            $table->decimal('total_cost', 12, 2)->default(0); // in Naira
            $table->timestamps();
            $table->unique(['insurer_id', 'provider_name', 'batch_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
        Schema::dropIfExists('claim_items');
        Schema::dropIfExists('claims');
    }
}; 