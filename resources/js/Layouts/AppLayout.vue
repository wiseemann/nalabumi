<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: 'Nalabumi AI'
    }
});
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen flex flex-col">
        <nav class="fixed top-0 w-full z-50 flex justify-between items-center px-10 md:px-10 h-16 bg-surface border-b border-outline-variant shadow-sm">
            <div class="flex items-center gap-8">
                <Link href="/" class="text-primary font-bold text-xl tracking-tight">Nalabumi</Link>
                <div class="hidden md:flex items-center gap-6">
                    <Link href="/dashboard" :class="[route().current('dashboard') ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-primary', 'py-4 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors']">
                        <span class="material-symbols-outlined text-[18px]">dashboard</span>
                        Dashboard
                    </Link>
                    <Link href="/analytics" :class="[route().current('analytics') ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-primary', 'py-4 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors']">
                        <span class="material-symbols-outlined text-[18px]">analytics</span>
                        Analytics
                    </Link>
                    <Link href="/archive" :class="[route().current('archive') ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-primary', 'py-4 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors']">
                        <span class="material-symbols-outlined text-[18px]">archive</span>
                        Arsip
                    </Link>
                    <Link href="/chat" :class="[route().current('chat') ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-primary', 'py-4 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors']">
                        <span class="material-symbols-outlined text-[18px]">smart_toy</span>
                        Chatbot
                    </Link>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="px-4 py-2 bg-primary-container text-on-primary-container rounded-lg font-semibold text-xs hover:bg-primary hover:text-on-primary transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
                    Admin Login
                </button>
            </div>
        </nav>
        <main class="flex-1 pt-16">
            <slot />
        </main>
    </div>
</template>
