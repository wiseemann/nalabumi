<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import { marked } from 'marked';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const isOpen = ref(false);
const messages = ref([]);
const question = ref('');
const isLoading = ref(false);
const chatContainer = ref(null);

const STORAGE_KEY = 'nalabumi_floating_chat_history';

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            messages.value = JSON.parse(saved);
        } catch (e) {
            console.error('Failed to parse floating chat history', e);
        }
    }
});

watch(messages, (newMessages) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(newMessages));
}, { deep: true });

async function scrollToBottom() {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

function toggleChat() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        scrollToBottom();
    }
}

async function sendMessage() {
    if (!question.value.trim() || isLoading.value) return;

    const currentQuestion = question.value;
    messages.value.push({ role: 'user', content: currentQuestion });
    question.value = '';
    
    isLoading.value = true;
    scrollToBottom();

    try {
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: currentQuestion }),
        });
        const data = await response.json();
        messages.value.push({ role: 'assistant', content: data.answer, thought: data.thought, sources: data.sources });
    } catch (e) {
        messages.value.push({ role: 'assistant', content: 'Maaf, terjadi kesalahan.' });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
}

function renderMarkdown(text) {
    return marked(text);
}

const showClearConfirm = ref(false);

function promptClearChat() {
    showClearConfirm.value = true;
}

function confirmClearChat() {
    messages.value = [];
    localStorage.removeItem(STORAGE_KEY);
    showClearConfirm.value = false;
}

function cancelClearChat() {
    showClearConfirm.value = false;
}
</script>

