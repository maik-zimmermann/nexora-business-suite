# Marketing Landing Page

## Overview

Replace the boilerplate `Welcome.vue` with a polished, conversion-focused marketing landing page for the Nexora Business Suite. The page should communicate what Nexora is, who it's for, and why they should sign up — culminating in a clear call to action that drives users to the subscription checkout flow. Unsplash images may be used for hero visuals and feature illustrations.

## Goals

- Replace the default Laravel welcome page with a compelling marketing landing page.
- Clearly communicate the Nexora Business Suite value proposition to prospective customers.
- Highlight key product modules, features, and benefits with supporting visuals.
- Drive conversions by directing visitors to the subscription checkout or a free trial.
- Build trust through social proof sections (testimonials, logos, or stats).
- Ensure the page is fully responsive across desktop, tablet, and mobile.

## Non-Goals / Out of Scope

- Backend changes to routing or controllers — the page uses the existing root route.
- Authentication or onboarding flows — the page links to them but does not implement them.
- A/B testing or analytics instrumentation.
- A blog, documentation portal, or support pages.
- Dynamic content pulled from the database (modules, pricing) — static copy is acceptable for now.

---

## Page Sections

### 1. Navigation Bar

A sticky top navigation bar containing:

- The Nexora Business Suite logo/wordmark on the left.
- Navigation links in the centre or right: Features, Modules, Pricing, About.
- A "Log in" text link and a prominent "Start free trial" CTA button on the far right.

### 2. Hero Section

A full-width hero above the fold with:

- A bold headline communicating the core value proposition (e.g. "The all-in-one business suite for modern teams").
- A concise sub-headline expanding on the headline (1–2 sentences).
- A primary CTA button ("Start your free trial") and a secondary link ("See how it works").
- A high-quality hero image or illustration from Unsplash (e.g. a team collaborating, a modern office, or a dashboard mockup). The image should sit beside or behind the text.

### 3. Social Proof / Trust Bar

A narrow band below the hero containing:

- A short label such as "Trusted by teams at" or a headline like "Loved by growing businesses".
- 4–6 placeholder company logo slots (greyscale, generic logos or text placeholders).
- Optionally, a headline stat such as "500+ businesses" or "99.9% uptime".

### 4. Features / Benefits Section

A section showcasing 3–6 core benefits or differentiators of Nexora, laid out in a grid or alternating rows. Each feature card includes:

- A relevant icon or small Unsplash image.
- A short feature title (e.g. "All your tools in one place").
- A 2–3 sentence description.

Example features to consider:

- Unified workspace across modules (CRM, Invoicing, Projects, etc.)
- Role-based access control and team management.
- Flexible subscription — pay only for the modules you need.
- Real-time usage tracking and transparent billing.
- Enterprise-grade security and data privacy.
- Fast onboarding — up and running in minutes.

### 5. Modules Showcase

A dedicated section highlighting the individual Nexora modules. Presented as a tab interface, card grid, or horizontal scroll, each module card includes:

- A module name and icon.
- A 1–2 sentence description of what the module does.
- An optional "Learn more" link (can be a placeholder `#` for now).

Example modules: CRM, Invoicing, Project Management, Time Tracking, Analytics.

### 6. How It Works

A simple numbered steps section (3–4 steps) showing how a user gets started:

1. Choose your modules and start a free trial.
2. Invite your team and configure your workspace.
3. Go live and start tracking, billing, or managing.

Each step can include a small icon or Unsplash image thumbnail.

### 7. Testimonials / Social Proof

A section with 2–4 testimonial cards, each containing:

- A short quote (placeholder text is acceptable).
- A name, role, and company (placeholder data).
- An optional avatar from Unsplash (small, circular).

### 8. Pricing Teaser

A brief section that mentions pricing exists without listing full detail:

- Headline: "Pricing that scales with your business."
- Sub-copy: "Start free, add only the modules you need, and scale as you grow."
- A "View pricing" CTA button that links to the checkout/plan builder page.

### 9. Final CTA Section

A full-width, high-contrast section near the bottom of the page:

- A strong headline encouraging sign-up (e.g. "Ready to grow your business?").
- A sub-line reinforcing the free trial or low-risk entry.
- A large "Start free trial" primary button.

### 10. Footer

A standard marketing footer with:

- Logo and a one-line product description.
- Column links: Product (Features, Modules, Pricing), Company (About, Blog, Careers), Legal (Privacy Policy, Terms of Service).
- Copyright notice.

---

## Design Direction

- **Tone:** Professional, modern, and approachable. Confidence without arrogance.
- **Colour palette:** Follow the existing Tailwind CSS design tokens and brand colours already in the project. Where a brand colour is not yet established, prefer a deep navy or slate primary with a vibrant accent.
- **Typography:** Clear hierarchy — large, bold headings; regular weight body text; consistent spacing.
- **Imagery:** Use Unsplash images for hero, feature illustrations, testimonial avatars, and module screenshots. Prefer photos with neutral or light backgrounds that integrate cleanly with the design.
- **Dark / light mode:** Not required at launch; default to the existing app theme.

---

## Acceptance Criteria

- [ ] The root URL (`/`) renders the new marketing landing page instead of the Laravel boilerplate.
- [ ] All 10 sections described above are present and visually distinct.
- [ ] The navigation bar is sticky and links scroll to the relevant sections on the same page.
- [ ] The "Log in" link points to the existing login route.
- [ ] The "Start free trial" and "Start your free trial" CTAs point to the subscription checkout route.
- [ ] The page is fully responsive and looks correct on mobile, tablet, and desktop viewports.
- [ ] Unsplash images are used for at least the hero section and one other section.
- [ ] No existing authenticated routes or functionality are broken.
- [ ] All new behaviour is covered by feature tests.
- [ ] No existing tests are broken.

---

## Open Questions

- What is the official Nexora Business Suite logo or wordmark to use? (Placeholder acceptable for now) Nexora
- Are there any established brand colours or a style guide to follow? No
- Should the "Blog", "Careers", and "About" footer links be dead links (`#`) for now, or omitted entirely? dead links
- Should the modules listed in the showcase match the actual modules in the database, or use static placeholder copy? actual modules
- Is there a specific Unsplash collection or style preference for imagery? No
