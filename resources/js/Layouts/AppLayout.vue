<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FloatingChat from '@/Components/FloatingChat.vue';

defineProps({
    title: {
        type: String,
        default: 'Nalabumi AI'
    }
});

const shouldShowFloatingChat = computed(() => {
    return !route().current('landing') && !route().current('chat');
});
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen flex flex-col">
        <nav class="fixed top-0 w-full z-50 flex justify-between items-center px-10 md:px-10 h-16 bg-surface border-b border-outline-variant shadow-sm">
            <div class="flex items-center gap-8">
                <Link href="/" class="flex items-center">
                    <img :src="'/logo.svg'" alt="Nalabumi Logo" class="h-10 w-auto object-contain" />
                </Link>
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
            <div></div>
        </nav>
        <main class="flex-1 pt-16">
            <slot />
        </main>
        
        <FloatingChat v-if="shouldShowFloatingChat" />
    </div>
</template>