<template>
    <div class="fixed bottom-6 right-6 z-[100]">
        <!-- Floating Button -->
        <button 
            @click="toggleChat"
            class="w-14 h-14 rounded-full bg-primary text-on-primary shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center focus:outline-none focus:ring-4 focus:ring-primary/30"
            :class="{'rotate-12': isOpen}"
        >
            <span class="material-symbols-outlined text-3xl transition-transform" v-if="!isOpen">forum</span>
            <span class="material-symbols-outlined text-3xl transition-transform" v-else>close</span>
        </button>

        <!-- Chat Window -->
        <Transition name="slide-up">
            <div v-if="isOpen" class="absolute bottom-20 right-0 w-[350px] sm:w-[400px] h-[550px] max-h-[80vh] bg-surface-container-low border border-outline-variant/50 rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                
                <!-- Header -->
                <div class="bg-primary text-on-primary px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-white rounded-full p-1 flex items-center justify-center shrink-0">
                            <img :src="'/favicon.svg'" alt="Nalabumi" class="w-full h-full object-contain" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm">Nalabumi AI</h3>
                            <p class="text-[10px] text-primary-container">Asisten Lingkungan Anda</p>
                        </div>
                    </div>
                    <button @click="promptClearChat" class="text-on-primary/80 hover:text-white transition-colors" title="Hapus Obrolan">
                        <span class="material-symbols-outlined text-xl">delete_sweep</span>
                    </button>
                </div>

                <!-- Messages Area -->
                <div ref="chatContainer" class="flex-1 overflow-y-auto p-4 custom-scrollbar bg-surface-container-low">
                    
                    <div v-if="messages.length === 0" class="h-full flex flex-col items-center justify-center text-center text-on-surface-variant px-4">
                        <img :src="'/favicon.svg'" class="w-16 h-16 opacity-50 mb-4" />
                        <p class="text-sm font-medium">Ada yang bisa saya bantu terkait lingkungan?</p>
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div v-for="(msg, i) in messages" :key="i" class="flex w-full" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            
                            <!-- User Message -->
                            <div v-if="msg.role === 'user'" class="max-w-[85%] bg-primary text-on-primary rounded-xl rounded-br-sm px-4 py-2.5 shadow-sm text-sm">
                                {{ msg.content }}
                            </div>

                            <!-- AI Message -->
                            <div v-else class="max-w-[90%] flex flex-col gap-1.5">
                                <details v-if="msg.thought" class="group bg-surface-container border border-outline-variant/40 rounded-lg px-3 py-1.5 shadow-sm cursor-pointer w-fit max-w-full">
                                    <summary class="flex items-center gap-1.5 text-[10px] font-semibold text-on-surface-variant uppercase outline-none select-none">
                                        <span class="material-symbols-outlined text-[14px] transition-transform group-open:rotate-90">chevron_right</span>
                                        Proses Berpikir
                                    </summary>
                                    <div class="mt-2 pt-2 border-t border-outline-variant/30 text-[11px] text-on-surface-variant/80 whitespace-pre-wrap max-h-32 overflow-y-auto custom-scrollbar">
                                        {{ msg.thought }}
                                    </div>
                                </details>

                                <div class="bg-surface-container-lowest border border-outline-variant/60 rounded-xl rounded-bl-sm px-4 py-3 shadow-sm text-sm text-on-surface">
                                    <div class="prose prose-sm max-w-none prose-p:my-1 prose-ul:my-1 prose-li:my-0.5" v-html="renderMarkdown(msg.content)"></div>
                                </div>

                                <!-- Sources -->
                                <div v-if="msg.sources?.length" class="flex flex-wrap gap-1.5 mt-0.5">
                                    <a v-for="s in msg.sources" :key="s.id" :href="s.url" target="_blank"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded bg-secondary-container/30 border border-secondary-container/50 text-secondary text-[10px] font-medium transition-colors hover:bg-secondary-container/50 truncate max-w-[150px]">
                                        <span class="material-symbols-outlined text-[12px] shrink-0">public</span>
                                        <span class="truncate">{{ s.source }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div v-if="isLoading" class="max-w-[85%]">
                            <div class="bg-surface-container-lowest border border-outline-variant/60 rounded-xl rounded-bl-sm px-4 py-3 shadow-sm flex items-center gap-1.5 h-10 w-fit">
                                <div class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0s;"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0.15s;"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-3 bg-surface border-t border-outline-variant/50 relative z-10">
                    <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                        <input 
                            v-model="question" 
                            type="text"
                            placeholder="Ketik pesan..."
                            class="flex-1 bg-surface-container-lowest border border-outline-variant/50 rounded-full px-4 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:ring-2 focus:ring-primary/50 focus:border-primary/50"
                            :disabled="isLoading"
                        />
                        <button type="submit" 
                            :disabled="isLoading || !question.trim()"
                            class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-colors"
                            :class="question.trim() && !isLoading ? 'bg-primary text-on-primary hover:bg-primary/90 shadow-sm' : 'bg-surface-container text-on-surface-variant/40 cursor-not-allowed'">
                            <span class="material-symbols-outlined text-xl" :class="{'animate-pulse': isLoading}">
                                {{ isLoading ? 'hourglass_empty' : 'send' }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Clear Chat Confirmation Overlay -->
                <div v-if="showClearConfirm" class="absolute inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
                    <div class="bg-surface-container-lowest rounded-xl p-5 w-full shadow-lg text-center">
                        <span class="material-symbols-outlined text-error text-3xl mb-2">delete_sweep</span>
                        <h4 class="font-bold text-on-surface text-sm mb-1">Hapus Obrolan?</h4>
                        <p class="text-xs text-on-surface-variant mb-4">Pesan tidak dapat dipulihkan.</p>
                        <div class="flex gap-2">
                            <button @click="cancelClearChat" class="flex-1 py-1.5 rounded border border-outline-variant text-xs font-semibold hover:bg-surface-container transition-colors">Batal</button>
                            <button @click="confirmClearChat" class="flex-1 py-1.5 rounded bg-error text-on-error text-xs font-semibold hover:bg-error/90 transition-colors">Hapus</button>
                        </div>
                    </div>
                </div>
                
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: bottom right;
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(150, 150, 150, 0.3);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(150, 150, 150, 0.5);
}

:deep(.prose) {
    color: var(--color-on-surface);
    line-height: 1.5;
}
:deep(.prose strong) {
    color: var(--color-on-surface);
    font-weight: 600;
}
:deep(.prose a) {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 500;
}
:deep(.prose a:hover) {
    text-decoration: underline;
}
:deep(.prose code) {
    background-color: var(--color-surface-container);
    padding: 0.1em 0.3em;
    border-radius: 0.25rem;
    font-size: 0.85em;
    color: var(--color-on-surface);
}
</style>
