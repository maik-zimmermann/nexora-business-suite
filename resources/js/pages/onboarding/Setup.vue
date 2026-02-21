<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/onboarding';

const props = defineProps<{
    email: string;
    user: { id: number };
}>();

const form = useForm({
    name: '',
    organisation_name: '',
    slug: '',
    password: '',
    password_confirmation: '',
});

const slugManuallyEdited = ref(false);

watch(
    () => form.organisation_name,
    (value) => {
        if (!slugManuallyEdited.value) {
            form.slug = value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
        }
    },
);

const subdomainPreview = computed(() => {
    return form.slug ? `${form.slug}.nexora.io` : 'your-org.nexora.io';
});

function submit() {
    form.post(store.url(props.user));
}
</script>

<template>
    <AuthBase
        title="Complete your setup"
        description="Just a few details to get your workspace ready"
    >
        <Head title="Setup" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        :model-value="email"
                        disabled
                        class="opacity-60"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Full name</Label>
                    <Input
                        id="name"
                        type="text"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Your full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="organisation_name">Organisation name</Label>
                    <Input
                        id="organisation_name"
                        type="text"
                        v-model="form.organisation_name"
                        required
                        placeholder="Acme Inc."
                    />
                    <InputError :message="form.errors.organisation_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="slug">Subdomain</Label>
                    <Input
                        id="slug"
                        type="text"
                        v-model="form.slug"
                        required
                        placeholder="acme"
                        @input="slugManuallyEdited = true"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ subdomainPreview }}
                    </p>
                    <InputError :message="form.errors.slug" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Complete Setup
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
