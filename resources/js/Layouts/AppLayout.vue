<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FloatingChat from '@/Components/FloatingChat.vue';

defineProps({
    title: {
        type: String,
        default: 'Nalabumi AI'
    }
});

const mobileMenuOpen = ref(false);

const shouldShowFloatingChat = computed(() => {
    return !route().current('landing') && !route().current('chat');
});
</script>

<template>
    <Head :title="title" />
    <div class="flex flex-col" style="height: 100dvh; overflow: hidden;">
        <nav class="flex-shrink-0 w-full z-50 flex justify-between items-center px-4 md:px-10 h-16 bg-surface border-b border-outline-variant shadow-sm">
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
                        Archive
                    </Link>
                    <Link href="/chat" :class="[route().current('chat') ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-primary', 'py-4 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors']">
                        <span class="material-symbols-outlined text-[18px]">smart_toy</span>
                        Chatbot
                    </Link>
                </div>
            </div>
            
            <!-- Mobile Menu Toggle Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
            </button>
        </nav>

        <!-- Mobile Menu Dropdown -->
        <div v-if="mobileMenuOpen" class="md:hidden flex flex-col bg-surface border-b border-outline-variant shadow-md absolute top-16 left-0 w-full z-[60]">
            <Link href="/dashboard" @click="mobileMenuOpen = false" :class="[route().current('dashboard') ? 'bg-primary/10 text-primary border-l-4 border-primary' : 'text-on-surface hover:bg-surface-variant', 'px-6 py-4 font-semibold text-sm flex items-center gap-3 transition-colors']">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                Dashboard
            </Link>
            <Link href="/analytics" @click="mobileMenuOpen = false" :class="[route().current('analytics') ? 'bg-primary/10 text-primary border-l-4 border-primary' : 'text-on-surface hover:bg-surface-variant', 'px-6 py-4 font-semibold text-sm flex items-center gap-3 transition-colors']">
                <span class="material-symbols-outlined text-[20px]">analytics</span>
                Analytics
            </Link>
            <Link href="/archive" @click="mobileMenuOpen = false" :class="[route().current('archive') ? 'bg-primary/10 text-primary border-l-4 border-primary' : 'text-on-surface hover:bg-surface-variant', 'px-6 py-4 font-semibold text-sm flex items-center gap-3 transition-colors']">
                <span class="material-symbols-outlined text-[20px]">archive</span>
                Archive
            </Link>
            <Link href="/chat" @click="mobileMenuOpen = false" :class="[route().current('chat') ? 'bg-primary/10 text-primary border-l-4 border-primary' : 'text-on-surface hover:bg-surface-variant', 'px-6 py-4 font-semibold text-sm flex items-center gap-3 transition-colors']">
                <span class="material-symbols-outlined text-[20px]">smart_toy</span>
                Chatbot
            </Link>
        </div>
        <main class="flex-1 min-h-0 overflow-y-auto">
            <slot />
        </main>
        
        <FloatingChat v-if="shouldShowFloatingChat" />
    </div>
</template>
