<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Events\TenantProvisioned;
use App\Models\CheckoutSession;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a new user, tenant, and subscription from a completed Stripe Checkout session.
     *
     * @param  array<string, mixed>  $payload
     */
    public function provision(array $payload): void
    {
        $sessionId = $payload['data']['object']['id'] ?? null;

        if (! $sessionId) {
            return;
        }

        $checkoutSession = CheckoutSession::where('session_id', $sessionId)->first();

        if (! $checkoutSession) {
            return;
        }

        // Prevent duplicate provisioning
        if (User::where('email', $checkoutSession->email)->exists()) {
            $checkoutSession->delete();

            return;
        }

        DB::transaction(function () use ($checkoutSession, $payload) {
            $stripeSubscription = $payload['data']['object']['subscription'] ?? null;

            $user = User::create([
                'name' => Str::before($checkoutSession->email, '@'),
                'email' => $checkoutSession->email,
                'password' => null,
            ]);

            $slug = Str::slug(Str::before($checkoutSession->email, '@'));
            $slug = $this->ensureUniqueSlug($slug);

            $tenant = Tenant::create([
                'id' => Str::uuid()->toString(),
                'name' => $slug,
                'slug' => $slug,
                'is_active' => false,
            ]);

            if (config('cashier.secret')) {
                $tenant->createOrGetStripeCustomer([
                    'email' => $checkoutSession->email,
                ]);
            }

            $trialEndsAt = null;
            if (isset($payload['data']['object']['subscription'])) {
                $stripeClient = \Laravel\Cashier\Cashier::stripe();
                $sub = $stripeClient->subscriptions->retrieve($stripeSubscription);
                $trialEndsAt = $sub->trial_end ? \Carbon\Carbon::createFromTimestamp($sub->trial_end) : null;
            }

            TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'stripe_subscription_id' => $stripeSubscription,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'billing_interval' => $checkoutSession->billing_interval,
                'module_slugs' => $checkoutSession->module_slugs,
                'seat_limit' => $checkoutSession->seat_limit,
                'usage_quota' => $checkoutSession->usage_quota,
                'trial_ends_at' => $trialEndsAt,
            ]);

            $ownerRole = Role::where('slug', 'owner')->firstOrFail();

            TenantMembership::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role_id' => $ownerRole->id,
            ]);

            $checkoutSession->delete();

            TenantProvisioned::dispatch($user, $tenant);
        });
    }

    /**
     * Ensure a slug is unique among tenants.
     */
    private function ensureUniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
