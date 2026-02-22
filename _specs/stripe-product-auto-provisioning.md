# Stripe Product Auto-Provisioning

## Overview

When a module is created or updated in Nexora Business Suite, the corresponding Stripe products and prices should be created automatically — eliminating the need to manually configure them in Stripe and copy IDs into environment variables. Alongside module pricing, the seat and usage quota products also need to be automatically provisioned in Stripe, reflecting the tiered pricing model where a configured base amount is included in the subscription and additional consumption costs extra.

## Goals

- Automatically create and update Stripe products and prices when a module is created or its pricing changes.
- Automatically provision a Stripe product and prices for per-seat overage (seats beyond the included base).
- Automatically provision a Stripe product and prices for usage quota overage (usage beyond the included base).
- Store the resulting Stripe Price IDs back on the relevant model/configuration so they can be used during Stripe Checkout.
- Provide a way to re-sync all modules and billing products to Stripe on demand (e.g. for initial setup or recovery).
- Ensure the provisioning logic is safe to run multiple times without creating duplicate products or prices.

## Non-Goals / Out of Scope

- Managing subscriptions or handling subscription lifecycle events.
- Updating prices that have already been attached to active subscriptions (Stripe does not allow editing live prices).
- Free trials or discount codes.
- A UI for managing Stripe products from within the application.
- Multi-currency support.

---

## Module Stripe Provisioning

### When It Happens

Stripe product and price creation should be triggered automatically whenever:
- A new module is created.
- An existing module's name or pricing is changed.

### What Gets Created in Stripe

For each module, the following Stripe objects should be created:

| Stripe Object | Purpose |
|---|---|
| **Product** | Represents the module (e.g. "CRM Module"). Identified by a metadata field containing the module slug to allow idempotent re-syncing. |
| **Price (monthly)** | Recurring price billed monthly, using the module's `monthly_price_cents` value. |
| **Price (annual)** | Recurring price billed yearly, using the module's `annual_price_cents` value. |

After creation, the resulting Stripe Price IDs are stored back on the module record (`stripe_monthly_price_id` and `stripe_annual_price_id`).

### Idempotency

Re-syncing a module should not create duplicate Stripe products. The service should look up an existing Stripe product by metadata (module slug) before creating a new one.

Since Stripe prices are immutable once created, re-syncing should only create new prices if the price amounts have changed. Old prices are not deleted but the module record is updated to point to the new IDs.

---

## Seat Overage Stripe Provisioning

### Pricing Model

Each subscription includes a configurable base number of seats at no extra charge (set via `BILLING_MIN_SEATS` or at subscription time). Additional seats beyond the included base are billed at a per-seat recurring price.

### What Gets Created in Stripe

| Stripe Object | Purpose |
|---|---|
| **Product** | Represents "Additional Seat" — a single Stripe product for seat overage. |
| **Price (monthly per seat)** | Recurring monthly price per additional seat. |
| **Price (annual per seat)** | Recurring annual price per additional seat. |

The resulting Price IDs should be stored in the billing configuration (e.g. `BILLING_SEAT_MONTHLY_PRICE_ID` / `BILLING_SEAT_ANNUAL_PRICE_ID`) or in a dedicated settings table.

---

## Usage Quota Overage Stripe Provisioning

### Pricing Model

Each subscription includes a configurable base usage quota at no extra charge. Usage beyond that quota is billed as a metered overage. The metered price is reported to Stripe via the usage reporting API.

### What Gets Created in Stripe

| Stripe Object | Purpose |
|---|---|
| **Product** | Represents "Usage Overage" — a single Stripe product for metered usage. |
| **Metered Price** | A metered recurring price that accumulates reported usage and bills the customer at the end of each period. |

The resulting Price ID should be stored in billing configuration (`BILLING_USAGE_METERED_PRICE_ID`).

---

## Sync Command

An Artisan command (`modules:sync-stripe`) should be available to manually trigger provisioning for all modules and the seat/usage billing products. This command is useful for:

- Initial environment setup.
- Recovery after a Stripe account is reset or changed.
- Syncing modules added by a seeder.

The command should be idempotent and safe to run in any environment where Stripe credentials are configured.

---

## Open Questions

- [ ] Where should seat and usage overage Price IDs be stored — environment variables, a settings table, or a dedicated config file? (Environment variables require a restart to update; a settings table is more dynamic.) settings table is probably the best option.
- [ ] Should the application support multiple currencies in future? If so, does the current price-in-cents model need to accommodate a currency field? No
- [ ] Should the sync command archive old Stripe prices when prices change, or leave them active? Archive, just make sure the old prices don't produce any unexpected errors.

---

## Acceptance Criteria

- Creating a new module automatically provisions a Stripe product with two prices (monthly and annual) and populates the module's price ID fields.
- Updating a module's name or prices triggers a re-sync with Stripe.
- Running the sync command provisions all missing Stripe objects for existing modules.
- Seat overage and usage quota Stripe products and prices exist and their IDs are accessible to the checkout flow.
- Re-running sync does not create duplicate Stripe products.
- When Stripe credentials are not configured, provisioning is skipped gracefully without errors.
