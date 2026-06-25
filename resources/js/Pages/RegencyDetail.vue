<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    regency: String,
    province: String,
    location: Object,
    urgencyScore: Object,
    stats: Object,
    categories: Array,
    cases: Array,
    pagination: Object,
    filters: Object,
    from: String,
});

// Filters
const search = ref(props.filters.search ?? '');
const selectedCategory = ref(props.filters.category ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const selectedSeverity = ref(props.filters.severity ?? '');

// Drawer
const drawerOpen = ref(false);
const selectedCase = ref(null);

function openDrawer(c) {
    selectedCase.value = c;
    drawerOpen.value = true;
}

function closeDrawer() {
    drawerOpen.value = false;
}

function applyFilters() {
    router.get('/regency', {
        regency: props.regency,
        province: props.province,
        search: search.value,
        category: selectedCategory.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        severity: selectedSeverity.value,
        page: 1,
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    search.value = '';
    selectedCategory.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    selectedSeverity.value = '';
    applyFilters();
}

function goToPage(page) {
    router.get('/regency', {
        regency: props.regency,
        province: props.province,
        search: search.value,
        category: selectedCategory.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        severity: selectedSeverity.value,
        page,
    }, { preserveState: true, preserveScroll: true });
}

function getSeverityBadge(severity) {
    if (severity >= 61) return { label: 'Kritis', cls: 'bg-error-container text-on-error-container', dot: 'bg-error' };
    if (severity >= 41) return { label: 'Tinggi', cls: 'bg-tertiary-container text-on-tertiary-container', dot: 'bg-tertiary' };
    if (severity >= 21) return { label: 'Sedang', cls: 'bg-secondary-container text-on-secondary-container', dot: 'bg-secondary' };
    return { label: 'Rendah', cls: 'bg-primary-fixed-dim text-on-primary-fixed-variant', dot: 'bg-primary' };
}

function getUrgencyStyle(level) {
    const map = {
        kritis: { cls: 'bg-error-container text-on-error-container', color: 'text-error' },
        tinggi: { cls: 'bg-tertiary-container text-on-tertiary-container', color: 'text-tertiary' },
        sedang: { cls: 'bg-secondary-container text-on-secondary-container', color: 'text-secondary' },
        rendah: { cls: 'bg-primary-fixed-dim text-on-primary-fixed-variant', color: 'text-primary' },
    };
    return map[level?.toLowerCase()] ?? map.rendah;
}

const urgencyStyle = computed(() => getUrgencyStyle(props.urgencyScore?.level));
const urgencyLabel = computed(() => {
    const l = props.urgencyScore?.level;
    if (!l) return 'Tidak Diketahui';
    return l.charAt(0).toUpperCase() + l.slice(1);
});

const drawerSeverityBadge = computed(() => selectedCase.value ? getSeverityBadge(selectedCase.value.severity) : null);
</script>

<template>
    <AppLayout :title="`${regency} - Nalabumi`">
        <div class="py-8 px-6 md:px-10 max-w-[1440px] mx-auto flex flex-col gap-8">

            <!-- Back + Header -->
            <div class="flex flex-col gap-4">
                <Link :href="from === 'analytics' ? '/analytics' : '/dashboard'" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors text-xs font-semibold uppercase tracking-wider w-fit">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Kembali ke {{ from === 'analytics' ? 'Analitik' : 'Peta Nasional' }}
                </Link>

                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">location_on</span>
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ province ? `Provinsi ${province}` : '—' }}</span>
                    </div>
                    <h1 class="text-4xl font-bold text-on-surface">{{ regency || '—' }}</h1>
                    <p class="text-base text-on-surface-variant max-w-2xl mt-2">
                        Detail laporan dan deteksi kerusakan lingkungan berdasarkan data artikel dan analisis AI pada area ini.
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Status Laporan -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status Laporan Lingkungan</span>
                        <span class="material-symbols-outlined text-on-surface-variant">monitoring</span>
                    </div>
                    <div class="grid grid-cols-4 gap-3 mt-auto">
                        <div class="flex flex-col p-4 rounded-xl bg-error/5 border border-error/10 relative overflow-hidden group hover:bg-error/10 transition-colors cursor-default">
                            <div class="absolute top-0 right-0 w-10 h-10 bg-error/10 rounded-bl-full"></div>
                            <span class="text-xs text-error/80 mb-1 font-medium relative z-10">Kritis</span>
                            <span class="text-2xl font-bold text-error relative z-10 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-error"></span>{{ stats.critical }}
                            </span>
                        </div>
                        <div class="flex flex-col p-4 rounded-xl bg-tertiary-container/20 border border-tertiary-container/30 relative overflow-hidden group hover:bg-tertiary-container/40 transition-colors cursor-default">
                            <div class="absolute top-0 right-0 w-10 h-10 bg-tertiary-container/40 rounded-bl-full"></div>
                            <span class="text-xs text-tertiary mb-1 font-medium relative z-10">Tinggi</span>
                            <span class="text-2xl font-bold text-tertiary relative z-10 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-tertiary"></span>{{ stats.high }}
                            </span>
                        </div>
                        <div class="flex flex-col p-4 rounded-xl bg-secondary-container/30 border border-secondary-container/50 relative overflow-hidden group hover:bg-secondary-container/50 transition-colors cursor-default">
                            <div class="absolute top-0 right-0 w-10 h-10 bg-secondary-container/50 rounded-bl-full"></div>
                            <span class="text-xs text-secondary mb-1 font-medium relative z-10">Sedang</span>
                            <span class="text-2xl font-bold text-secondary relative z-10 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-secondary-container"></span>{{ stats.medium }}
                            </span>
                        </div>
                        <div class="flex flex-col p-4 rounded-xl bg-primary/5 border border-primary/10 relative overflow-hidden group hover:bg-primary/10 transition-colors cursor-default">
                            <div class="absolute top-0 right-0 w-10 h-10 bg-primary/10 rounded-bl-full"></div>
                            <span class="text-xs text-primary/80 mb-1 font-medium relative z-10">Rendah</span>
                            <span class="text-2xl font-bold text-primary relative z-10 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-primary"></span>{{ stats.low }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Urgency Score & Info -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-xs font-semibold text-primary uppercase tracking-wider">Skor Urgensi Lingkungan</span>
                        <span class="material-symbols-outlined text-primary">eco</span>
                    </div>
                    <template v-if="urgencyScore">
                        <div class="flex items-end gap-4 mb-4">
                            <span class="text-5xl font-bold text-on-surface">{{ urgencyScore.score }}</span>
                            <span class="text-on-surface-variant text-sm mb-1">/100</span>
                            <span class="ml-2 px-3 py-1 rounded-full text-xs font-bold uppercase" :class="urgencyStyle.cls">
                                {{ urgencyLabel }}
                            </span>
                        </div>
                        <div class="w-full bg-surface-container rounded-full h-2 mb-4">
                            <div class="h-2 rounded-full transition-all duration-700"
                                :class="{
                                    'bg-error': urgencyScore.score >= 61,
                                    'bg-tertiary': urgencyScore.score >= 41 && urgencyScore.score < 61,
                                    'bg-secondary': urgencyScore.score >= 21 && urgencyScore.score < 41,
                                    'bg-primary': urgencyScore.score < 21,
                                }"
                                :style="`width: ${urgencyScore.score}%`"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mt-auto">
                            <div class="bg-surface-container rounded-lg p-3 text-center">
                                <span class="block text-lg font-bold text-on-surface">{{ urgencyScore.case_count }}</span>
                                <span class="text-xs text-on-surface-variant uppercase block">Total Kasus</span>
                            </div>
                            <div class="bg-surface-container rounded-lg p-3 text-center">
                                <span class="block text-lg font-bold text-on-surface">{{ urgencyScore.avg_severity ?? '—' }}</span>
                                <span class="text-xs text-on-surface-variant uppercase block">Rata-rata Keparahan</span>
                            </div>
                            <div class="bg-surface-container rounded-lg p-3 text-center">
                                <span class="block text-sm font-bold text-on-surface">{{ urgencyScore.date ? new Date(urgencyScore.date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—' }}</span>
                                <span class="text-xs text-on-surface-variant uppercase block">Diperbarui</span>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex flex-col items-center justify-center flex-1 text-center py-4 gap-2">
                            <span class="material-symbols-outlined text-outline text-[40px]">query_stats</span>
                            <p class="text-sm text-on-surface-variant">Belum ada skor urgensi untuk wilayah ini.</p>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Search & Filters -->
            <section class="flex flex-col gap-4">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        class="w-full pl-10 pr-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-sm text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-sm"
                        placeholder="Cari berdasarkan judul berita, kategori, atau sumber..."
                        type="text"
                    />
                </div>

                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-wrap gap-4 items-end shadow-sm">
                    <!-- Category -->
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[180px]">
                        <label class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori</label>
                        <select v-model="selectedCategory" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
                        </select>
                    </div>
                    <!-- Date From -->
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[160px]">
                        <label class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Rentang Waktu (Mulai)</label>
                        <input v-model="dateFrom" type="date" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" />
                    </div>
                    <!-- Date To -->
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[160px]">
                        <label class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Rentang Waktu (Selesai)</label>
                        <input v-model="dateTo" type="date" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" />
                    </div>
                    <!-- Severity -->
                    <div class="flex flex-col gap-1.5 flex-1 min-w-[160px]">
                        <label class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status Keparahan</label>
                        <select v-model="selectedSeverity" class="w-full bg-surface-container border border-outline-variant rounded-lg px-4 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Semua Status</option>
                            <option value="rendah">Rendah</option>
                            <option value="sedang">Sedang</option>
                            <option value="tinggi">Tinggi</option>
                            <option value="kritis">Kritis</option>
                        </select>
                    </div>
                    <!-- Buttons -->
                    <div class="flex items-center gap-2 mt-2 lg:mt-0">
                        <button @click="applyFilters" class="bg-primary hover:opacity-90 text-on-primary text-xs font-semibold px-5 py-2.5 rounded-lg flex items-center gap-2 transition-colors h-[42px]">
                            <span class="material-symbols-outlined text-[18px]">filter_list</span>
                            Terapkan
                        </button>
                        <button @click="resetFilters" class="bg-surface-variant hover:bg-surface-dim text-on-surface-variant text-xs font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors h-[42px] border border-outline-variant">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                            Reset
                        </button>
                    </div>
                </div>
            </section>

            <!-- Data Table -->
            <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <!-- Table header info -->
                <div class="p-4 border-b border-outline-variant bg-surface-container-lowest flex items-center justify-between flex-wrap gap-2">
                    <span class="text-xs text-error italic flex flex-col gap-0.5">
                        <span class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">info</span>
                            Hasil prediksi AI dapat keliru. Gunakan sebagai acuan awal investigasi.
                        </span>
                        <span class="text-[10px] text-on-surface-variant ml-5.5">This severity score is evaluated by AI.</span>
                    </span>
                    <span class="text-xs text-on-surface-variant font-medium">
                        {{ pagination.total }} laporan ditemukan
                    </span>
                </div>

                <!-- Empty state -->
                <div v-if="cases.length === 0" class="flex flex-col items-center justify-center py-16 gap-3">
                    <span class="material-symbols-outlined text-outline text-[48px]">search_off</span>
                    <p class="text-on-surface-variant text-sm">Tidak ada laporan ditemukan untuk wilayah atau filter ini.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-surface-container-low border-b border-outline-variant">
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Sumber</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Judul Berita</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                                <th class="px-6 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Skor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="(c, i) in cases"
                                :key="c.id"
                                class="group hover:bg-surface-container cursor-pointer transition-colors"
                                :class="i % 2 === 0 ? 'bg-surface-container-lowest' : 'bg-surface-bright'"
                                @click="openDrawer(c)"
                            >
                                <td class="px-6 py-4 text-sm text-on-surface">{{ c.date }}</td>
                                <td class="px-6 py-4 text-sm text-on-surface">{{ c.category }}</td>
                                <td class="px-6 py-4 text-sm text-on-surface flex items-center gap-2">
                                    <span class="material-symbols-outlined text-on-surface-variant text-[16px]">public</span>
                                    {{ c.source }}
                                </td>
                                <td class="px-6 py-4 text-sm text-on-surface font-medium truncate max-w-[240px]">
                                    <a v-if="c.url" :href="c.url" target="_blank" class="hover:text-primary hover:underline" @click.stop>
                                        {{ c.title }}
                                    </a>
                                    <span v-else>{{ c.title }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="getSeverityBadge(c.severity).cls">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="getSeverityBadge(c.severity).dot"></span>
                                        {{ getSeverityBadge(c.severity).label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-bold text-sm" :class="{
                                        'text-error': c.severity >= 61,
                                        'text-tertiary': c.severity >= 41 && c.severity < 61,
                                        'text-secondary': c.severity >= 21 && c.severity < 41,
                                        'text-primary': c.severity < 21,
                                    }">{{ c.severity }}/100</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="border-t border-outline-variant bg-surface-container-low px-6 py-3 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-sm text-on-surface-variant">
                        Menampilkan {{ ((pagination.currentPage - 1) * pagination.perPage) + 1 }}–{{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }} dari {{ pagination.total }} laporan
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            @click="goToPage(pagination.currentPage - 1)"
                            :disabled="pagination.currentPage <= 1"
                            class="p-1 rounded text-on-surface-variant hover:bg-surface-variant disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <span class="material-symbols-outlined">chevron_left</span>
                        </button>
                        <span class="text-xs text-on-surface-variant px-2">{{ pagination.currentPage }} / {{ pagination.lastPage }}</span>
                        <button
                            @click="goToPage(pagination.currentPage + 1)"
                            :disabled="pagination.currentPage >= pagination.lastPage"
                            class="p-1 rounded text-on-surface-variant hover:bg-surface-variant disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <!-- ═══════════════════════════════════ -->
        <!-- DRAWER OVERLAY + PANEL              -->
        <!-- ═══════════════════════════════════ -->
        <Teleport to="body">
            <!-- Overlay -->
            <Transition name="fade">
                <div
                    v-if="drawerOpen"
                    class="fixed inset-0 bg-on-surface/20 backdrop-blur-sm z-[60]"
                    @click="closeDrawer"
                ></div>
            </Transition>

            <!-- Slide-over Drawer -->
            <Transition name="slide-right">
                <aside
                    v-if="drawerOpen && selectedCase"
                    class="fixed top-0 right-0 h-full w-full max-w-[420px] bg-surface-container-lowest shadow-[0_0_32px_rgba(0,0,0,0.12)] z-[70] flex flex-col border-l border-outline-variant"
                >
                    <!-- Drawer Header -->
                    <div class="flex items-start justify-between p-6 border-b border-outline-variant bg-surface">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="material-symbols-outlined text-primary text-sm">auto_awesome</span>
                                <span class="text-xs text-primary tracking-wide uppercase font-semibold">Detail Laporan</span>
                            </div>
                            <h2 class="text-lg font-semibold text-on-surface leading-snug line-clamp-2">{{ selectedCase.title }}</h2>
                            <p class="text-sm text-on-surface-variant mt-1">{{ selectedCase.source }} · {{ selectedCase.date }}</p>
                        </div>
                        <button @click="closeDrawer" class="text-on-surface-variant hover:text-on-surface hover:bg-surface-variant rounded-full p-1 transition-colors flex-shrink-0 ml-3">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Drawer Scrollable Content -->
                    <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">

                        <!-- Status & Skor -->
                        <section :class="[
                            'p-4 rounded-lg border',
                            selectedCase.severity >= 61 ? 'bg-error-container/30 border-error/20' :
                            selectedCase.severity >= 41 ? 'bg-tertiary-container/20 border-tertiary-container/30' :
                            selectedCase.severity >= 21 ? 'bg-secondary-container/20 border-secondary-container/30' :
                            'bg-primary/5 border-primary/10'
                        ]">
                            <h3 class="text-xs font-semibold uppercase flex items-center gap-2 mb-3"
                                :class="{
                                    'text-error': selectedCase.severity >= 61,
                                    'text-tertiary': selectedCase.severity >= 41 && selectedCase.severity < 61,
                                    'text-secondary': selectedCase.severity >= 21 && selectedCase.severity < 41,
                                    'text-primary': selectedCase.severity < 21,
                                }">
                                <span class="material-symbols-outlined text-[18px]">online_prediction</span>
                                Status & Skor Keparahan
                            </h3>
                            <div class="space-y-2.5">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-on-surface-variant">Kategori:</span>
                                    <span class="text-sm font-semibold text-on-surface">{{ selectedCase.category }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-on-surface-variant">Skor Keparahan:</span>
                                    <span class="text-sm font-bold px-2 py-0.5 rounded"
                                        :class="{
                                            'bg-error text-on-error': selectedCase.severity >= 61,
                                            'bg-tertiary-container text-on-tertiary-container': selectedCase.severity >= 41 && selectedCase.severity < 61,
                                            'bg-secondary-container text-on-secondary-container': selectedCase.severity >= 21 && selectedCase.severity < 41,
                                            'bg-primary-fixed-dim text-on-primary-fixed-variant': selectedCase.severity < 21,
                                        }">
                                        {{ selectedCase.severity }}/100
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-on-surface-variant">Level:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="drawerSeverityBadge.cls">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="drawerSeverityBadge.dot"></span>
                                        {{ drawerSeverityBadge.label }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-on-surface-variant italic text-center mt-2 border-t border-outline-variant/30 pt-1.5">
                                    This severity score is evaluated by AI.
                                </div>
                            </div>
                        </section>

                        <!-- Informasi Artikel -->
                        <section>
                            <h3 class="text-xs font-semibold text-on-surface-variant uppercase mb-3">Informasi Artikel</h3>
                            <ul class="space-y-3 text-sm text-on-surface">
                                <li class="flex gap-3 items-start">
                                    <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">newspaper</span>
                                    <div>
                                        <p class="font-medium">{{ selectedCase.title }}</p>
                                        <p class="text-xs text-on-surface-variant mt-0.5">{{ selectedCase.source }}</p>
                                    </div>
                                </li>
                                <li class="flex gap-3 items-start">
                                    <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">schedule</span>
                                    <span>{{ selectedCase.date }}</span>
                                </li>
                                <li v-if="selectedCase.cause" class="flex gap-3 items-start">
                                    <span class="material-symbols-outlined text-error text-[18px] shrink-0 mt-0.5">warning</span>
                                    <div>
                                        <p class="text-xs text-on-surface-variant font-semibold uppercase mb-1">Penyebab</p>
                                        <p>{{ selectedCase.cause }}</p>
                                    </div>
                                </li>
                                <li v-if="selectedCase.impact" class="flex gap-3 items-start">
                                    <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">insights</span>
                                    <div>
                                        <p class="text-xs text-on-surface-variant font-semibold uppercase mb-1">Dampak</p>
                                        <p>{{ selectedCase.impact }}</p>
                                    </div>
                                </li>
                            </ul>
                        </section>

                        <hr class="border-outline-variant" />

                        <!-- Lokasi -->
                        <section>
                            <h3 class="text-xs font-semibold text-on-surface-variant uppercase mb-3">Lokasi Terdampak</h3>
                            <div class="bg-surface-container rounded-lg p-4 flex flex-col gap-3 border border-outline-variant">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-outline">location_on</span>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-on-surface">{{ selectedCase.location_name }}</span>
                                        <span class="text-xs text-on-surface-variant">{{ selectedCase.province_name }}</span>
                                    </div>
                                </div>
                                <template v-if="location?.latitude && location?.longitude">
                                    <div class="flex gap-2 mt-1">
                                        <span class="bg-surface-variant text-on-surface-variant px-2 py-1 rounded text-[10px] font-semibold">Lat: {{ location.latitude }}</span>
                                        <span class="bg-surface-variant text-on-surface-variant px-2 py-1 rounded text-[10px] font-semibold">Long: {{ location.longitude }}</span>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </div>

                    <!-- Drawer Footer -->
                    <div class="p-6 border-t border-outline-variant bg-surface-bright flex flex-col gap-2">
                        <a v-if="selectedCase.url" :href="selectedCase.url" target="_blank"
                            class="w-full bg-primary hover:opacity-90 text-on-primary text-xs font-semibold py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            Buka Artikel Sumber
                        </a>
                        <button @click="closeDrawer"
                            class="w-full bg-surface-variant hover:bg-surface-dim text-on-surface-variant text-xs font-semibold py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors border border-outline-variant">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                            Tutup
                        </button>
                    </div>
                </aside>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.3s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>
