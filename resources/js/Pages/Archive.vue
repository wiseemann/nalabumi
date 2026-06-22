<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    articles: Object,
    categories: Array,
    provinces: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const region = ref(props.filters.region || '');
const category = ref(props.filters.category || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const status = ref(props.filters.status || '');

const drawerOpen = ref(false);
const selectedArticle = ref(null);

function applyFilters() {
    router.get('/archive', {
        search: search.value,
        region: region.value,
        category: category.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        status: status.value,
    }, { preserveState: true });
}

function resetFilters() {
    search.value = '';
    region.value = '';
    category.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    status.value = '';
    router.get('/archive', {}, { preserveState: true });
}

function openDrawer(article) {
    selectedArticle.value = article;
    drawerOpen.value = true;
}

function closeDrawer() {
    drawerOpen.value = false;
    selectedArticle.value = null;
}

function getStatusBadge(severity) {
    if (severity >= 61) return { text: 'Kritis', class: 'bg-error-container text-on-error-container border border-error/20' };
    if (severity >= 41) return { text: 'Tinggi', class: 'bg-tertiary-container text-on-tertiary-container border border-tertiary-container/50' };
    if (severity >= 21) return { text: 'Sedang', class: 'bg-secondary-container text-on-secondary-container border border-secondary-container/50' };
    return { text: 'Rendah', class: 'bg-primary-fixed-dim text-on-primary-fixed-variant border border-primary-fixed-dim' };
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <AppLayout title="Katalog Arsip">
        <div class="py-8 px-6 md:px-10 max-w-[1440px] mx-auto flex flex-col gap-8">
            <header class="flex flex-col gap-2">
                <h1 class="text-4xl font-bold text-on-surface">Katalog Arsip Data</h1>
                <p class="text-base text-on-surface-variant max-w-2xl">Akses komprehensif ke rekam jejak lingkungan. Filter, analisis, dan tinjau laporan masa lalu untuk perencanaan mitigasi yang lebih baik.</p>
            </header>

            <section class="flex flex-col gap-4">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                    <input v-model="search" @keyup.enter="applyFilters" class="w-full pl-10 pr-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-sm text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-sm" placeholder="Cari arsip berdasarkan wilayah, portal berita, atau judul..." type="text" />
                </div>

                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-wrap gap-4 items-end shadow-sm">
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">
                        <label class="text-xs font-semibold text-on-surface-variant">Wilayah (Region)</label>
                        <select v-model="region" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Semua Wilayah</option>
                            <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">
                        <label class="text-xs font-semibold text-on-surface-variant">Kategori</label>
                        <select v-model="category" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[180px]">
                        <label class="text-xs font-semibold text-on-surface-variant">Tanggal Mulai</label>
                        <input v-model="dateFrom" type="date" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" />
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[180px]">
                        <label class="text-xs font-semibold text-on-surface-variant">Tanggal Selesai</label>
                        <input v-model="dateTo" type="date" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" />
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">
                        <label class="text-xs font-semibold text-on-surface-variant">Status Lingkungan</label>
                        <select v-model="status" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Semua Status</option>
                            <option value="rendah">Rendah</option>
                            <option value="sedang">Sedang</option>
                            <option value="tinggi">Tinggi</option>
                            <option value="kritis">Kritis</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 mt-2 lg:mt-0">
                        <button @click="applyFilters" class="bg-primary hover:bg-surface-tint text-on-primary text-xs font-semibold px-6 py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors h-[42px]">
                            <span class="material-symbols-outlined text-[18px]">filter_list</span>
                            Terapkan Filter
                        </button>
                        <button @click="resetFilters" class="bg-surface-variant hover:bg-surface-dim text-on-surface-variant text-xs font-semibold px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors h-[42px] border border-outline-variant">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                            Reset
                        </button>
                    </div>
                </div>
            </section>

            <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-surface-container-low border-b border-outline-variant">
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Wilayah</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Sumber</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Judul Berita</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="article in articles.data" :key="article.id" class="odd:bg-surface-container-lowest even:bg-surface-bright hover:bg-surface-container cursor-pointer transition-colors" @click="openDrawer(article)">
                                <td class="px-6 py-4 text-sm text-on-surface">{{ formatDate(article.published_at) }}</td>
                                <td class="px-6 py-4 text-sm text-on-surface font-medium">{{ article.locations.map(l => l.regency_name || l.name).join(', ') }}</td>
                                <td class="px-6 py-4 text-sm text-on-surface">{{ article.categories.map(c => c.name).join(', ') }}</td>
                                <td class="px-6 py-4 text-sm text-on-surface flex items-center gap-2">
                                    <span class="material-symbols-outlined text-on-surface-variant text-[16px]">public</span>
                                    {{ article.source }}
                                </td>
                                <td class="px-6 py-4 text-sm text-on-surface font-medium max-w-[200px] truncate" :title="article.title">{{ article.title }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="article.cases?.length" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="getStatusBadge(article.cases[0].severity).class">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :style="{ background: getStatusBadge(article.cases[0].severity).text === 'Kritis' ? '#ba1a1a' : getStatusBadge(article.cases[0].severity).text === 'Tinggi' ? '#a96300' : getStatusBadge(article.cases[0].severity).text === 'Sedang' ? '#745b00' : '#436526' }"></span>
                                        {{ getStatusBadge(article.cases[0].severity).text }}
                                    </span>
                                    <span v-else class="text-on-surface-variant text-xs">—</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="material-symbols-outlined text-outline hover:text-primary transition-colors">chevron_right</span>
                                </td>
                            </tr>
                            <tr v-if="articles.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-4xl mb-2 block">search_off</span>
                                    <p>Tidak ada data yang ditemukan</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-outline-variant bg-surface-container-low px-6 py-3 flex items-center justify-between">
                    <span class="text-sm text-on-surface-variant">Menampilkan {{ articles.from || 0 }}-{{ articles.to || 0 }} dari {{ articles.total }} arsip</span>
                    <div class="flex gap-2">
                        <button v-if="articles.prev_page_url" @click="router.get(articles.prev_page_url)" class="p-1 rounded text-on-surface-variant hover:bg-surface-variant disabled:opacity-50">
                            <span class="material-symbols-outlined">chevron_left</span>
                        </button>
                        <button v-if="articles.next_page_url" @click="router.get(articles.next_page_url)" class="p-1 rounded text-on-surface-variant hover:bg-surface-variant">
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="drawerOpen" class="fixed inset-0 bg-on-surface/20 backdrop-blur-sm z-[60] transition-opacity" @click="closeDrawer"></div>
        <aside v-if="drawerOpen" class="fixed top-0 right-0 h-full w-full max-w-[400px] bg-surface-container-lowest shadow-[0_0_24px_rgba(0,0,0,0.1)] z-[70] transform transition-transform duration-300 ease-in-out flex flex-col border-l border-outline-variant">
            <div class="flex items-start justify-between p-6 border-b border-outline-variant bg-surface">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-primary text-sm">info</span>
                        <span class="text-xs text-primary tracking-wide uppercase font-semibold">Info Lebih Lanjut</span>
                    </div>
                    <h2 class="text-lg font-semibold text-on-surface">{{ selectedArticle?.title }}</h2>
                    <p class="text-sm text-on-surface-variant mt-1">{{ selectedArticle?.source }} • {{ formatDate(selectedArticle?.published_at) }}</p>
                </div>
                <button @click="closeDrawer" class="text-on-surface-variant hover:text-on-surface hover:bg-surface-variant rounded-full p-1 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 custom-scrollbar">
                <section v-if="selectedArticle?.content">
                    <h3 class="text-xs text-on-surface-variant mb-3 uppercase font-semibold">Konten Artikel</h3>
                    <p class="text-sm text-on-surface whitespace-pre-line leading-relaxed">{{ selectedArticle.content }}</p>
                </section>
                <section v-if="selectedArticle?.locations?.length">
                    <h3 class="text-xs text-on-surface-variant mb-3 uppercase font-semibold">Lokasi Terkait</h3>
                    <div class="bg-surface-container rounded-lg p-4 flex flex-col gap-3 border border-outline-variant">
                        <div v-for="loc in selectedArticle.locations" :key="loc.id" class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-outline">location_on</span>
                            <div class="flex flex-col">
                                <span class="text-sm text-on-surface font-medium">{{ loc.regency_name || loc.name }}</span>
                                <span class="text-xs text-on-surface-variant">{{ loc.province_name }}</span>
                            </div>
                        </div>
                    </div>
                </section>
                <section v-if="selectedArticle?.categories?.length">
                    <h3 class="text-xs text-on-surface-variant mb-3 uppercase font-semibold">Kategori</h3>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="cat in selectedArticle.categories" :key="cat.id" class="bg-surface-variant text-on-surface-variant px-3 py-1 rounded-full text-xs font-semibold">{{ cat.name }}</span>
                    </div>
                </section>
            </div>
            <div class="p-6 border-t border-outline-variant bg-surface-bright">
                <a :href="selectedArticle?.url" target="_blank" rel="noopener noreferrer" class="w-full bg-surface hover:bg-surface-container border border-outline text-on-surface text-xs font-semibold py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                    Lihat Sumber Asli
                </a>
            </div>
        </aside>
    </AppLayout>
</template>
