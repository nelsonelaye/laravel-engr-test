<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('min_batch_size')->default(1);
            $table->integer('max_batch_size')->default(100);
            $table->decimal('daily_capacity', 12, 2)->default(100000); // in Naira
            $table->enum('preferred_date_type', ['encounter', 'submission'])->default('encounter');
            $table->json('specialty_efficiencies')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
}; 