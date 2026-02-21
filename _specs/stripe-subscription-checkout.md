# Stripe Subscription Checkout with Tenant Creation

## Overview

Instead of a traditional registration form, new accounts are created through a Stripe-powered subscription checkout flow. When a user completes a subscription, a new tenant is automatically provisioned for them. The checkout is designed to collect the absolute minimum data required upfront — additional profile and configuration details are gathered during a post-subscription onboarding flow.

Subscriptions are composed dynamically by selecting which Nexora Business Suite modules the tenant wants to enable. Pricing also reflects the number of user seats and the included usage quota, with additional seats and overage usage billed at extra cost.

## Goals

- Replace the standard registration form with a Stripe Checkout-based subscription flow.
- Automatically provision a new tenant when a subscription is successfully created.
- Minimise friction at signup — capture only what is necessary before redirecting to Stripe.
- Allow tenants to build a custom subscription by selecting modules, seat count, and usage quota.
- Define a seat limit per subscription, with the option to purchase additional seats.
- Define a usage quota per subscription, with overage usage billed at extra cost.
- Defer all non-essential account setup to a post-checkout onboarding flow.

## Non-Goals / Out of Scope

- Managing or updating existing subscriptions (e.g. plan changes, cancellations) — this is a separate billing management feature.
- Defining which specific modules exist and what they do — this spec covers the subscription model only.
- Webhook handling beyond provisioning the tenant on successful payment.
- Multi-tenant admin controls over subscriptions from the Nexora administration panel.
- Free trials or coupon codes (may be added later).
- Invoice management or payment history UI.

---

## Checkout Flow

### 1. Pre-Checkout: Plan Builder

Before entering payment, the user configures their subscription by selecting:

- **Modules** — one or more available Nexora Business Suite modules (e.g. CRM, Invoicing, Projects). Each module adds to the total price.
- **Seats** — the number of user seats included in the base subscription. A minimum seat count applies. Additional seats beyond the included quota are purchasable at an extra per-seat price.
- **Usage quota** — the amount of tracked usage units included in the subscription. Additional usage beyond the quota is billed at an overage rate.

The plan builder presents a live price summary that updates as the user makes selections.

### 2. Minimal User Data Collection

Only the following data is collected before redirecting to Stripe:

| Field          | Purpose                                        |
|----------------|------------------------------------------------|
| Email address  | Used to create the user account and identify the customer in Stripe |

No password, name, company name, or billing address is required at this stage (Stripe Checkout collects payment details).

### 3. Stripe Checkout

The user is redirected to Stripe Checkout to complete payment. Stripe handles:

- Payment method collection.
- Billing address (if required by Stripe for tax purposes).
- Subscription creation.

Stripe Checkout is used in **subscription mode** so that recurring billing is set up immediately.

### 4. Post-Checkout: Tenant Provisioning

On successful payment, Stripe sends a webhook event. The application:

1. Receives and verifies the Stripe webhook.
2. Creates a user account for the provided email address (with a temporary or no password — the user will set a password during onboarding).
3. Creates a new tenant linked to the subscription.
4. Associates the user with the tenant as the **owner**.
5. Records the subscription details (modules, seat limit, usage quota, Stripe subscription ID) against the tenant.
6. Sends the user a welcome/account activation email with a link to begin onboarding.

### 5. Onboarding Flow

After account activation, the user completes an onboarding flow that collects:

- Display name / full name.
- Tenant/organisation name and subdomain.
- Password (or passwordless link if preferred).
- Any other profile or configuration data deferred from signup.

The onboarding flow is gated behind email verification or a signed activation link.

---

## Subscription Model

### Modules

- A list of available modules is maintained in the system.
- Each module has a name, description, and a price (monthly and/or annual).
- A subscription must include at least one module.
- Modules are represented as Stripe Price or Product items.

### Seats

