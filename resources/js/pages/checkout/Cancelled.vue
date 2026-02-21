<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { index } from '@/routes/checkout';
</script>

<template>
    <AuthBase
        title="Checkout cancelled"
        description="Your checkout session was cancelled"
    >
        <Head title="Checkout Cancelled" />

        <div class="flex flex-col items-center gap-6 text-center">
            <p class="text-sm text-muted-foreground">
                No charges were made. You can start over whenever you're ready.
            </p>

            <TextLink :href="index()" class="text-sm">
                Back to plan builder
            </TextLink>
        </div>
    </AuthBase>
</template>
