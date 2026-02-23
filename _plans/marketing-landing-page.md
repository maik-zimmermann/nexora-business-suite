# Plan: Marketing Landing Page

## Context

The current `Welcome.vue` is the boilerplate Laravel starter kit page. It needs to be replaced with a polished, conversion-focused marketing landing page for Nexora Business Suite that communicates the product's value proposition and drives trial sign-ups. The spec is at `_specs/marketing-landing-page.md`.

Answers to open questions from the spec:
- Logo: "Nexora" wordmark (text placeholder)
- Brand colours: None established — use existing CSS design tokens (dark navy/slate primary)
- Footer dead links: Use `#` for Blog, Careers, About
- Modules: Pull actual modules from the database (CRM, Projects, Invoicing, HR, Support)
- Unsplash images: No preference — choose contextually appropriate photos

---

## Approach

### Backend: Pass Modules to the Welcome Page

The root route currently renders `Welcome` with no props. We need to pass the active modules from the database so the Modules Showcase section can use real data.

**File:** `routes/web.php`

Change the root route closure to eager-load and pass modules:

```php
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'modules' => \App\Models\Module::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'monthly_price_cents']),
    ]);
})->middleware('tenant.redirect')->name('home');
```

No new controller needed — keep the closure, it's a simple read.

### Frontend: Rewrite Welcome.vue

Replace the entire contents of `resources/js/pages/Welcome.vue` with the new marketing page. The component will:

- Accept a `modules` prop (array of Module objects from above)
- Use Wayfinder imports for links: `import { index as checkoutIndex } from '@/routes/checkout'` and `import { create as loginCreate } from '@/routes/login'`
- Use `<Link>` from `@inertiajs/vue3` for internal navigation
- Use `lucide-vue-next` icons throughout
- Use Tailwind CSS v4 utility classes and the existing CSS design tokens

### Page Section Implementation Plan

#### 1. Sticky Navigation Bar
- Logo: "Nexora" text with a simple icon (use `Layers` from lucide)
- Links: anchor scroll to `#features`, `#modules`, `#pricing`
- Auth links: "Log in" → `loginCreate()`, "Start free trial" → `checkoutIndex()`
- Sticky: `sticky top-0 z-50 bg-white/95 backdrop-blur-sm border-b`
- Mobile: collapse nav links, keep CTAs visible

#### 2. Hero Section
- Two-column layout on desktop, stacked on mobile
- Left: headline + sub-headline + two CTA buttons
- Right: Unsplash image (team/workspace photo, `object-cover rounded-2xl`)
- Unsplash URL pattern: `https://images.unsplash.com/photo-{id}?auto=format&fit=crop&w=800&q=80`
- Example hero image: a modern team collaborating at a desk

#### 3. Trust Bar
- Narrow band with muted background (`bg-muted`)
- "Trusted by growing businesses" label
- 5–6 company name placeholders in greyscale text
- Stat chips: "500+ teams", "99.9% uptime", "5 modules"

#### 4. Features / Benefits Section (`id="features"`)
- 3-column grid on desktop, 1-column on mobile
- 6 feature cards with lucide icon + title + description
- Features: Unified Workspace, Role-Based Access, Flexible Modules, Usage Tracking, Fast Onboarding, Secure by Default

#### 5. Modules Showcase (`id="modules"`)
- Card grid (2 or 3 cols) from the `modules` prop
- Each card: module name, description, monthly price formatted as `$X/mo`
- Icon per module: CRM → `Users`, Projects → `Kanban`, Invoicing → `Receipt`, HR → `UserCog`, Support → `HeadphonesIcon`
- "Add to plan" placeholder button per card (links to checkout)

#### 6. How It Works
- Numbered steps 1–3 with icon + step title + description
- Steps: Choose modules → Invite your team → Go live
- Horizontal layout on desktop, vertical on mobile

#### 7. Testimonials
- 3 testimonial cards in a row
- Placeholder quotes, names, roles, companies
- Avatar: small Unsplash portrait images, circular (`rounded-full object-cover w-12 h-12`)

#### 8. Pricing Teaser (`id="pricing"`)
- Centred section with headline + sub-copy
- "View pricing" button → `checkoutIndex()`

#### 9. Final CTA Section
- Full-width, high-contrast background (use `bg-primary text-primary-foreground`)
- Bold headline + sub-line + large "Start free trial" button → `checkoutIndex()`

#### 10. Footer
- 4-column grid: Brand + Product + Company + Legal
- Brand col: "Nexora" logo + one-liner tagline + copyright
- All non-existent links use `href="#"`

---

## Critical Files

| File | Action |
|---|---|
| `routes/web.php` | Pass `modules` prop to Welcome render |
| `resources/js/pages/Welcome.vue` | Full rewrite — new marketing page |

No new files needed. No new controllers, no new components (all self-contained in Welcome.vue).

---

## Unsplash Images to Use

All images use direct Unsplash photo URLs (no API key needed for `images.unsplash.com`):

- **Hero:** `https://images.unsplash.com/photo-1522071820081-009f0129c71c` (team collaborating)
- **Testimonial avatars:** 3 portrait photos from Unsplash

---

## Wayfinder Route Imports

```typescript
import { index as checkoutIndex } from '@/routes/checkout'
import { create as loginCreate } from '@/routes/login'
```

---

## Testing

After implementation:

1. Run `vendor/bin/sail artisan test --compact` — all existing tests must pass (no backend logic changed beyond passing modules prop, which is a read-only query).
2. Write a feature test for the welcome route asserting:
   - GET `/` returns 200
   - Response is an Inertia render of `Welcome`
   - The `modules` prop contains the seeded modules

**Test file:** `tests/Feature/WelcomePageTest.php`

3. Run `vendor/bin/sail bin pint --dirty` to fix any PHP formatting.
4. Visually verify the page in the browser by opening the root URL.
