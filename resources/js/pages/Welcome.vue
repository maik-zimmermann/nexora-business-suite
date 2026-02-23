<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Layers,
    Users,
    Kanban,
    Receipt,
    UserCog,
    Headphones,
    LayoutGrid,
    ShieldCheck,
    Puzzle,
    BarChart3,
    Zap,
    Lock,
    Package,
    UserPlus,
    Rocket,
    Star,
    Menu,
    X,
    ArrowRight,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { login } from '@/routes';
import { index as checkoutIndex } from '@/routes/checkout';

interface Module {
    id: number;
    name: string;
    slug: string;
    description: string;
    monthly_price_cents: number;
}

defineProps<{
    modules: Module[];
}>();

const mobileMenuOpen = ref(false);

const moduleIcons: Record<string, typeof Users> = {
    crm: Users,
    projects: Kanban,
    invoicing: Receipt,
    hr: UserCog,
    support: Headphones,
};

const features = [
    {
        icon: LayoutGrid,
        title: 'Unified Workspace',
        description:
            'All your business tools in one place. No more switching between apps.',
    },
    {
        icon: ShieldCheck,
        title: 'Role-Based Access',
        description:
            'Fine-grained permissions ensure every team member sees exactly what they need.',
    },
    {
        icon: Puzzle,
        title: 'Flexible Modules',
        description:
            'Pick only the modules you need. Add more as your business grows.',
    },
    {
        icon: BarChart3,
        title: 'Usage Tracking',
        description:
            'Monitor usage across your organisation with built-in analytics and reporting.',
    },
    {
        icon: Zap,
        title: 'Fast Onboarding',
        description:
            'Get your team up and running in minutes, not weeks. No training required.',
    },
    {
        icon: Lock,
        title: 'Secure by Default',
        description:
            'Enterprise-grade security with encryption at rest and in transit.',
    },
];

const steps = [
    {
        icon: Package,
        title: 'Choose your modules',
        description:
            'Select the tools your business needs. Start lean and scale up anytime.',
    },
    {
        icon: UserPlus,
        title: 'Invite your team',
        description:
            'Add team members and assign roles. Everyone gets access instantly.',
    },
    {
        icon: Rocket,
        title: 'Go live',
        description:
            'Your workspace is ready. Start collaborating and growing your business.',
    },
];

const testimonials = [
    {
        quote: 'Nexora replaced five different tools for us. Our team is more productive and our costs dropped by 40%.',
        name: 'Sarah Chen',
        role: 'COO',
        company: 'Brightpath Labs',
        avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&q=80',
    },
    {
        quote: 'The modular approach is genius. We started with CRM and invoicing, then added HR when we hit 50 employees.',
        name: 'Marcus Rivera',
        role: 'Founder',
        company: 'Driftwood Digital',
        avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=96&q=80',
    },
    {
        quote: 'Onboarding was seamless. We went from sign-up to fully operational in under an hour.',
        name: 'Amira Patel',
        role: 'Head of Operations',
        company: 'Verve Collective',
        avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=96&q=80',
    },
];

function formatPrice(cents: number): string {
    return `$${(cents / 100).toFixed(0)}`;
}
</script>

