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

const deleteArticle = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus artikel ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/admin/articles/${id}`, {
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
    if (confirm('Analisis ulang semua artikel yang sudah di-scrape tapi belum dianalisis?')) {
        router.post(route('admin.articles.sync-unanalyzed'), {}, {
            preserveScroll: true,
            onSuccess: () => loadQueue(),
        });
    }
};

const runEmbedding = () => {
    if (confirm('Generate embedding untuk semua artikel yang sudah dianalisis tapi belum memiliki embedding?')) {
        router.post(route('admin.embedding.run'), {}, {
            preserveScroll: true,
            onSuccess: () => loadQueue(),
        });
    }
};

const runReliability = () => {
    if (confirm('Hitung reliabilitas untuk semua artikel yang sudah dianalisis tapi belum memiliki skor?')) {
        router.post(route('admin.reliability.run'), {}, {
            preserveScroll: true,
            onSuccess: () => loadQueue(),
        });
    }
};

const showQueuePanel = ref(false);
const queueData = ref(null);
const queueLoading = ref(false);
const queueFilter = ref('all');

const showAnalysisModal = ref(false);
const selectedArticle = ref(null);

const viewAnalysis = (article) => {
    selectedArticle.value = article;
    showAnalysisModal.value = true;
};

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
    if (showQueuePanel.value) {
        loadQueue();
    }
};

const setQueueFilter = (type) => {
    queueFilter.value = type;
    loadQueue(type);
};

const selectedIds = ref([]);

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
};

const isAllSelected = computed(() => props.articles.data?.length > 0 && selectedIds.value.length === props.articles.data.length);

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.articles.data.map(a => a.id);
    }
};

const bulkVerify = () => {
    if (!selectedIds.value.length) return;
    if (confirm(`Verifikasi ${selectedIds.value.length} artikel yang dipilih?`)) {
        router.post('/admin/articles/bulk/verify', { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = []; },
        });
    }
};

const bulkReject = () => {
    if (!selectedIds.value.length) return;
    if (confirm(`Tolak ${selectedIds.value.length} artikel yang dipilih?`)) {
        router.post('/admin/articles/bulk/reject', { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = []; },
        });
    }
};

const bulkDelete = () => {
    if (!selectedIds.value.length) return;
    if (confirm(`Hapus ${selectedIds.value.length} artikel yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) {
        router.post('/admin/articles/bulk/delete', { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = []; },
        });
    }
};

