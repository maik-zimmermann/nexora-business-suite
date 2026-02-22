# Fix Duplicate Stripe Customer and Duplicate Onboarding Email

## Context

During tenant provisioning two bugs exist in `TenantProvisioningService`:

1. **Duplicate Stripe customer / wrong customer ID**: `CheckoutSessionBuilder` passes `customer_email` to Stripe Checkout, so Stripe automatically creates a customer and links it to the subscription. That customer ID is available in the `checkout.session.completed` webhook payload at `data.object.customer`. However, `TenantProvisioningService` ignores it and calls `$tenant->createOrGetStripeCustomer()` instead, which creates a *second* Stripe customer. The tenant ends up storing the wrong (orphaned) customer ID — not the one linked to the active subscription.

2. **Duplicate onboarding email**: The `checkout.session.completed` webhook listener is queued (`ShouldQueue`). Stripe may deliver the same webhook more than once. The current idempotency guard checks `User::where('email')->exists()`, but this check and the user creation happen in separate steps — if two queue workers process the same webhook concurrently, both can pass the guard before either creates the user. Both then provision the tenant and dispatch `TenantProvisioned`, resulting in two activation emails.

---

## Fix 1 — Correct Stripe Customer ID

**File**: `app/Services/TenantProvisioningService.php`

The webhook payload contains the Stripe customer ID at `$payload['data']['object']['customer']`. Read this value and assign it directly to the tenant instead of calling `createOrGetStripeCustomer()`.

Change:
```php
if (config('cashier.secret')) {
    $tenant->createOrGetStripeCustomer(['email' => $checkoutSession->email]);
}
```

To:
```php
$stripeCustomerId = $payload['data']['object']['customer'] ?? null;
if ($stripeCustomerId) {
    $tenant->stripe_id = $stripeCustomerId;
    $tenant->save();
} elseif (config('cashier.secret')) {
    $tenant->createOrGetStripeCustomer(['email' => $checkoutSession->email]);
}
```

The Cashier `Billable` trait stores the customer ID in the `stripe_id` column on the `Tenant` model. No schema changes needed.

---

## Fix 2 — Idempotent Provisioning

**File**: `app/Services/TenantProvisioningService.php`

Replace the pre-transaction email-existence guard with a database-level idempotency lock. Use `DB::transaction()` with `lockForUpdate()` on the `CheckoutSession` record so only one worker can process a given session at a time.

Change the guard from:
```php
if (User::where('email', $checkoutSession->email)->exists()) {
    $checkoutSession->delete();
    return;
}
```

To: re-fetch and lock the `CheckoutSession` *inside* the transaction at the start, and abort if it no longer exists (already processed by another worker):
```php
DB::transaction(function () use ($sessionId, $payload) {
    $checkoutSession = CheckoutSession::where('session_id', $sessionId)
        ->lockForUpdate()
        ->first();

    if (! $checkoutSession) {
        return; // Already processed by another worker
    }

    // ... rest of provisioning, then delete $checkoutSession
});
```

Remove the outer pre-transaction guard entirely. The `lockForUpdate` ensures only one DB connection can proceed past that point for a given session ID.

---

## Critical Files

| File | Change |
|---|---|
| `app/Services/TenantProvisioningService.php` | Read customer ID from payload; replace pre-transaction guard with in-transaction lock |
| `tests/Feature/Checkout/WebhookTest.php` | Add tests: correct `stripe_id` stored on tenant; concurrent webhook idempotency |

---

## Implementation Notes

- The `$payload` array is already passed into the `DB::transaction()` closure via `use` — the `customer` ID extraction should happen inside the transaction alongside the other `$payload` reads.
- The `elseif` fallback for `createOrGetStripeCustomer()` handles the test environment where `cashier.secret` may be empty and Stripe never creates a customer (so `data.object.customer` is null).
- No migration needed — `stripe_id` already exists on the `tenants` table.
- Orphaned Stripe customers from existing bad signups are left in place (per spec decision).

---

## Verification

1. Run existing tests first: `vendor/bin/sail artisan test --compact --filter=WebhookTest`
2. After changes, run the same filter to confirm new tests pass.
3. Run full suite: `vendor/bin/sail artisan test --compact`
