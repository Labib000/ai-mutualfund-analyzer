<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedTinyInteger('day_of_month');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('generated_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sips');
    }
};
