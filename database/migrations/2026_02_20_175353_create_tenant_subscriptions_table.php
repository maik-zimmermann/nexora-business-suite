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
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->unique();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('status');
            $table->string('billing_interval');
            $table->json('module_slugs');
            $table->unsignedSmallInteger('seat_limit')->default(5);
            $table->string('seat_stripe_price_id')->nullable();
            $table->unsignedInteger('usage_quota');
            $table->string('usage_stripe_price_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('read_only_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
