# Metered Graduated Tiered Pricing for Seats and Usage Quota

## Overview

Both seat and usage quota billing should use a metered graduated tiered pricing model in Stripe. The first tier covers the minimum/included quantity at no cost, and any usage beyond that is billed at a defined per-unit price. Neither seats nor usage quota are configurable in the plan builder UI — both show only informational examples explaining the pricing structure. Both seat count and usage quota consumption must be reported to Stripe via the metered usage API so that customers are correctly billed. Seat reporting is tied to the subscription's billing interval — monthly subscriptions report monthly, annual subscriptions report annually.

## Goals

- Model both seat and usage quota billing as Stripe metered graduated tiered prices.
- The first tier (up to the minimum/included quantity) is always free.
- Usage beyond the minimum is billed at a configured per-unit price.
- Remove both seat count and usage quota configuration from the plan builder; replace both with informational/example content.
- Report both seat count and usage quota consumption to Stripe using the metered usage reporting API.
- Seat reporting period matches the subscription's billing interval (monthly or annual).
- Ensure Stripe product provisioning creates prices using the graduated tiered model for both seats and usage quota.

## Non-Goals / Out of Scope

- Admin configuration of seat or usage quota pricing from the plan builder.
- Multi-currency support.
- Volume-based or package pricing models (only graduated tiers).
- Real-time usage reporting — periodic or event-driven reporting is acceptable.

---

## Pricing Model

Both seats and usage quota share the same graduated tiered structure: the first tier up to the included minimum is free, and all additional units are billed at a fixed per-unit price defined at the system level.

### Seat Pricing

| Tier | Range | Price |
|---|---|---|
| Tier 1 | 1 – minimum seats (inclusive) | Free (£0.00 / seat) |
| Tier 2 | minimum + 1 and above | System-defined per-seat price |

### Usage Quota Pricing

| Tier | Range | Price |
|---|---|---|
| Tier 1 | 0 – included quota (inclusive) | Free (£0.00 / unit) |
| Tier 2 | included quota + 1 and above | System-defined per-unit price |

Both the included quantities and per-unit prices are fixed system-level values, not configurable by admins.

---

## Plan Builder Changes

### Seat Count (Informational Only)

The plan builder no longer exposes seat count or per-seat price configuration fields. Instead, a read-only informational section should be shown that:
- Explains the seat model and what counts as a seat.
- Shows example tier breakpoints (e.g. "First 5 seats included, then £X/seat/month").
- Makes clear that seat limits and pricing are managed at the system level.

### Usage Quota (Informational Only)

The plan builder no longer exposes any usage quota configuration fields. A read-only informational section should be shown that:
- Explains what the usage quota is and how it is measured.
- Shows example quota tiers (e.g. "Up to X units/month included, then £Y per unit").
- Makes clear that quota limits and pricing are managed at the system level.

---

## Stripe Provisioning

### Seat Overage Price

The seat overage Stripe price must use the `graduated` tiers model with `billing_scheme: tiered`:

- **Tier 1**: Up to the minimum seat count, unit amount = 0.
- **Tier 2**: All additional units, unit amount = system-defined per-seat price.
- **Recurring**: Monthly and annual variants.
- **Usage type**: `metered`, with `aggregate_usage: sum`.

### Usage Quota Price

The usage quota Stripe price must use:

- `billing_scheme: tiered`, `tiers_mode: graduated`.
- `usage_type: metered` so that usage is reported and accumulated per billing period.
- **Tier 1**: Up to the included quota amount, unit amount = 0.
- **Tier 2**: All additional units, unit amount = system-defined per-unit price.
- **Aggregate usage**: `sum`.

Because Stripe prices are immutable, any change to the tier structure requires creating a new price and updating the stored Price ID.

---

## Usage Reporting to Stripe

Both seat count and usage quota consumption must be actively reported to Stripe so that metered billing functions correctly.

### When to Report

Usage should be reported to Stripe:
- Periodically (e.g. on a scheduled job, at least once per billing period).
- Or when a meaningful usage threshold is crossed.

### Seat Reporting

For each active subscription with a metered seat price item:
- Determine the tenant's current active seat count.
- Report the seat count to Stripe using the subscription item's usage record API.
- The reporting interval must match the subscription's billing interval — monthly subscriptions report per calendar month, annual subscriptions report per year.
- Use `action: set` (absolute value) to reflect the current seat count at the time of reporting, rather than accumulating increments.

### Usage Quota Reporting

For each active subscription with a metered usage quota price item:
- Retrieve the tenant's current usage for the billing period.
- Report the total usage to Stripe using the subscription item's usage record API.
- Use `action: set` (absolute value) rather than `increment` to avoid double-counting on repeated reports.

### Tracking Current Usage

The application must track the current usage per tenant per billing period in a way that can be queried and reported to Stripe. If this is not already tracked, a mechanism to accumulate usage events must be introduced.

---

## Open Questions

- [ ] What unit does "usage quota" measure — API calls, records, operations? This must be defined to ensure correct reporting. Globally API calls for third party integrations. Module specific usage may be introduced later.
- [ ] Is usage currently tracked anywhere in the application, or does tracking need to be added from scratch? Usage quota is already tracked in the database, seat count is calculated from the active users in the tenant.
- [ ] How frequently should usage be reported to Stripe — scheduled job, webhook trigger, or on each usage event? Each usage event for the usage quota.
- [ ] Should the system alert tenants when they approach or exceed the free tier limit? Yes
- [ ] When exactly should seat count be snapshotted for reporting — at the start of the period, end of period, or peak during the period? Start, end and peak. The peak of the current billing period should be used for reporting.

---

## Acceptance Criteria

- Seat Stripe prices use a graduated tiered metered model where the first N seats are free.
- Usage quota Stripe prices use a graduated tiered metered model where the first N units are free.
- The plan builder UI does not allow admins to configure seat count, per-seat price, usage quota limits, or usage prices.
- The plan builder UI shows informational/example sections for both seats and usage quota.
- Seat count is reported to Stripe as metered usage, with the reporting interval matching the subscription's billing interval (monthly or annual).
- Usage quota consumption is reported to Stripe on a defined schedule or trigger.
- Reported seat and usage values are idempotent — running the reporter multiple times in a period does not inflate the billed amounts.
- Re-running the Stripe sync command updates prices to the graduated tiered model without creating duplicates.