<template>
    <div>
        <Head title="Nexora - The Modular Business Suite">
            <link rel="preconnect" href="https://rsms.me/" />
            <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        </Head>

        <!-- Navigation -->
        <nav
            class="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur-sm"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8"
            >
                <a href="#" class="flex items-center gap-2 text-foreground">
                    <Layers class="h-7 w-7" />
                    <span class="text-xl font-bold tracking-tight">Nexora</span>
                </a>

                <!-- Desktop nav -->
                <div class="hidden items-center gap-8 md:flex">
                    <a
                        href="#features"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >Features</a
                    >
                    <a
                        href="#modules"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >Modules</a
                    >
                    <a
                        href="#pricing"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >Pricing</a
                    >
                </div>

                <div class="hidden items-center gap-3 md:flex">
                    <Link
                        :href="login()"
                        class="rounded-md px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="checkoutIndex()"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        Start free trial
                    </Link>
                </div>

                <!-- Mobile menu button -->
                <button
                    class="md:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="h-6 w-6" />
                    <Menu v-else class="h-6 w-6" />
                </button>
            </div>

            <!-- Mobile nav -->
            <div
                v-if="mobileMenuOpen"
                class="border-t border-border px-4 pt-2 pb-4 md:hidden"
            >
                <div class="flex flex-col gap-2">
                    <a
                        href="#features"
                        class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
                        @click="mobileMenuOpen = false"
                        >Features</a
                    >
                    <a
                        href="#modules"
                        class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
                        @click="mobileMenuOpen = false"
                        >Modules</a
                    >
                    <a
                        href="#pricing"
                        class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
                        @click="mobileMenuOpen = false"
                        >Pricing</a
                    >
                    <hr class="my-2 border-border" />
                    <Link
                        :href="login()"
                        class="rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent"
                        >Log in</Link
                    >
                    <Link
                        :href="checkoutIndex()"
                        class="rounded-md bg-primary px-3 py-2 text-center text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >Start free trial</Link
                    >
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h1
                        class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                    >
                        Run your entire business from one place
                    </h1>
                    <p
                        class="mt-6 text-lg leading-relaxed text-muted-foreground"
                    >
                        Nexora brings CRM, project management, invoicing, HR and
                        support into a single modular platform. Pick only what
                        you need and scale as you grow.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <Link
                            :href="checkoutIndex()"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Start free trial
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <a
                            href="#modules"
                            class="inline-flex items-center gap-2 rounded-md border border-border px-6 py-3 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                        >
                            Explore modules
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <img
                        src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80"
                        alt="Team collaborating at a desk"
                        class="rounded-2xl object-cover shadow-xl"
                    />
                </div>
            </div>
        </section>

        <!-- Trust Bar -->
        <section class="bg-muted/50 py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p
                    class="text-center text-xs font-medium tracking-widest text-muted-foreground uppercase"
                >
                    Trusted by growing businesses
                </p>
                <div
                    class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-4"
                >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >Brightpath</span
                    >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >Driftwood</span
                    >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >Verve Co.</span
                    >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >Peakline</span
                    >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >Stonemill</span
                    >
                    <span class="text-lg font-semibold text-muted-foreground/50"
                        >ArcVenture</span
                    >
                </div>
                <div
                    class="mt-8 flex flex-wrap items-center justify-center gap-6"
                >
                    <div
                        class="flex items-center gap-2 rounded-full bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm"
                    >
                        <Users class="h-4 w-4 text-muted-foreground" />
                        500+ teams
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-full bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm"
                    >
                        <Zap class="h-4 w-4 text-muted-foreground" />
                        99.9% uptime
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-full bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm"
                    >
                        <Puzzle class="h-4 w-4 text-muted-foreground" />
                        5 modules
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section
            id="features"
            class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8"
        >
            <div class="text-center">
                <h2
                    class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                >
                    Everything you need to run your business
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                    Built for teams that want simplicity without sacrificing
                    power.
                </p>
            </div>
            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border border-border bg-card p-6 transition-shadow hover:shadow-md"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <component
                            :is="feature.icon"
                            class="h-5 w-5 text-primary"
                        />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-card-foreground">
                        {{ feature.title }}
                    </h3>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ feature.description }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Modules Showcase -->
        <section id="modules" class="bg-muted/30 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2
                        class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                    >
                        Pick the modules you need
                    </h2>
                    <p
                        class="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground"
                    >
                        Start with one module or bundle them all. Each module
                        works seamlessly with the others.
                    </p>
                </div>
                <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="mod in modules"
                        :key="mod.id"
                        class="flex flex-col rounded-xl border border-border bg-card p-6 transition-shadow hover:shadow-md"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
                        >
                            <component
                                :is="moduleIcons[mod.slug] ?? Puzzle"
                                class="h-5 w-5 text-primary"
                            />
                        </div>
                        <h3
                            class="mt-4 text-lg font-semibold text-card-foreground"
                        >
                            {{ mod.name }}
                        </h3>
                        <p
                            class="mt-2 flex-1 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ mod.description }}
                        </p>
                        <div class="mt-6 flex items-center justify-between">
                            <span class="text-2xl font-bold text-foreground">
                                {{ formatPrice(mod.monthly_price_cents)
                                }}<span
                                    class="text-sm font-normal text-muted-foreground"
                                    >/mo</span
                                >
                            </span>
                            <Link
                                :href="checkoutIndex()"
                                class="rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                            >
                                Add to plan
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2
                    class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                >
                    Up and running in three steps
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                    Getting started with Nexora is fast and straightforward.
                </p>
            </div>
            <div class="mt-16 grid gap-12 md:grid-cols-3">
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="text-center"
                >
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary text-xl font-bold text-primary-foreground"
                    >
                        {{ index + 1 }}
                    </div>
                    <div class="mt-4 flex justify-center">
                        <component
                            :is="step.icon"
                            class="h-6 w-6 text-muted-foreground"
                        />
                    </div>
                    <h3 class="mt-3 text-lg font-semibold text-foreground">
                        {{ step.title }}
                    </h3>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ step.description }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="bg-muted/30 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2
                        class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                    >
                        Loved by teams everywhere
                    </h2>
                </div>
                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <div
                        v-for="testimonial in testimonials"
                        :key="testimonial.name"
                        class="rounded-xl border border-border bg-card p-6"
                    >
                        <div class="flex items-center gap-1">
                            <Star
                                v-for="n in 5"
                                :key="n"
                                class="h-4 w-4 fill-amber-400 text-amber-400"
                            />
                        </div>
                        <p
                            class="mt-4 text-sm leading-relaxed text-muted-foreground"
                        >
                            "{{ testimonial.quote }}"
                        </p>
                        <div class="mt-6 flex items-center gap-3">
                            <img
                                :src="testimonial.avatar"
                                :alt="testimonial.name"
                                class="h-10 w-10 rounded-full object-cover"
                            />
                            <div>
                                <p
                                    class="text-sm font-semibold text-card-foreground"
                                >
                                    {{ testimonial.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ testimonial.role }},
                                    {{ testimonial.company }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Teaser -->
        <section
            id="pricing"
            class="mx-auto max-w-7xl px-4 py-20 text-center sm:px-6 lg:px-8"
        >
            <h2
                class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                Simple, transparent pricing
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                Pay only for the modules you use. No hidden fees, no long-term
                contracts.
            </p>
            <div class="mt-8">
                <Link
                    :href="checkoutIndex()"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-6 py-3 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                >
                    View pricing
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="bg-primary py-20">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <h2
                    class="text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl"
                >
                    Ready to streamline your business?
                </h2>
                <p class="mt-4 text-lg text-primary-foreground/80">
                    Join hundreds of teams already using Nexora to work smarter.
                    Start your free trial today.
                </p>
                <div class="mt-8">
                    <Link
                        :href="checkoutIndex()"
                        class="inline-flex items-center gap-2 rounded-md bg-background px-8 py-3 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-background/90"
                    >
                        Start free trial
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border bg-background py-16">
            <div
                class="mx-auto grid max-w-7xl gap-8 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8"
            >
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 text-foreground">
                        <Layers class="h-6 w-6" />
                        <span class="text-lg font-bold tracking-tight"
                            >Nexora</span
                        >
                    </div>
                    <p class="mt-3 text-sm text-muted-foreground">
                        The modular business suite for growing teams.
                    </p>
                    <p class="mt-6 text-xs text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Nexora. All rights
                        reserved.
                    </p>
                </div>

                <!-- Product -->
                <div>
                    <h4 class="text-sm font-semibold text-foreground">
                        Product
                    </h4>
                    <ul class="mt-4 flex flex-col gap-2">
                        <li>
                            <a
                                href="#features"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Features</a
                            >
                        </li>
                        <li>
                            <a
                                href="#modules"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Modules</a
                            >
                        </li>
                        <li>
                            <a
                                href="#pricing"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Pricing</a
                            >
                        </li>
                        <li>
                            <Link
                                :href="checkoutIndex()"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Get started</Link
                            >
                        </li>
                    </ul>
                </div>

                <!-- Company -->
                <div>
                    <h4 class="text-sm font-semibold text-foreground">
                        Company
                    </h4>
                    <ul class="mt-4 flex flex-col gap-2">
                        <li>
                            <a
                                href="#"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >About</a
                            >
                        </li>
                        <li>
                            <a
                                href="#"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Blog</a
                            >
                        </li>
                        <li>
                            <a
                                href="#"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Careers</a
                            >
                        </li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-sm font-semibold text-foreground">Legal</h4>
                    <ul class="mt-4 flex flex-col gap-2">
                        <li>
                            <a
                                href="#"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Privacy Policy</a
                            >
                        </li>
                        <li>
                            <a
                                href="#"
                                class="text-sm text-muted-foreground hover:text-foreground"
                                >Terms of Service</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </footer>
    </div>
</template>
