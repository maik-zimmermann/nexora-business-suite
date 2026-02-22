# Fix Duplicate Stripe Customer and Duplicate Onboarding Email

## Overview

During the registration flow, two separate Stripe customers are created for the same new user: one by Stripe Checkout itself (from the `customer_email` parameter) and one by the tenant provisioning service after checkout completes. The provisioning service then stores the ID of the second, redundant customer on the tenant — discarding the customer that Stripe Checkout already created and associated with the subscription. Additionally, the onboarding email is sent more than once under certain conditions, causing a confusing experience for new users.

## Goals

- Ensure exactly one Stripe customer exists per new tenant after checkout.
- Store the correct Stripe customer ID — the one Stripe created during the checkout session — on the tenant.
- Ensure the onboarding/activation email is sent exactly once per successful checkout.

## Non-Goals / Out of Scope

- Changes to the plan builder or checkout session parameters.
- Changes to subscription or billing logic beyond the customer ID fix.
- Retry or idempotency handling for webhook events beyond the scope of this bug.

---

## Problem Description

### Duplicate Stripe Customer

The `CheckoutSessionBuilder` creates a Stripe Checkout session with `customer_email` set. When the user completes payment, Stripe automatically creates a new Stripe customer and links it to the resulting subscription. This customer ID is available in the `checkout.session.completed` webhook payload under `data.object.customer`.

After the webhook fires, `TenantProvisioningService` calls `createOrGetStripeCustomer()` on the freshly created tenant. Because no Stripe customer ID is stored on the tenant yet, Cashier creates a **second** Stripe customer. The tenant ends up with this second (incorrect) customer ID, while the original customer — the one linked to the active subscription — is orphaned.

### Wrong Customer ID on Tenant

Because the provisioning service ignores the customer ID from the webhook payload and creates a new one, the tenant's `stripe_customer_id` does not match the Stripe customer that owns the subscription. This breaks any future billing operations that rely on the customer ID (e.g. invoices, payment method updates, subscription management).

### Duplicate Onboarding Email

The webhook listener is queued. If Stripe delivers the `checkout.session.completed` webhook more than once (which Stripe may do on timeout or delivery failure), or if the queue processes the same job twice, the provisioning service's duplicate-provisioning guard only prevents re-creating the user and tenant — but it does not guarantee the onboarding event is dispatched exactly once. The result is that the new user receives the activation/onboarding email multiple times.

---

## Acceptance Criteria

- [ ] After a successful checkout, exactly one Stripe customer exists for the new tenant.
- [ ] The Stripe customer ID stored on the tenant matches the customer created by Stripe during checkout (available in the webhook payload as `data.object.customer`).
- [ ] The provisioning service does not call `createOrGetStripeCustomer()` when a customer ID is already present in the webhook payload.
- [ ] The onboarding/activation email is sent exactly once per completed checkout, even if the webhook is delivered more than once.
- [ ] Idempotency is enforced at the webhook handling level so that processing the same `checkout.session.completed` event twice has no additional side effects.
- [ ] All existing tests continue to pass.
- [ ] New tests cover the corrected customer ID assignment and the single-dispatch guarantee for the onboarding email.

---

## Open Questions

- Should idempotency be enforced by storing the processed `checkout.session.completed` event ID, or is deleting the `CheckoutSession` record (as currently done) sufficient when combined with a database-level lock? Should be enough.
- Should the orphaned Stripe customer (created by `createOrGetStripeCustomer()`) be deleted from Stripe retroactively, or is it acceptable to leave existing duplicates in place and only fix new signups going forward? It is acceptable to leave orphaned Stripe customers in place.
