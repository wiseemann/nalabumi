<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { marked } from 'marked';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const messages = ref([]);
const question = ref('');
const isLoading = ref(false);

const exampleQuestions = [
    'Kabupaten mana yang memiliki tingkat deforestasi tertinggi?',
    'Wilayah mana yang paling terdampak pencemaran sungai?',
    'Apa penyebab utama kebakaran hutan bulan ini?',
    'Bagaimana tren kerusakan lingkungan di Kalimantan Timur?',
];

async function sendMessage() {
    if (!question.value.trim() || isLoading.value) return;

    const userMessage = { role: 'user', content: question.value };
    messages.value.push(userMessage);
    const currentQuestion = question.value;
    question.value = '';
    isLoading.value = true;

    try {
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: currentQuestion }),
        });
        const data = await response.json();
        messages.value.push({ role: 'assistant', content: data.answer, sources: data.sources });
    } catch (e) {
        messages.value.push({ role: 'assistant', content: 'Maaf, terjadi kesalahan. Silakan coba lagi.' });
    } finally {
        isLoading.value = false;
    }
}

function useExample(q) {
    question.value = q;
    sendMessage();
}

function renderMarkdown(text) {
    return marked(text);
}
</script>

<template>
    <AppLayout title="Chatbot">
        <div class="max-w-4xl mx-auto py-8 px-4 h-screen flex flex-col">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Chatbot Nalabumi AI</h1>

            <div class="flex-1 overflow-y-auto bg-white rounded-lg shadow p-4 mb-4 space-y-4">
                <div v-if="messages.length === 0" class="text-center text-gray-500 py-8">
                    <p class="mb-4">Ajukan pertanyaan tentang lingkungan Indonesia</p>
                    <div class="space-y-2">
                        <button v-for="q in exampleQuestions" :key="q"
                            @click="useExample(q)"
                            class="block w-full text-left text-sm p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                            {{ q }}
                        </button>
                    </div>
                </div>

                <div v-for="(msg, i) in messages" :key="i" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] rounded-lg p-3"
                        :class="msg.role === 'user' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-900'">
                        <div v-if="msg.role === 'assistant'" class="text-sm prose prose-sm max-w-none prose-p:my-1 prose-ul:my-1 prose-li:my-0.5" v-html="renderMarkdown(msg.content)"></div>
                        <p v-else class="text-sm whitespace-pre-wrap">{{ msg.content }}</p>
                        <div v-if="msg.sources?.length" class="mt-2 pt-2 border-t border-gray-200">
                            <p class="text-xs font-medium mb-1">Sumber:</p>
                            <ul class="text-xs space-y-1">
                                <li v-for="s in msg.sources" :key="s.id">
                                    <a :href="s.url" target="_blank" class="text-emerald-600 hover:underline">{{ s.title }}</a>
                                    <span class="text-gray-500 ml-1">({{ s.source }})</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div v-if="isLoading" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg p-3">
                        <div class="flex space-x-2">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input v-model="question" type="text" placeholder="Tulis pertanyaan..."
                    class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    :disabled="isLoading" />
                <button type="submit" :disabled="isLoading || !question.trim()"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Kirim
                </button>
            </form>
        </div>
    </AppLayout>
</template>
