<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/checkout';

interface Module {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    monthly_price_cents: number;
    annual_price_cents: number;
}

const props = defineProps<{
    modules: Module[];
    minimumSeats: number;
    billingIntervals: string[];
}>();

const form = useForm({
    email: '',
    module_slugs: [] as string[],
    seat_limit: props.minimumSeats,
    usage_quota: 1000,
    billing_interval: 'monthly',
});

const isAnnual = computed(() => form.billing_interval === 'annual');

function toggleModule(slug: string) {
    const index = form.module_slugs.indexOf(slug);
    if (index === -1) {
        form.module_slugs.push(slug);
    } else {
        form.module_slugs.splice(index, 1);
    }
}

function isModuleSelected(slug: string): boolean {
    return form.module_slugs.includes(slug);
}

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function modulePrice(module: Module): number {
    return isAnnual.value ? module.annual_price_cents : module.monthly_price_cents;
}

const modulesTotal = computed(() => {
    return props.modules
        .filter((m) => form.module_slugs.includes(m.slug))
        .reduce((sum, m) => sum + modulePrice(m), 0);
});

const total = computed(() => modulesTotal.value);

const billingLabel = computed(() => (isAnnual.value ? '/year' : '/month'));

function submit() {
    form.post(store.url());
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <Head title="Choose Your Plan" />

        <div class="mx-auto w-full max-w-5xl px-6 py-12">
            <div class="mb-10 text-center">
                <h1 class="text-3xl font-bold tracking-tight">Build Your Plan</h1>
                <p class="mt-2 text-muted-foreground">Select the modules you need and start your 14-day free trial.</p>
            </div>

            <div class="grid gap-10 lg:grid-cols-[1fr_320px]">
                <div class="space-y-8">
                    <div>
                        <h2 class="mb-4 text-lg font-semibold">Billing Interval</h2>
                        <div class="flex gap-2">
                            <Button
                                :variant="form.billing_interval === 'monthly' ? 'default' : 'outline'"
                                @click="form.billing_interval = 'monthly'"
                            >
                                Monthly
                            </Button>
                            <Button
                                :variant="form.billing_interval === 'annual' ? 'default' : 'outline'"
                                @click="form.billing_interval = 'annual'"
                            >
                                Annual
                                <span class="ml-1 text-xs opacity-75">(Save ~17%)</span>
                            </Button>
                        </div>
                    </div>

                    <div>
                        <h2 class="mb-4 text-lg font-semibold">Modules</h2>
                        <InputError :message="form.errors.module_slugs" class="mb-2" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                v-for="module in modules"
                                :key="module.slug"
                                type="button"
                                class="flex items-start gap-3 rounded-lg border p-4 text-left transition-colors"
                                :class="[
                                    isModuleSelected(module.slug)
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50',
                                ]"
                                @click="toggleModule(module.slug)"
                            >
                                <Checkbox
                                    :checked="isModuleSelected(module.slug)"
                                    class="mt-0.5"
                                />
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">{{ module.name }}</span>
                                        <span class="text-sm text-muted-foreground">
                                            {{ formatPrice(modulePrice(module)) }}{{ billingLabel }}
                                        </span>
                                    </div>
                                    <p v-if="module.description" class="mt-1 text-sm text-muted-foreground">
                                        {{ module.description }}
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="seat_limit">Seats (min {{ minimumSeats }})</Label>
                            <Input
                                id="seat_limit"
                                type="number"
                                v-model.number="form.seat_limit"
                                :min="minimumSeats"
                                step="1"
                            />
                            <InputError :message="form.errors.seat_limit" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="usage_quota">Usage Quota (units)</Label>
                            <Input
                                id="usage_quota"
                                type="number"
                                v-model.number="form.usage_quota"
                                :min="1"
                                step="100"
                            />
                            <InputError :message="form.errors.usage_quota" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="you@company.com"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="lg:sticky lg:top-8 lg:self-start">
                    <div class="rounded-lg border bg-card p-6">
                        <h3 class="mb-4 text-lg font-semibold">Summary</h3>

                        <div class="space-y-2 text-sm">
                            <template v-for="module in modules" :key="module.slug">
                                <div
                                    v-if="isModuleSelected(module.slug)"
                                    class="flex justify-between"
                                >
                                    <span>{{ module.name }}</span>
                                    <span>{{ formatPrice(modulePrice(module)) }}</span>
                                </div>
                            </template>

                            <div v-if="form.module_slugs.length === 0" class="text-muted-foreground">
                                Select at least one module
                            </div>
                        </div>

                        <div class="mt-4 border-t pt-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-muted-foreground">{{ form.seat_limit }} seat(s) included</span>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-muted-foreground">{{ form.usage_quota.toLocaleString() }} usage units</span>
                            </div>
                        </div>

                        <div class="mt-4 border-t pt-4">
                            <div class="flex items-baseline justify-between">
                                <span class="font-semibold">Total</span>
                                <div class="text-right">
                                    <span class="text-2xl font-bold">{{ formatPrice(total) }}</span>
                                    <span class="text-sm text-muted-foreground">{{ billingLabel }}</span>
                                </div>
                            </div>
                        </div>

                        <Button
                            class="mt-6 w-full"
                            :disabled="form.processing || form.module_slugs.length === 0"
                            @click="submit"
                        >
                            <Spinner v-if="form.processing" />
                            Start Free Trial
                        </Button>

                        <p class="mt-3 text-center text-xs text-muted-foreground">
                            14-day free trial. Cancel anytime.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
