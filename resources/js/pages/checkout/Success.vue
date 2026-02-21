<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="Check your inbox"
        description="Your subscription has been created successfully"
    >
        <Head title="Checkout Success" />

        <div class="flex flex-col items-center gap-6 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-8 w-8 text-green-600 dark:text-green-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <p class="text-sm text-muted-foreground">
                We've sent an activation email with a link to complete your account setup.
                The link will expire in 7 days.
            </p>

            <TextLink :href="login()" class="text-sm">
                Go to login
            </TextLink>
        </div>
    </AuthBase>
</template>