const jobTypeTabs = [
    { key: 'all', label: 'Semua' },
    { key: 'scrape', label: 'Scrape' },
    { key: 'scrape_unscraped', label: 'Unscraped' },
    { key: 'analyze', label: 'Analyze' },
    { key: 'embedding', label: 'Embedding' },
    { key: 'reliability', label: 'Reliability' },
];

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
    { key: 'pending', label: 'Perlu Review', icon: 'pending' },
    { key: 'unanalyzed', label: 'Belum Analisis', icon: 'psychology' },
    { key: 'scrape_failed', label: 'Gagal Scrape', icon: 'error' },
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
                        <span class="text-xs font-semibold uppercase tracking-wider">Perlu Review</span>
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
            <div class="grid grid-cols-2 md:grid-cols-7 gap-4 mt-4">
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
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-tertiary text-[20px]">psychology</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.unanalyzed }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Belum Analisis</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">neurology</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.no_embedding }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Belum Embedding</p>
                    </div>
                </div>
                <div class="glass-panel rounded-xl p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary text-[20px]">verified</span>
                    <div>
                        <p class="text-sm font-bold text-on-surface">{{ stats.no_reliability }}</p>
                        <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Belum Reliabilitas</p>
                    </div>
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
                            <span v-if="queueData && queueData.pending > 0" class="px-1.5 py-0.5 rounded-full bg-error text-on-error text-[10px] font-bold">{{ queueData.pending }}</span>
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
                    <!-- Bulk Actions -->
                        <div v-if="selectedIds.length > 0" class="px-4 py-2 bg-primary-container/30 border-b border-outline-variant flex items-center gap-3">
                            <span class="text-xs font-semibold text-on-surface">{{ selectedIds.length }} dipilih</span>
                            <button @click="bulkVerify" class="px-2.5 py-1 rounded-lg bg-primary-container text-on-primary-container text-[10px] font-semibold hover:bg-primary hover:text-on-primary transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">check</span>
                                Verifikasi
                            </button>
                            <button @click="bulkReject" class="px-2.5 py-1 rounded-lg bg-error-container text-on-error-container text-[10px] font-semibold hover:bg-error hover:text-on-error transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">close</span>
                                Tolak
                            </button>
                            <button @click="bulkDelete" class="px-2.5 py-1 rounded-lg bg-surface-container text-on-surface-variant text-[10px] font-semibold hover:bg-error-container hover:text-on-error-container transition-colors flex items-center gap-1 border border-outline-variant">
                                <span class="material-symbols-outlined text-[12px]">delete</span>
                                Hapus
                            </button>
                            <button @click="selectedIds = []" class="ml-auto text-xs text-on-surface-variant hover:text-on-surface transition-colors">Batal</button>
                        </div>
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container text-on-surface-variant uppercase tracking-wider">
                                <tr>
                                    <th class="px-2 py-3 w-8">
                                        <input type="checkbox" :checked="isAllSelected" @click="toggleSelectAll" class="rounded border-outline-variant text-primary focus:ring-primary" />
                                    </th>
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
                                <td class="px-2 py-3">
                                    <input type="checkbox" :checked="selectedIds.includes(article.id)" @change="toggleSelect(article.id)" class="rounded border-outline-variant text-primary focus:ring-primary" />
                                </td>
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
                                    <span v-if="article.scrape_failed"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold inline-flex items-center gap-1 bg-error-container text-on-error-container"
                                    >
                                        <span class="material-symbols-outlined text-[12px]">error</span>
                                        Gagal Scrape
                                    </span>
                                    <span v-else-if="!article.is_analyzed"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold inline-flex items-center gap-1 bg-surface-container text-on-surface-variant"
                                    >
                                        <span class="material-symbols-outlined text-[12px]">psychology</span>
                                        Belum Analisis
                                    </span>
                                    <span v-else
                                        :class="[verificationBadge(article.is_verified).class, 'px-2 py-0.5 rounded-full text-[10px] font-semibold inline-flex items-center gap-1']"
                                    >
                                        <span class="material-symbols-outlined text-[12px]">{{ verificationBadge(article.is_verified).icon }}</span>
                                        {{ verificationBadge(article.is_verified).text }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        v-if="article.is_analyzed && article.is_verified !== true"
                                        @click="verifyArticle(article.id)"
                                        class="p-1.5 rounded-lg bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary transition-colors"
                                        title="Verifikasi"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                    </button>
                                    <button
                                        v-if="article.is_analyzed && article.is_verified !== false"
                                        @click="rejectArticle(article.id)"
                                        class="p-1.5 rounded-lg bg-error-container text-on-error-container hover:bg-error hover:text-on-error transition-colors ml-1"
                                        title="Tolak"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                    <button
                                        @click="viewAnalysis(article)"
                                        class="p-1.5 rounded-lg bg-surface-container text-on-surface-variant hover:bg-primary-container hover:text-on-primary-container transition-colors ml-1"
                                        title="Detail Analisis"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">info</span>
                                    </button>
                                    <button
                                        @click="deleteArticle(article.id)"
                                        class="p-1.5 rounded-lg bg-surface-container text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors ml-1"
                                        title="Hapus"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!articles.data?.length">
                                <td colspan="8" class="px-4 py-10 text-center text-on-surface-variant">
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
                    <div class="flex items-center gap-2 mb-4 flex-wrap">
                        <button
                            v-for="tab in jobTypeTabs"
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
                            <span v-if="queueData?.counts?.[tab.key] > 0" class="px-1 py-0.5 rounded-full bg-on-primary/20 text-[10px] font-bold">{{ queueData.counts[tab.key] }}</span>
                        </button>
                    </div>

                    <div v-if="queueLoading" class="flex items-center justify-center py-16">
                        <span class="material-symbols-outlined animate-spin text-primary text-[36px]">progress_activity</span>
                    </div>

                    <template v-else-if="queueData">
                        <!-- Queue Stats -->
                        <div class="grid grid-cols-6 gap-3 mb-5">
                            <div v-for="tab in jobTypeTabs" :key="tab.key" class="bg-surface-container rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold" :class="tab.key === 'all' ? 'text-on-surface' : 'text-primary'">{{ tab.key === 'all' ? queueData.pending : (queueData.counts?.[tab.key] || 0) }}</p>
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider mt-0.5">{{ tab.label }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-center gap-3 mb-4 flex-wrap">
                            <button @click="runScrape" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-tertiary-container text-on-tertiary-container hover:bg-tertiary hover:text-on-tertiary">
                                <span class="material-symbols-outlined text-[14px]">rss_feed</span>
                                Run Scrape
                            </button>
                            <button @click="runAnalysis" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-secondary-container text-on-secondary-container hover:bg-secondary hover:text-on-secondary">
                                <span class="material-symbols-outlined text-[14px]">psychology</span>
                                Run Analysis
                            </button>
                            <button @click="runEmbedding" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary">
                                <span class="material-symbols-outlined text-[14px]">neurology</span>
                                Run Embedding
                            </button>
                            <button @click="runReliability" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 bg-surface-container text-on-surface-variant hover:bg-primary-container hover:text-on-primary-container border border-outline-variant">
                                <span class="material-symbols-outlined text-[14px]">verified</span>
                                Run Reliability
                            </button>
                        </div>

                        <div v-if="queueData.failed > 0" class="mb-5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-error-container text-on-error-container text-xs font-semibold">
                                <span class="material-symbols-outlined text-[14px]">error</span>
                                {{ queueData.failed }} job gagal
                            </span>
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
                                                <span :class="[job.job_type === 'scrape' || job.job_type === 'scrape_unscraped' ? 'bg-tertiary-container text-on-tertiary-container' : 'bg-secondary-container text-on-secondary-container', 'px-1.5 py-0.5 rounded text-[10px] font-semibold']">{{ job.job_type === 'scrape_unscraped' ? 'Unscraped' : job.job_type.charAt(0).toUpperCase() + job.job_type.slice(1) }}</span>
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

        <!-- Analysis Detail Modal -->
        <Teleport to="body">
            <div v-if="showAnalysisModal && selectedArticle" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAnalysisModal = false"></div>
                <div class="relative glass-panel rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                            Detail Proses Analisis
                        </h2>
                        <button @click="showAnalysisModal = false" class="p-1 rounded-lg hover:bg-surface-container transition-colors">
                            <span class="material-symbols-outlined text-on-surface-variant">close</span>
                        </button>
                    </div>

                    <!-- Article Info -->
                    <div class="mb-5">
                        <a :href="selectedArticle.url" target="_blank" class="text-sm font-bold text-primary hover:underline">{{ selectedArticle.title }}</a>
                        <p class="text-xs text-on-surface-variant mt-0.5">{{ selectedArticle.source }} — {{ selectedArticle.published_at }}</p>
                    </div>

                    <!-- Pipeline Status -->
                    <div class="grid grid-cols-4 gap-3 mb-5">
                        <div class="rounded-xl p-3 text-center" :class="selectedArticle.is_scraped ? 'bg-primary-container text-on-primary-container' : selectedArticle.scrape_failed ? 'bg-error-container text-on-error-container' : 'bg-surface-container text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[20px] block mb-1">{{ selectedArticle.is_scraped ? 'check_circle' : selectedArticle.scrape_failed ? 'error' : 'cloud_download' }}</span>
                            <p class="text-[10px] font-semibold uppercase tracking-wider">Scrape</p>
                            <p class="text-[9px] mt-0.5 opacity-70">{{ selectedArticle.scraped_at || (selectedArticle.scrape_failed ? 'Gagal' : '—') }}</p>
                        </div>
                        <div class="rounded-xl p-3 text-center" :class="selectedArticle.is_analyzed ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[20px] block mb-1">{{ selectedArticle.is_analyzed ? 'check_circle' : 'psychology' }}</span>
                            <p class="text-[10px] font-semibold uppercase tracking-wider">Analisis</p>
                            <p class="text-[9px] mt-0.5 opacity-70">{{ selectedArticle.analyzed_at || '—' }}</p>
                        </div>
                        <div class="rounded-xl p-3 text-center" :class="selectedArticle.has_embedding ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[20px] block mb-1">{{ selectedArticle.has_embedding ? 'check_circle' : 'neurology' }}</span>
                            <p class="text-[10px] font-semibold uppercase tracking-wider">Embedding</p>
                        </div>
                        <div class="rounded-xl p-3 text-center" :class="selectedArticle.reliability_score !== null ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[20px] block mb-1">{{ selectedArticle.reliability_score !== null ? 'check_circle' : 'verified' }}</span>
                            <p class="text-[10px] font-semibold uppercase tracking-wider">Reliabilitas</p>
                            <p class="text-[9px] mt-0.5 opacity-70">{{ selectedArticle.reliability_level || '—' }}</p>
                        </div>
                    </div>

                    <!-- Analysis Results -->
                    <div v-if="selectedArticle.is_analyzed" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-surface-container rounded-xl p-4">
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Kategori</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="cat in selectedArticle.categories" :key="cat" class="px-2 py-0.5 bg-primary-container text-on-primary-container rounded text-[10px] font-semibold">{{ cat }}</span>
                                    <span v-if="!selectedArticle.categories?.length" class="text-xs text-on-surface-variant">—</span>
                                </div>
                            </div>
                            <div class="bg-surface-container rounded-xl p-4">
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Lokasi</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="loc in selectedArticle.locations" :key="loc" class="px-2 py-0.5 bg-secondary-container text-on-secondary-container rounded text-[10px] font-semibold">{{ loc }}</span>
                                    <span v-if="!selectedArticle.locations?.length" class="text-xs text-on-surface-variant">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-surface-container rounded-xl p-4">
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Tingkat Keparahan</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden">
                                        <div class="h-full rounded-full transition-all" :class="selectedArticle.severity >= 61 ? 'bg-error' : selectedArticle.severity >= 41 ? 'bg-tertiary' : selectedArticle.severity >= 21 ? 'bg-secondary' : 'bg-primary'" :style="{ width: (selectedArticle.severity ?? 0) + '%' }"></div>
                                    </div>
                                    <span class="text-sm font-bold text-on-surface">{{ selectedArticle.severity ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="bg-surface-container rounded-xl p-4">
                                <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Confidence</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden">
                                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: (selectedArticle.confidence ?? 0) + '%' }"></div>
                                    </div>
                                    <span class="text-sm font-bold text-on-surface">{{ selectedArticle.confidence ?? '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedArticle.reliability_score !== null" class="bg-surface-container rounded-xl p-4">
                            <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Skor Reliabilitas</p>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden">
                                    <div class="h-full rounded-full transition-all" :class="selectedArticle.reliability_score >= 90 ? 'bg-primary' : selectedArticle.reliability_score >= 70 ? 'bg-secondary' : selectedArticle.reliability_score >= 40 ? 'bg-tertiary' : 'bg-error'" :style="{ width: (selectedArticle.reliability_score ?? 0) + '%' }"></div>
                                </div>
                                <span class="text-sm font-bold text-on-surface">{{ selectedArticle.reliability_score }}</span>
                                <span class="text-xs text-on-surface-variant ml-1">({{ selectedArticle.reliability_level }})</span>
                            </div>
                        </div>

                        <div v-if="selectedArticle.cause" class="bg-surface-container rounded-xl p-4">
                            <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Penyebab</p>
                            <p class="text-sm text-on-surface">{{ selectedArticle.cause }}</p>
                        </div>
                        <div v-if="selectedArticle.impact" class="bg-surface-container rounded-xl p-4">
                            <p class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold mb-1">Dampak</p>
                            <p class="text-sm text-on-surface">{{ selectedArticle.impact }}</p>
                        </div>
                    </div>

                    <div v-else class="bg-surface-container rounded-xl p-6 text-center">
                        <span class="material-symbols-outlined text-[32px] block mb-2 text-on-surface-variant">hourglass_empty</span>
                        <p class="text-sm text-on-surface-variant">Artikel belum dianalisis. Proses analisis akan berjalan setelah scraping selesai.</p>
                    </div>
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