- Each subscription defines a **base seat limit** — the number of users who can be members of the tenant.
- A minimum seat count is enforced (e.g. 1 or 3).
- Additional seats can be purchased at a per-seat monthly price, configurable in the system.
- The tenant's current seat usage is tracked and enforced — inviting a new member when at the seat limit is blocked until additional seats are purchased.

### Usage Quota

- Each subscription includes a base usage quota (e.g. number of API calls, tracked events, or processed records — the unit is defined per module or globally).
- Usage is tracked against the tenant in real time (or near real time).
- When usage exceeds the included quota, overage is billed automatically via Stripe's metered billing or usage records.
- The tenant should be able to view current usage vs quota from within the application.

---

## Data Model

### Subscription Record (on Tenant)

Each tenant has an associated subscription record storing:

| Field                     | Description                                                   |
|---------------------------|---------------------------------------------------------------|
| `tenant_id`               | Foreign key to the tenant                                     |
| `stripe_customer_id`      | Stripe customer identifier                                    |
| `stripe_subscription_id`  | Stripe subscription identifier                                |
| `status`                  | Subscription status (active, past_due, cancelled, etc.)      |
| `modules`                 | Array/JSON of enabled module identifiers                      |
| `seat_limit`              | Included seat count                                           |
| `usage_quota`             | Included usage units                                          |
| `current_period_end`      | When the current billing period ends                          |
| Timestamps                | `created_at`, `updated_at`                                    |

### Usage Tracking

Usage events are recorded per tenant and aggregated for quota comparison:

| Field         | Description                                      |
|---------------|--------------------------------------------------|
| `tenant_id`   | Tenant being tracked                             |
| `type`        | Category of usage event                          |
| `quantity`    | Number of units consumed                         |
| `recorded_at` | When the usage occurred                          |

---

## Webhook Events

The following Stripe webhook events must be handled:

| Event                                | Action                                                                 |
|--------------------------------------|------------------------------------------------------------------------|
| `checkout.session.completed`         | Provision user, tenant, and subscription record                        |
| `customer.subscription.updated`      | Sync subscription status and metadata changes                          |
| `customer.subscription.deleted`      | Mark tenant subscription as cancelled; restrict access as appropriate  |
| `invoice.payment_failed`             | Update status to `past_due`; notify tenant owner                       |

---

## Acceptance Criteria

- [ ] A plan builder UI allows selecting modules, seat count, and usage quota with a live price summary.
- [ ] Only an email address is required before the user is redirected to Stripe Checkout.
- [ ] A Stripe Checkout session is created in subscription mode with the correct line items (modules + seats + usage).
- [ ] On `checkout.session.completed`, a user, tenant, and subscription record are created automatically.
- [ ] The new user is assigned the `owner` role in the new tenant.
- [ ] An activation/onboarding email is sent to the user after provisioning.
- [ ] An onboarding flow collects deferred data (name, org name, subdomain, password) after activation.
- [ ] The subscription record stores all relevant Stripe identifiers, module selection, seat limit, and usage quota.
- [ ] Inviting a tenant member beyond the seat limit is blocked with an appropriate error.
- [ ] Usage is tracked per tenant and compared against the included quota.
- [ ] The tenant owner can view current usage vs quota in the application.
- [ ] Stripe webhook signatures are verified before processing.
- [ ] All new behaviour is covered by feature tests.
- [ ] No existing tests are broken.

---

## Open Questions

- What is the minimum seat count for a subscription? (e.g. 1, 3, 5) 5
- Is usage tracked globally per tenant, or per module independently? globally per tenant
- Should overage be billed via Stripe metered billing, or calculated and invoiced at period end? via Stripe metered billing
- What happens to a tenant's data if a subscription is cancelled — grace period, read-only access, or immediate lockout? Ideally a grace period, followed by a period of read-only access and finally a lockout.
- Should existing Nexora admin users be able to create tenants without going through Stripe (e.g. internal or trial tenants)? Yes
- Should annual billing be supported at launch, or monthly only? Yes
- Is there a free tier or trial period before requiring payment? I want to provide a free trial for new users.
