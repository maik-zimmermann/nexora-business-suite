<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    tenants: Array<{
        name: string;
        slug: string;
        url: string;
    }>;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Select Organisation" />

        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <a :href="home()" class="flex flex-col items-center gap-2 font-medium">
                        <div class="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                            <AppLogoIcon class="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                        </div>
                    </a>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">Select an organisation</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            Choose the workspace you'd like to open
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <a
                        v-for="tenant in tenants"
                        :key="tenant.slug"
                        :href="tenant.url"
                        class="flex items-center gap-3 rounded-lg border border-border bg-card p-4 transition-colors hover:bg-accent"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <Building2 class="h-5 w-5" />
                        </div>
                        <div class="grid flex-1 text-left">
                            <span class="text-sm font-medium">{{ tenant.name }}</span>
                            <span class="text-xs text-muted-foreground">{{ tenant.slug }}</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
