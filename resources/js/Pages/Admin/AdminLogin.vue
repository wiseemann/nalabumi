<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Admin Login — Nalabumi" />
    <div class="min-h-screen bg-surface flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <!-- Logo & Title -->
            <div class="text-center mb-8">
                <img :src="'/logo.svg'" alt="Nalabumi Logo" class="h-14 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-on-surface">Admin Panel</h1>
                <p class="text-on-surface-variant text-sm mt-1">Masuk untuk mengelola platform Nalabumi</p>
            </div>

            <!-- Login Card -->
            <div class="glass-panel rounded-xl p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-on-surface mb-1.5">Email</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">mail</span>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                autofocus
                                class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all text-sm"
                                placeholder="admin@nalabumi.id"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-error font-medium">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-on-surface mb-1.5">Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all text-sm"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors"
                            >
                                <span class="material-symbols-outlined text-[20px]">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-error font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary"
                        />
                        <label for="remember" class="text-sm text-on-surface-variant select-none">Ingat saya</label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 bg-primary text-on-primary font-semibold rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm"
                    >
                        <span v-if="form.processing" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                        <span class="material-symbols-outlined text-[18px]" v-else>login</span>
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <!-- Back to public -->
            <div class="text-center mt-6">
                <a href="/" class="text-sm text-on-surface-variant hover:text-primary transition-colors inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Kembali ke Halaman Utama
                </a>
            </div>
        </div>
    </div>
</template>
