<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { marked } from 'marked';
import { Link } from '@inertiajs/vue3';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const messages = ref([]);
const question = ref('');
const isLoading = ref(false);
const chatContainer = ref(null);
const textareaRef = ref(null);

const exampleQuestions = [
    { text: 'Kabupaten mana yang memiliki tingkat deforestasi tertinggi?', icon: 'forest' },
    { text: 'Wilayah mana yang paling terdampak pencemaran sungai?', icon: 'water_drop' },
    { text: 'Apa penyebab utama kebakaran hutan bulan ini?', icon: 'local_fire_department' },
    { text: 'Bagaimana tren kerusakan lingkungan di Kalimantan Timur?', icon: 'trending_up' },
];

async function scrollToBottom() {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTo({
            top: chatContainer.value.scrollHeight,
            behavior: 'smooth'
        });
    }
}

async function sendMessage() {
    if (!question.value.trim() || isLoading.value) return;

    const userMessage = { role: 'user', content: question.value };
    messages.value.push(userMessage);
    const currentQuestion = question.value;
    
    question.value = '';
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto'; // Reset height
    }
    
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
        messages.value.push({ role: 'assistant', content: 'Maaf, terjadi kesalahan. Silakan coba lagi.' });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
}

function useExample(q) {
    question.value = q;
    sendMessage();
}

function renderMarkdown(text) {
    return marked(text);
}

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
}

const CHAT_STORAGE_KEY = 'nalabumi_chat_history';
const showClearConfirm = ref(false);

onMounted(() => {
    const saved = localStorage.getItem(CHAT_STORAGE_KEY);
    if (saved) {
        try {
            messages.value = JSON.parse(saved);
            nextTick(() => {
                if (chatContainer.value) {
                    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                }
            });
        } catch (e) {
            console.error('Failed to parse chat history', e);
        }
    }
});

watch(messages, (newMessages) => {
    localStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(newMessages));
}, { deep: true });

function promptClearChat() {
    showClearConfirm.value = true;
}

function confirmClearChat() {
    messages.value = [];
    localStorage.removeItem(CHAT_STORAGE_KEY);
    showClearConfirm.value = false;
}

function cancelClearChat() {
    showClearConfirm.value = false;
}
</script>

