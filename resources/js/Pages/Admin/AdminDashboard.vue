<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    articles: Object,
    stats: Object,
    categories: Array,
    filters: Object,
    isBingLocked: Boolean,
});

const search = ref(props.filters?.search || '');
const activeFilter = ref(props.filters?.filter || 'all');
const showAddModal = ref(false);
const showSyncModal = ref(false);
const mobileMenuOpen = ref(false);

const addForm = useForm({
    title: '',
    url: '',
    source: '',
    content: '',
    published_at: '',
});

const applyFilter = (filter) => {
    activeFilter.value = filter;
    router.get('/admin', {
        filter: filter,
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const applySearch = () => {
    router.get('/admin', {
        filter: activeFilter.value,
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const verifyArticle = (id) => {
    router.patch(`/admin/articles/${id}/verify`, {}, {
        preserveScroll: true,
    });
};

const rejectArticle = (id) => {
    if (confirm('Apakah Anda yakin ingin menolak artikel ini?')) {
        router.patch(`/admin/articles/${id}/reject`, {}, {
            preserveScroll: true,
        });
    }
};

const submitArticle = () => {
    addForm.post('/admin/articles', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const logout = () => {
    router.post('/admin/logout');
};

const confirmSyncUnanalyzed = () => {
    showSyncModal.value = true;
};

const executeSync = () => {
    showSyncModal.value = false;
    router.post('/admin/articles/sync-unanalyzed', {}, {
        preserveScroll: true,
    });
};

const fetchHistorical = () => {
    if (props.isBingLocked) return;
    
    if (confirm('PERINGATAN: Fitur ini akan memaksa server menembakkan 25 pencarian berat ke mesin Bing dan mendatangkan puluhan berita historis secara bersamaan. Jika Anda melakukannya terlalu sering, IP kita bisa saja diblokir oleh Bing.\n\nApakah Anda yakin ingin menggunakan fitur darurat ini sekarang?')) {
        router.post(route('admin.articles.fetch-historical'), {}, { preserveScroll: true });
    }
};

const runScrape = () => {
    if (confirm('Scrape semua artikel yang belum di-scrape sekarang? Semua artikel tanpa konten akan diproses.')) {
        router.post(route('admin.scrape.run'), {}, {
            preserveScroll: true,
            onSuccess: () => loadQueue(),
        });
    }
};

const runAnalysis = () => {
    if (confirm('Analisis ulang semua artikel yang sudah di-scrape tapi belum dianalisis? Periksa data yang ada sekarang.')) {
        router.post(route('admin.articles.sync-unanalyzed'), {}, {
            preserveScroll: true,
            onSuccess: () => loadQueue(),
        });
    }
};

const showQueuePanel = ref(false);
const queueData = ref(null);
const queueLoading = ref(false);
const queueFilter = ref('all');

const loadQueue = (type) => {
    queueLoading.value = true;
    const filterType = type || queueFilter.value;
    fetch(route('admin.scrape.queue') + `?type=${filterType}`)
        .then(res => res.json())
        .then(data => {
            queueData.value = data;
            queueLoading.value = false;
        })
        .catch(() => {
            queueLoading.value = false;
        });
};

const toggleQueue = () => {
    showQueuePanel.value = !showQueuePanel.value;
    if (showQueuePanel.value && !queueData.value) {
        loadQueue();
    }
};

const setQueueFilter = (type) => {
    queueFilter.value = type;
    loadQueue(type);
};

const severityLabel = (severity) => {
    if (severity === null || severity === undefined) return { text: '—', class: 'bg-surface-container text-on-surface-variant' };
    if (severity >= 61) return { text: 'Kritis', class: 'bg-error-container text-on-error-container' };
    if (severity >= 41) return { text: 'Tinggi', class: 'bg-tertiary-container text-on-tertiary-container' };
    if (severity >= 21) return { text: 'Sedang', class: 'bg-secondary-container text-on-secondary-container' };
    return { text: 'Rendah', class: 'bg-primary-container text-on-primary-container' };
};

const verificationBadge = (isVerified) => {
    if (isVerified === null || isVerified === undefined) return { text: 'Pending', icon: 'pending', class: 'bg-secondary-container text-on-secondary-container' };
    if (isVerified === true) return { text: 'Terverifikasi', icon: 'check_circle', class: 'bg-primary-container text-on-primary-container' };
    return { text: 'Ditolak', icon: 'cancel', class: 'bg-error-container text-on-error-container' };
};

const filterTabs = [
    { key: 'all', label: 'Semua', icon: 'list' },
    { key: 'pending', label: 'Pending', icon: 'pending' },
    { key: 'verified', label: 'Terverifikasi', icon: 'check_circle' },
    { key: 'rejected', label: 'Ditolak', icon: 'cancel' },
];
</script>

<template>
    <Head title="Admin Dashboard — Nalabumi" />
    <div class="min-h-screen bg-surface">
        <!-- Top Bar -->
        <nav class="fixed top-0 w-full z-50 flex justify-between items-center px-6 md:px-10 h-16 bg-surface border-b border-outline-variant shadow-sm">
            <div class="flex items-center gap-4">
                <Link href="/" class="flex items-center">
                    <img :src="'/logo.svg'" alt="Nalabumi Logo" class="h-10 w-auto object-contain" />
                </Link>
                <div class="h-6 w-px bg-outline-variant hidden md:block"></div>
                <span class="text-sm font-bold text-primary uppercase tracking-wider hidden md:block">Admin Panel</span>

                <div class="hidden md:flex ml-4 gap-6 items-center">
                    <Link href="/admin" class="text-primary border-b-2 border-primary py-5 text-sm font-medium transition-colors">Dasbor Utama</Link>
                    <Link href="/admin/media-reputations" class="text-on-surface-variant hover:text-primary text-sm font-medium transition-colors">Reputasi Media</Link>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Link href="/dashboard" class="hidden md:flex text-xs text-on-surface-variant hover:text-primary font-medium transition-colors items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    Lihat Situs
                </Link>
                <button @click="logout" class="hidden md:flex px-4 py-2 bg-error-container text-on-error-container rounded-lg font-semibold text-xs hover:bg-error hover:text-on-error transition-colors items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    Keluar
                </button>
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-on-surface-variant hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
                </button>
            </div>
        </nav>

        <!-- Mobile Menu Dropdown -->
        <div v-if="mobileMenuOpen" class="md:hidden flex flex-col bg-surface border-b border-outline-variant shadow-md fixed top-16 left-0 w-full z-40">
            <Link href="/admin" @click="mobileMenuOpen = false" class="px-6 py-4 border-b border-outline-variant/50 text-sm font-semibold flex items-center gap-3 text-on-surface">
                <span class="material-symbols-outlined">dashboard</span>
                Dasbor Utama
            </Link>
            <Link href="/admin/media-reputations" @click="mobileMenuOpen = false" class="px-6 py-4 border-b border-outline-variant/50 text-sm font-semibold flex items-center gap-3 text-on-surface">
                <span class="material-symbols-outlined">verified</span>
                Reputasi Media
            </Link>
            <Link href="/dashboard" @click="mobileMenuOpen = false" class="px-6 py-4 border-b border-outline-variant/50 text-sm font-semibold flex items-center gap-3 text-on-surface">
                <span class="material-symbols-outlined">open_in_new</span>
                Lihat Situs Publik
            </Link>
            <button @click="logout" class="px-6 py-4 text-sm font-semibold text-error flex items-center gap-3 text-left w-full hover:bg-error/10">
                <span class="material-symbols-outlined">logout</span>
                Keluar
            </button>
        </div>

        <main class="pt-16 px-6 md:px-10 pb-10">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <div class="glass-panel rounded-xl p-4">
                    <div class="flex items-center gap-2 text-on-surface-variant mb-1">
                        <span class="material-symbols-outlined text-[18px]">article</span>
                        <span class="text-xs font-semibold uppercase tracking-wider">Total Artikel</span>
                    </div>
                    <p class="text-2xl font-bold text-on-surface">{{ stats.total }}</p>
                </div>
                <div class="glass-panel rounded-xl p-4">
                    <div class="flex items-center gap-2 text-secondary mb-1">
                        <span class="material-symbols-outlined text-[18px]">pending</span>
                        <span class="text-xs font-semibold uppercase tracking-wider">Pending Review</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary">{{ stats.pending }}</p>
                </div>
                <div class="glass-panel rounded-xl p-4">
                    <div class="flex items-center gap-2 text-primary mb-1">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        <span class="text-xs font-semibold uppercase tracking-wider">Terverifikasi</span>
                    </div>
                    <p class="text-2xl font-bold text-primary">{{ stats.verified }}</p>
                </div>
                <div class="glass-panel rounded-xl p-4">
                    <div class="flex items-center gap-2 text-error mb-1">
                        <span class="material-symbols-outlined text-[18px]">cancel</span>
                        <span class="text-xs font-semibold uppercase tracking-wider">Ditolak</span>
                    </div>
                    <p class="text-2xl font-bold text-error">{{ stats.rejected }}</p>
                </div>
            </div>

            <!-- Secondary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-4">
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">cases</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.totalCases }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Total Kasus</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">location_on</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.totalLocations }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Total Wilayah</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary text-[20px]">cloud_download</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.unscraped }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Belum Scrape</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-error text-[20px]">error_outline</span>
                    <div>
                        <p class="text-sm font-bold text-error">{{ stats.scrape_failed }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Gagal Scrape</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-tertiary text-[20px]">psychology</span>
                        <div>
                            <p class="text-sm font-bold text-on-surface">{{ stats.unanalyzed }}</p>
                            <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Belum Analisis</p>
                        </div>
                    </div>
                    <button v-if="stats.unanalyzed > 0" @click="confirmSyncUnanalyzed" class="p-1.5 rounded-lg bg-tertiary/10 text-tertiary hover:bg-tertiary hover:text-on-tertiary transition-colors" title="Analisis Ulang (Sync)">
                        <span class="material-symbols-outlined text-[16px]">sync</span>
                    </button>
                </div>
            </div>

            <!-- Article Management Section -->
            <div class="glass-panel rounded-xl mt-6 overflow-hidden">
                <!-- Toolbar -->
                <div class="p-4 border-b border-outline-variant flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            v-for="tab in filterTabs"
                            :key="tab.key"
                            @click="applyFilter(tab.key)"
                            :class="[
                                activeFilter === tab.key
                                    ? 'bg-primary text-on-primary'
                                    : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5'
                            ]"
                        >
                            <span class="material-symbols-outlined text-[14px]">{{ tab.icon }}</span>
                            {{ tab.label }}
                        </button>
                        <div class="h-5 w-px bg-outline-variant mx-1"></div>
                        <button
                            @click="runScrape"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-tertiary-container text-on-tertiary-container hover:bg-tertiary hover:text-on-tertiary"
                        >
                            <span class="material-symbols-outlined text-[14px]">rss_feed</span>
                            Run Scrape
                        </button>
                        <button
                            @click="runAnalysis"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-secondary-container text-on-secondary-container hover:bg-secondary hover:text-on-secondary"
                        >
                            <span class="material-symbols-outlined text-[14px]">psychology</span>
                            Run Analysis
                        </button>
                        <button
                            @click="toggleQueue"
                            :class="[
                                showQueuePanel
                                    ? 'bg-primary text-on-primary'
                                    : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5'
                            ]"
                        >
                            <span class="material-symbols-outlined text-[14px]">queue</span>
                            Antrean
                            <span v-if="queueData && (queueData.pending_scrape > 0 || queueData.pending_analysis > 0)" class="px-1.5 py-0.5 rounded-full bg-error text-on-error text-[10px] font-bold">{{ queueData.pending_scrape + queueData.pending_analysis }}</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                            <input
                                v-model="search"
                                @keyup.enter="applySearch"
                                type="text"
                                placeholder="Cari artikel..."
                                class="pl-9 pr-3 py-1.5 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary w-52"
                            />
                        </div>
                        <button 
                            @click="fetchHistorical" 
                            :disabled="isBingLocked"
                            class="px-3 py-1.5 bg-secondary text-on-secondary rounded-lg text-xs font-semibold hover:bg-secondary-container hover:text-on-secondary-container transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="material-symbols-outlined text-[14px]">history</span>
                            {{ isBingLocked ? 'Cooldown 12 Jam' : 'Tarik 15 Hari' }}
                        </button>
                        <button
                            @click="showAddModal = true"
                            class="px-3 py-1.5 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:bg-primary-container hover:text-on-primary-container transition-colors flex items-center gap-1.5"
                        >
                            <span class="material-symbols-outlined text-[14px]">add</span>
                            Tambah Artikel
                        </button>
                    </div>
                </div>

                <!-- Articles Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container text-on-surface-variant uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Artikel</th>
                                <th class="px-4 py-3 font-semibold">Sumber</th>
                                <th class="px-4 py-3 font-semibold">Wilayah</th>
                                <th class="px-4 py-3 font-semibold">Severity</th>
                                <th class="px-4 py-3 font-semibold">Confidence</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="article in articles.data" :key="article.id" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 max-w-xs">
                                    <a :href="article.url" target="_blank" class="text-on-surface hover:text-primary font-medium line-clamp-2 transition-colors">
                                        {{ article.title }}
                                    </a>
                                    <p class="text-[10px] text-on-surface-variant mt-0.5">{{ article.published_at || '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">{{ article.source }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="loc in article.locations"
                                            :key="loc"
                                            class="px-1.5 py-0.5 bg-surface-container rounded text-[10px] text-on-surface-variant"
                                        >{{ loc }}</span>
                                        <span v-if="!article.locations?.length" class="text-on-surface-variant">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[severityLabel(article.severity).class, 'px-2 py-0.5 rounded-full text-[10px] font-semibold']"
                                    >{{ article.severity !== null ? `${severityLabel(article.severity).text} (${article.severity})` : '—' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="article.confidence !== null" class="text-on-surface-variant">
                                        {{ article.confidence }}%
                                    </span>
                                    <span v-else class="text-on-surface-variant">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[verificationBadge(article.is_verified).class, 'px-2 py-0.5 rounded-full text-[10px] font-semibold inline-flex items-center gap-1']"
                                    >
                                        <span class="material-symbols-outlined text-[12px]">{{ verificationBadge(article.is_verified).icon }}</span>
                                        {{ verificationBadge(article.is_verified).text }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        v-if="article.is_verified !== true"
                                        @click="verifyArticle(article.id)"
                                        class="p-1.5 rounded-lg bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary transition-colors"
                                        title="Verifikasi"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                    </button>
                                    <button
                                        v-if="article.is_verified !== false"
                                        @click="rejectArticle(article.id)"
                                        class="p-1.5 rounded-lg bg-error-container text-on-error-container hover:bg-error hover:text-on-error transition-colors ml-1"
                                        title="Tolak"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!articles.data?.length">
                                <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[32px] block mb-2">inbox</span>
                                    Tidak ada artikel ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="articles.last_page > 1" class="p-4 border-t border-outline-variant flex items-center justify-between">
                    <p class="text-xs text-on-surface-variant">
                        Menampilkan {{ articles.from }}–{{ articles.to }} dari {{ articles.total }} artikel
                    </p>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="link in articles.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                link.active ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                !link.url ? 'opacity-40 pointer-events-none' : '',
                                'px-3 py-1 rounded-lg text-xs font-semibold transition-colors'
                            ]"
                            v-html="link.label.replace('pagination.previous', '&laquo; Prev').replace('pagination.next', 'Next &raquo;')"
                            preserve-scroll
                        />
                    </div>
                </div>
            </div>
        </main>

        <!-- Queue Panel -->
        <Teleport to="body">
            <div v-if="showQueuePanel" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showQueuePanel = false"></div>
                <div class="relative glass-panel rounded-2xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">queue</span>
                            Antrean
                        </h2>
                        <div class="flex items-center gap-2">
                            <button @click="loadQueue" :disabled="queueLoading" class="p-1.5 rounded-lg hover:bg-surface-container transition-colors" title="Refresh">
                                <span :class="['material-symbols-outlined text-on-surface-variant', queueLoading ? 'animate-spin' : '']">refresh</span>
                            </button>
                            <button @click="showQueuePanel = false" class="p-1 rounded-lg hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-on-surface-variant">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Filter tabs -->
                    <div class="flex items-center gap-2 mb-4">
                        <button
                            v-for="tab in [{ key: 'all', label: 'Semua' }, { key: 'scrape', label: 'Scrape' }, { key: 'analysis', label: 'Analysis' }]"
                            :key="tab.key"
                            @click="setQueueFilter(tab.key)"
                            :class="[
                                queueFilter === tab.key
                                    ? 'bg-primary text-on-primary'
                                    : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5'
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div v-if="queueLoading" class="flex items-center justify-center py-16">
                        <span class="material-symbols-outlined animate-spin text-primary text-[36px]">progress_activity</span>
                    </div>

                    <template v-else-if="queueData">
                        <!-- Queue Stats -->
                        <div class="grid grid-cols-4 gap-3 mb-5">
                            <div class="bg-surface-container rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-on-surface">{{ queueData.pending }}</p>
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider mt-0.5">Total</p>
                            </div>
                            <div class="bg-surface-container rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-tertiary">{{ queueData.pending_scrape }}</p>
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider mt-0.5">Scrape</p>
                            </div>
                            <div class="bg-surface-container rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-secondary">{{ queueData.pending_analysis }}</p>
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider mt-0.5">Analysis</p>
                            </div>
                            <div class="bg-surface-container rounded-xl p-3 text-center">
                                <p :class="['text-2xl font-bold', queueData.failed > 0 ? 'text-error' : 'text-primary']">{{ queueData.failed }}</p>
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider mt-0.5">Gagal</p>
                            </div>
                        </div>

                        <!-- Recent Jobs Table -->
                        <div class="mb-5">
                            <h3 class="text-sm font-bold text-on-surface mb-2 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-primary">pending_actions</span>
                                Job dalam Antrean
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-surface-container text-on-surface-variant uppercase tracking-wider">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Job</th>
                                            <th class="px-3 py-2 font-semibold">Tipe</th>
                                            <th class="px-3 py-2 font-semibold">Attempts</th>
                                            <th class="px-3 py-2 font-semibold">Tersedia</th>
                                            <th class="px-3 py-2 font-semibold">Dibuat</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant">
                                        <tr v-for="job in queueData.recentJobs" :key="job.id" class="hover:bg-surface-container-low transition-colors">
                                            <td class="px-3 py-2 text-on-surface font-medium">{{ job.display_name }}</td>
                                            <td class="px-3 py-2">
                                                <span :class="[job.category === 'scrape' ? 'bg-tertiary-container text-on-tertiary-container' : 'bg-secondary-container text-on-secondary-container', 'px-1.5 py-0.5 rounded text-[10px] font-semibold']">{{ job.category === 'scrape' ? 'Scrape' : 'Analysis' }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span :class="[job.attempts > 1 ? 'text-error' : 'text-on-surface-variant', 'font-semibold']">{{ job.attempts }}/3</span>
                                            </td>
                                            <td class="px-3 py-2 text-on-surface-variant">{{ job.available_at }}</td>
                                            <td class="px-3 py-2 text-on-surface-variant">{{ job.created_at }}</td>
                                        </tr>
                                        <tr v-if="!queueData.recentJobs?.length">
                                            <td colspan="5" class="px-3 py-8 text-center text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[28px] block mb-1">check_circle</span>
                                                Tidak ada job dalam antrean.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Failed Jobs -->
                        <div v-if="queueData.failedJobs?.length">
                            <h3 class="text-sm font-bold text-on-surface mb-2 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-error">error</span>
                                Job Gagal
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-surface-container text-on-surface-variant uppercase tracking-wider">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Queue</th>
                                            <th class="px-3 py-2 font-semibold">Gagal Pada</th>
                                            <th class="px-3 py-2 font-semibold">Exception</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant">
                                        <tr v-for="job in queueData.failedJobs" :key="job.id" class="hover:bg-surface-container-low transition-colors">
                                            <td class="px-3 py-2 text-on-surface-variant">{{ job.queue }}</td>
                                            <td class="px-3 py-2 text-on-surface-variant whitespace-nowrap">{{ job.failed_at }}</td>
                                            <td class="px-3 py-2 text-error max-w-xs truncate" :title="job.exception_preview">{{ job.exception_preview }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <div v-else class="flex items-center justify-center py-16">
                        <p class="text-on-surface-variant">Gagal memuat data antrean.</p>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Add Article Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAddModal = false"></div>
                <div class="relative glass-panel rounded-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">add_circle</span>
                            Tambah Artikel Manual
                        </h2>
                        <button @click="showAddModal = false" class="p-1 rounded-lg hover:bg-surface-container transition-colors">
                            <span class="material-symbols-outlined text-on-surface-variant">close</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitArticle" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Judul Berita *</label>
                            <input v-model="addForm.title" type="text" required
                                class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Judul artikel berita..." />
                            <p v-if="addForm.errors.title" class="text-xs text-error mt-1">{{ addForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">URL Sumber *</label>
                            <input v-model="addForm.url" type="url" required
                                class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                placeholder="https://..." />
                            <p v-if="addForm.errors.url" class="text-xs text-error mt-1">{{ addForm.errors.url }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Sumber Media *</label>
                                <input v-model="addForm.source" type="text" required
                                    class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    placeholder="CNN Indonesia" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Tanggal Publikasi</label>
                                <input v-model="addForm.published_at" type="datetime-local"
                                    class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Konten (Opsional)</label>
                            <textarea v-model="addForm.content" rows="4"
                                class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                                placeholder="Isi konten artikel... (Kosongkan untuk scraping otomatis)"></textarea>
                            <p class="text-[10px] text-on-surface-variant mt-0.5">Jika dikosongkan, konten akan di-scrape secara otomatis dari URL</p>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false"
                                class="px-4 py-2 rounded-lg border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="addForm.processing"
                                class="px-4 py-2 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:bg-primary-container hover:text-on-primary-container transition-colors disabled:opacity-50 flex items-center gap-1.5">
                                <span v-if="addForm.processing" class="material-symbols-outlined animate-spin text-[14px]">progress_activity</span>
                                <span class="material-symbols-outlined text-[14px]" v-else>save</span>
                                {{ addForm.processing ? 'Menyimpan...' : 'Simpan Artikel' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Sync Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showSyncModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showSyncModal = false"></div>
                <div class="relative bg-surface rounded-2xl w-full max-w-sm p-6 shadow-xl text-center animate-fade-in-up">
                    <div class="w-12 h-12 rounded-full bg-tertiary/10 text-tertiary flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-[28px]">sync_problem</span>
                    </div>
                    <h3 class="text-lg font-bold text-on-surface mb-2">Analisis Ulang Artikel</h3>
                    <p class="text-sm text-on-surface-variant mb-6 leading-relaxed">
                        Apakah Anda yakin ingin memasukkan kembali artikel yang nyangkut (stuck) ke dalam antrean AI?
                    </p>
                    <div class="flex gap-3 justify-center">
                        <button @click="showSyncModal = false" class="px-4 py-2.5 rounded-xl font-bold text-xs text-on-surface-variant hover:bg-surface-container-high transition-colors flex-1 border border-outline-variant/50">Batal</button>
                        <button @click="executeSync" class="px-4 py-2.5 rounded-xl font-bold text-xs bg-tertiary text-on-tertiary hover:bg-tertiary/90 transition-colors flex-1 shadow-lg shadow-tertiary/20 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">sync</span>
                            Sinkronisasi
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
