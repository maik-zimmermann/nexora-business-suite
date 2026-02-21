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
        Schema::table('modules', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable()->change();
            $table->string('stripe_annual_price_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable(false)->change();
            $table->string('stripe_annual_price_id')->nullable(false)->change();
        });
    }
};