<template>
    <AppLayout title="Nalabumi AI">
        <div class="h-[calc(100vh-4rem)] flex flex-col bg-surface-container-low relative overflow-hidden">
            
            <!-- Chat Area -->
            <div ref="chatContainer" class="flex-1 overflow-y-auto px-4 md:px-8 pt-8 pb-40 custom-scrollbar scroll-smooth">
                <div class="max-w-4xl mx-auto flex flex-col gap-6">
                    
                    <!-- Empty State -->
                    <div v-if="messages.length === 0" class="flex flex-col items-center justify-center min-h-[50vh] mt-4 animate-fade-in">
                        <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mb-6 overflow-hidden p-3">
                            <img :src="'/favicon.svg'" alt="Nalabumi Logo" class="w-full h-full object-contain" />
                        </div>
                        <h1 class="text-3xl font-bold text-on-surface mb-2 text-center">Halo, saya Nalabumi AI</h1>
                        <p class="text-on-surface-variant text-center max-w-lg mb-10 text-sm">
                            Asisten pintar Anda untuk menganalisis data, memahami tren, dan menjawab pertanyaan seputar lingkungan hidup di Indonesia.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full max-w-3xl">
                            <button v-for="q in exampleQuestions" :key="q.text"
                                @click="useExample(q.text)"
                                class="flex items-start gap-4 p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/50 hover:border-primary/40 hover:shadow-md hover:-translate-y-1 transition-all duration-300 text-left group">
                                <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center shrink-0 group-hover:bg-primary/10 transition-colors">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors text-[20px]">{{ q.icon }}</span>
                                </div>
                                <span class="text-sm font-medium text-on-surface-variant group-hover:text-on-surface leading-snug mt-1">{{ q.text }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <template v-else>
                        <div class="flex justify-end mb-2">
                            <button @click="promptClearChat" class="text-xs text-error hover:text-error/80 flex items-center gap-1.5 bg-error/5 hover:bg-error/10 px-3 py-1.5 rounded-full transition-colors border border-error/10">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                Hapus Obrolan
                            </button>
                        </div>

                        <div v-for="(msg, i) in messages" :key="i" class="flex w-full animate-fade-in-up" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            
                            <!-- User Message -->
                            <div v-if="msg.role === 'user'" 
                                class="max-w-[85%] md:max-w-[75%] bg-primary text-on-primary rounded-2xl rounded-tr-sm px-5 py-3.5 shadow-sm">
                                <p class="text-[15px] leading-relaxed whitespace-pre-wrap">{{ msg.content }}</p>
                            </div>

                            <!-- AI Message -->
                            <div v-else class="flex items-start gap-4 max-w-[95%] md:max-w-[85%]">
                                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center shrink-0 shadow-sm border border-primary-fixed-dim overflow-hidden p-2">
                                    <img :src="'/favicon.svg'" alt="Nalabumi AI" class="w-full h-full object-contain" />
                                </div>
                                <div class="flex flex-col gap-2 w-full min-w-0">
                                    
                                    <!-- Thought Process -->
                                    <details v-if="msg.thought" class="group bg-surface-container border border-outline-variant/40 rounded-2xl px-4 py-2.5 shadow-sm cursor-pointer mb-1 w-fit max-w-full">
                                        <summary class="flex items-center gap-2 text-[11px] font-semibold text-on-surface-variant uppercase tracking-wider outline-none select-none">
                                            <span class="material-symbols-outlined text-[16px] transition-transform group-open:rotate-90">chevron_right</span>
                                            Proses Berpikir
                                        </summary>
                                        <div class="mt-3 pt-3 border-t border-outline-variant/30 text-[13px] text-on-surface-variant/80 whitespace-pre-wrap leading-relaxed max-h-64 overflow-y-auto custom-scrollbar">
                                            {{ msg.thought }}
                                        </div>
                                    </details>

                                    <div class="bg-surface-container-lowest border border-outline-variant/60 rounded-2xl rounded-tl-sm px-5 py-4 shadow-sm">
                                        <div class="prose prose-sm max-w-none prose-p:my-1.5 prose-ul:my-1.5 prose-li:my-0.5 text-on-surface break-words" 
                                            v-html="renderMarkdown(msg.content)"></div>
                                    </div>
                                    
                                    <!-- Sources -->
                                    <div v-if="msg.sources?.length" class="flex flex-wrap gap-2 mt-1">
                                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider py-1">Sumber:</span>
                                        <a v-for="s in msg.sources" :key="s.id" :href="s.url" target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-secondary-container/30 border border-secondary-container/50 hover:bg-secondary-container/50 text-secondary text-xs font-medium transition-colors max-w-full">
                                            <span class="material-symbols-outlined text-[14px] shrink-0">public</span>
                                            <span class="truncate max-w-[200px]">{{ s.source }}</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Indicator -->
                        <div v-if="isLoading" class="flex items-start gap-4 max-w-[90%] md:max-w-[85%] animate-fade-in-up">
                            <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center shrink-0 shadow-sm border border-primary-fixed-dim overflow-hidden p-2">
                                <img :src="'/favicon.svg'" alt="Nalabumi AI" class="w-full h-full object-contain animate-pulse" />
                            </div>
                            <div class="bg-surface-container-lowest border border-outline-variant/60 rounded-2xl rounded-tl-sm px-5 py-4 shadow-sm flex items-center gap-1.5 h-[52px]">
                                <div class="w-2 h-2 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0s;"></div>
                                <div class="w-2 h-2 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0.15s;"></div>
                                <div class="w-2 h-2 rounded-full bg-primary/60 animate-bounce" style="animation-delay: 0.3s;"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Input Area -->
            <div class="absolute bottom-0 left-0 right-0 pt-10 pb-6 px-4 bg-gradient-to-t from-surface-container-low via-surface-container-low/95 to-transparent pointer-events-none">
                <div class="max-w-3xl mx-auto pointer-events-auto">
                    <form @submit.prevent="sendMessage" class="relative flex items-end gap-2 glass-panel p-2 rounded-2xl transition-all duration-300 focus-within:ring-2 focus-within:ring-primary/20 shadow-[0_8px_30px_rgba(0,0,0,0.08)]">
                        <textarea 
                            ref="textareaRef"
                            v-model="question" 
                            rows="1"
                            placeholder="Tanyakan sesuatu pada Nalabumi..."
                            class="flex-1 bg-transparent border-none focus:ring-0 text-on-surface placeholder:text-on-surface-variant/60 resize-none py-3.5 px-4 max-h-[120px] custom-scrollbar text-[15px]"
                            @keydown.enter.exact.prevent="sendMessage"
                            @input="autoResize"
                            :disabled="isLoading"
                        ></textarea>
                        
                        <button type="submit" 
                            :disabled="isLoading || !question.trim()"
                            class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-300 mb-1 mr-1"
                            :class="question.trim() && !isLoading ? 'bg-primary text-on-primary hover:bg-primary/90 hover:shadow-lg hover:-translate-y-0.5' : 'bg-surface-container text-on-surface-variant/40 cursor-not-allowed'">
                            <span class="material-symbols-outlined filled text-[22px]" :class="{'animate-spin': isLoading && !question.trim(), 'animate-pulse': isLoading}">
                                {{ isLoading ? 'hourglass_empty' : 'send' }}
                            </span>
                        </button>
                    </form>
                    <div class="text-center mt-3">
                        <span class="text-[11px] text-on-surface-variant/60">Nalabumi AI dapat membuat kesalahan. Harap periksa kembali informasi penting.</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Custom Confirm Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showClearConfirm" class="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4">
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 max-w-sm w-full shadow-lg">
                        <div class="flex flex-col items-center text-center gap-3">
                            <div class="w-12 h-12 bg-error/10 text-error rounded-full flex items-center justify-center mb-1">
                                <span class="material-symbols-outlined text-[28px]">delete_sweep</span>
                            </div>
                            <h3 class="text-lg font-bold text-on-surface">Hapus Riwayat Obrolan?</h3>
                            <p class="text-sm text-on-surface-variant leading-relaxed">
                                Seluruh pesan Anda dengan Nalabumi AI akan dihapus permanen dan tidak dapat dipulihkan.
                            </p>
                        </div>
                        <div class="flex gap-3 mt-6">
                            <button @click="cancelClearChat" class="flex-1 py-2.5 px-4 rounded-xl border border-outline-variant text-on-surface hover:bg-surface-container transition-colors text-sm font-semibold">
                                Batal
                            </button>
                            <button @click="confirmClearChat" class="flex-1 py-2.5 px-4 rounded-xl bg-error text-on-error hover:bg-error/90 shadow-sm transition-colors text-sm font-semibold">
                                Ya, Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
/* Modal Transition */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-out forwards;
}

.animate-fade-in-up {
    animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Typography styles for markdown content */
:deep(.prose) {
    color: var(--color-on-surface);
    line-height: 1.6;
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
:deep(.prose h1), :deep(.prose h2), :deep(.prose h3), :deep(.prose h4) {
    color: var(--color-on-surface);
    margin-top: 1.2em;
    margin-bottom: 0.6em;
    font-weight: 600;
    line-height: 1.3;
}
:deep(.prose code) {
    background-color: var(--color-surface-container);
    padding: 0.2em 0.4em;
    border-radius: 0.375rem;
    font-size: 0.85em;
    color: var(--color-on-surface);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
:deep(.prose pre) {
    background-color: var(--color-inverse-surface);
    padding: 1rem;
    border-radius: 0.75rem;
    overflow-x: auto;
    margin: 1em 0;
}
:deep(.prose pre code) {
    background-color: transparent;
    padding: 0;
    color: var(--color-inverse-on-surface);
    font-size: 0.875em;
}
:deep(.prose blockquote) {
    border-left: 4px solid var(--color-primary-fixed-dim);
    padding-left: 1rem;
    color: var(--color-on-surface-variant);
    font-style: italic;
}
:deep(.prose ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
}
:deep(.prose ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
}
</style>
