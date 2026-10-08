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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('txn_date');
            $table->date('nav_date');
            $table->decimal('nav', 12, 4);
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedInteger('stamp_duty_paise')->default(0);
            $table->decimal('units', 15, 3);
            $table->boolean('units_overridden')->default(false);
            $table->timestamps();

            $table->index(['holding_id', 'txn_date']);
            $table->unique(['sip_id', 'txn_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
