<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mediaReputations: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingMedia = ref(null);

const addForm = useForm({
    name: '',
    score: 50,
});

const editForm = useForm({
    name: '',
    score: 50,
});

const applySearch = () => {
    router.get('/admin/media-reputations', {
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const logout = () => {
    router.post('/admin/logout');
};

const submitAdd = () => {
    addForm.post('/admin/media-reputations', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const openEditModal = (media) => {
    editingMedia.value = media;
    editForm.name = media.name;
    editForm.score = media.score;
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingMedia.value) return;
    editForm.put(`/admin/media-reputations/${editingMedia.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingMedia.value = null;
            editForm.reset();
        },
    });
};

const deleteMedia = (id, name) => {
    if (confirm(`Apakah Anda yakin ingin menghapus "${name}" dari daftar reputasi media?`)) {
        router.delete(`/admin/media-reputations/${id}`, {
            preserveScroll: true,
        });
    }
};

const getScoreColor = (score) => {
    if (score >= 90) return 'text-[#0f5223] bg-[#c4eed0]';
    if (score >= 70) return 'text-[#004d40] bg-[#c2e7ff]';
    if (score >= 40) return 'text-[#745b00] bg-[#ffdf99]';
    return 'text-[#ba1a1a] bg-[#ffdad6]';
};
</script>

<template>
    <Head title="Reputasi Media — Admin Nalabumi" />
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
                    <Link href="/admin" class="text-on-surface-variant hover:text-primary text-sm font-medium transition-colors">Dasbor Utama</Link>
                    <Link href="/admin/media-reputations" class="text-primary border-b-2 border-primary py-5 text-sm font-medium transition-colors">Reputasi Media</Link>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- Mobile Navigation Menu Toggle would go here if needed, keeping it simple for now -->
                <Link href="/dashboard" class="text-xs text-on-surface-variant hover:text-primary font-medium transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    Lihat Situs
                </Link>
                <button @click="logout" class="px-4 py-2 bg-error-container text-on-error-container rounded-lg font-semibold text-xs hover:bg-error hover:text-on-error transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    Keluar
                </button>
            </div>
        </nav>

        <main class="pt-24 px-6 md:px-10 pb-10 max-w-7xl mx-auto">
            <header class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[28px]">domain_verification</span>
                        Manajemen Reputasi Media
                    </h1>
                    <p class="text-sm text-on-surface-variant mt-1">Atur daftar skor awal (base score) portal berita tepercaya di Indonesia.</p>
                </div>
                <button @click="showAddModal = true" class="px-4 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-semibold hover:bg-primary-container hover:text-on-primary-container transition-colors flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tambah Portal Baru
                </button>
            </header>

            <div class="glass-panel rounded-2xl overflow-hidden shadow-sm">
                <!-- Toolbar -->
                <div class="p-4 border-b border-outline-variant bg-surface-container-lowest">
                    <div class="relative max-w-sm">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                        <input v-model="search" @keyup.enter="applySearch" type="text" placeholder="Cari nama media atau domain..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-outline-variant bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all" />
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container text-on-surface-variant font-semibold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-6 py-4">Nama Media / Domain</th>
                                <th class="px-6 py-4 text-center">Skor Kepercayaan Dasar</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="media in mediaReputations.data" :key="media.id" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-6 py-4 font-semibold text-on-surface flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ media.name.substring(0, 1).toUpperCase() }}
                                    </div>
                                    {{ media.name }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="[getScoreColor(media.score), 'px-3 py-1 rounded-full text-xs font-bold inline-flex items-center justify-center min-w-[50px]']">
                                        {{ media.score }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <button @click="openEditModal(media)" class="p-2 rounded-lg text-secondary hover:bg-secondary-container hover:text-on-secondary-container transition-colors mr-1" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button @click="deleteMedia(media.id, media.name)" class="p-2 rounded-lg text-error hover:bg-error-container hover:text-on-error-container transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!mediaReputations.data?.length">
                                <td colspan="4" class="px-6 py-12 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-4xl block mb-2 opacity-50">search_off</span>
                                    Tidak ada data reputasi media yang ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="mediaReputations.last_page > 1" class="p-4 border-t border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface-container-lowest">
                    <p class="text-xs text-on-surface-variant font-medium">
                        Menampilkan {{ mediaReputations.from }}–{{ mediaReputations.to }} dari {{ mediaReputations.total }} entri
                    </p>
                    <div class="flex items-center gap-1 flex-wrap">
                        <Link v-for="link in mediaReputations.links" :key="link.label" :href="link.url || '#'" :class="[
                            link.active ? 'bg-primary text-on-primary font-bold shadow-sm' : 'bg-surface text-on-surface-variant hover:bg-surface-container border border-outline-variant',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                            'px-3 py-1.5 rounded-lg text-xs transition-all'
                        ]" v-html="link.label.replace('pagination.previous', '&laquo; Prev').replace('pagination.next', 'Next &raquo;')" preserve-scroll />
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
                <div class="relative bg-surface rounded-2xl w-full max-w-md p-6 shadow-2xl border border-outline-variant">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">add_circle</span>
                            Tambah Reputasi Media
                        </h2>
                        <button @click="showAddModal = false" class="p-1 rounded-full hover:bg-surface-variant transition-colors text-on-surface-variant">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wide">Nama Media / Domain *</label>
                            <input v-model="addForm.name" type="text" required class="w-full px-4 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="Contoh: kompas.com" />
                            <p class="text-[10px] text-on-surface-variant mt-1">Gunakan domain utamanya (contoh: kompas.com, detik.com).</p>
                            <p v-if="addForm.errors.name" class="text-xs text-error mt-1">{{ addForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wide flex justify-between">
                                Skor Kepercayaan (0-100) *
                                <span class="text-primary font-bold">{{ addForm.score }}</span>
                            </label>
                            <input v-model="addForm.score" type="range" min="0" max="100" required class="w-full accent-primary" />
                            <div class="flex justify-between text-[10px] text-on-surface-variant font-medium mt-1">
                                <span>0 (Tidak Tepercaya)</span>
                                <span>100 (Sangat Tepercaya)</span>
                            </div>
                            <p v-if="addForm.errors.score" class="text-xs text-error mt-1">{{ addForm.errors.score }}</p>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 mt-6 border-t border-outline-variant">
                            <button type="button" @click="showAddModal = false" class="px-5 py-2.5 rounded-xl border border-outline-variant text-on-surface-variant text-sm font-bold hover:bg-surface-container transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="addForm.processing" class="px-5 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-bold hover:bg-primary-container hover:text-on-primary-container transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm">
                                <span v-if="addForm.processing" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                                <span class="material-symbols-outlined text-[18px]" v-else>save</span>
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Edit Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showEditModal = false"></div>
                <div class="relative bg-surface rounded-2xl w-full max-w-md p-6 shadow-2xl border border-outline-variant">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-secondary">edit</span>
                            Edit Reputasi Media
                        </h2>
                        <button @click="showEditModal = false" class="p-1 rounded-full hover:bg-surface-variant transition-colors text-on-surface-variant">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wide">Nama Media / Domain *</label>
                            <input v-model="editForm.name" type="text" required class="w-full px-4 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary transition-all" />
                            <p v-if="editForm.errors.name" class="text-xs text-error mt-1">{{ editForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wide flex justify-between">
                                Skor Kepercayaan (0-100) *
                                <span class="text-secondary font-bold">{{ editForm.score }}</span>
                            </label>
                            <input v-model="editForm.score" type="range" min="0" max="100" required class="w-full accent-secondary" />
                            <div class="flex justify-between text-[10px] text-on-surface-variant font-medium mt-1">
                                <span>0 (Tidak Tepercaya)</span>
                                <span>100 (Sangat Tepercaya)</span>
                            </div>
                            <p v-if="editForm.errors.score" class="text-xs text-error mt-1">{{ editForm.errors.score }}</p>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 mt-6 border-t border-outline-variant">
                            <button type="button" @click="showEditModal = false" class="px-5 py-2.5 rounded-xl border border-outline-variant text-on-surface-variant text-sm font-bold hover:bg-surface-container transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 bg-secondary text-on-secondary rounded-xl text-sm font-bold hover:bg-secondary-container hover:text-on-secondary-container transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm">
                                <span v-if="editForm.processing" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                                <span class="material-symbols-outlined text-[18px]" v-else>save_as</span>
                                Perbarui
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
