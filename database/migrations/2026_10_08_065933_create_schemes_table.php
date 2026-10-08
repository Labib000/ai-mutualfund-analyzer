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
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('amfi_code')->unique();
            $table->char('isin_growth', 12)->nullable()->index();
            $table->char('isin_reinvestment', 12)->nullable();
            $table->string('name');
            $table->string('amc');
            $table->string('scheme_type', 20);
            $table->string('category');
            $table->string('plan', 10)->nullable();
            $table->decimal('latest_nav', 12, 4)->nullable();
            $table->date('latest_nav_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('history_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
