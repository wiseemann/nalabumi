<script setup>
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    categories: Array,
});

const map = ref(null);
const mapContainer = ref(null);
const markersLayer = ref(null);
const urgencyData = ref([]);
const selectedCategory = ref(null);
const selectedDateRange = ref('30');
const regionDetail = ref(null);
const showDetail = ref(false);
const selectedArticle = ref(null);
const showArticleModal = ref(false);

const levelColors = {
    rendah: '#22c55e',
    sedang: '#eab308',
    tinggi: '#f97316',
    kritis: '#ef4444',
};

const dateRanges = [
    { label: 'Hari ini', value: '1' },
    { label: '7 hari', value: '7' },
    { label: '30 hari', value: '30' },
    { label: '1 tahun', value: '365' },
];

const regencyCoords = {
    'Sumatera Barat': [-0.7, 100.5],
    'Pasaman': [0.5, 99.8],
    'Pasaman Barat': [0.3, 99.5],
    'Limapuluh Kota': [-0.1, 100.6],
    'Sawahlunto': [-0.7, 100.8],
    'Dharmasraya': [-1.2, 101.3],
    'Solok Selatan': [-1.5, 101.2],
    'Solok': [-0.8, 100.6],
    'Sijunjung': [-0.9, 101.0],
    'Pesisir Selatan': [-1.8, 100.5],
    'Kabupaten Banjar': [-3.4, 115.0],
    'Banjar': [-3.4, 115.0],
    'Nagan Raya': [4.1, 96.5],
    'Aceh': [4.3, 96.8],
    'Halmahera Tengah': [0.5, 128.0],
    'Maluku Utara': [1.0, 128.0],
    'Sikka': [-8.6, 122.0],
    'Flores': [-8.6, 122.0],
    'Nusa Tenggara Timur': [-8.6, 121.0],
    'Kalimantan Selatan': [-3.0, 115.0],
};

onMounted(() => {
    initMap();
    fetchUrgencyData();
});

function initMap() {
    map.value = L.map(mapContainer.value).setView([-2.5, 118], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map.value);

    markersLayer.value = L.layerGroup().addTo(map.value);
}

async function fetchUrgencyData() {
    const params = new URLSearchParams();
    if (selectedCategory.value) {
        params.append('category', selectedCategory.value);
    }

    const response = await fetch(`/api/urgency-scores?${params}`);
    urgencyData.value = await response.json();
    updateMarkers();
}

function updateMarkers() {
    markersLayer.value.clearLayers();

    urgencyData.value.forEach(item => {
        if (!item.location) return;

        const loc = item.location;
        const name = loc.regency_name || loc.name;
        const coords = regencyCoords[name];

        if (!coords) return;

        const color = levelColors[item.level] || '#6b7280';

        const marker = L.circleMarker(coords, {
            radius: Math.max(8, Math.min(25, item.score / 4)),
            fillColor: color,
            color: '#fff',
            weight: 2,
            fillOpacity: 0.8,
        });

        marker.bindPopup(`
            <div style="min-width:200px">
                <h3 style="font-weight:bold;margin-bottom:8px">${name}</h3>
                ${loc.province_name ? `<p style="color:#6b7280;font-size:12px">${loc.province_name}</p>` : ''}
                <div style="margin-top:8px">
                    <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600;background:${color};color:#fff">${item.level}</span>
                    <span style="margin-left:8px;font-size:14px;font-weight:bold">Skor: ${item.score}</span>
                </div>
                <p style="margin-top:8px;font-size:13px">Kasus: ${item.case_count}</p>
            </div>
        `);

        marker.on('click', () => {
            fetchRegionDetail(loc.id, name, loc.province_name);
        });

        markersLayer.value.addLayer(marker);
    });
}

async function fetchRegionDetail(locationId, name, province) {
    const response = await fetch(`/api/regions/${locationId}/details`);
    const data = await response.json();
    regionDetail.value = { ...data, displayName: name, displayProvince: province };
    showDetail.value = true;
    selectedArticle.value = null;
}

function viewArticle(article) {
    selectedArticle.value = article;
    showArticleModal.value = true;
}

function closeArticleModal() {
    showArticleModal.value = false;
    selectedArticle.value = null;
}

watch([selectedCategory, selectedDateRange], () => {
    fetchUrgencyData();
});
</script>

<template>
    <AppLayout title="Peta Lingkungan">
        <div class="h-screen flex flex-col">
            <div class="bg-white border-b border-gray-200 p-4">
                <div class="max-w-7xl mx-auto flex flex-wrap gap-4 items-center">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kategori</label>
                        <select v-model="selectedCategory" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option :value="null">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Periode</label>
                        <select v-model="selectedDateRange" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="range in dateRanges" :key="range.value" :value="range.value">{{ range.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 ml-auto">
                        <span class="inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-full" style="background:#22c55e"></span> Rendah</span>
                        <span class="inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-full" style="background:#eab308"></span> Sedang</span>
                        <span class="inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-full" style="background:#f97316"></span> Tinggi</span>
                        <span class="inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-full" style="background:#ef4444"></span> Kritis</span>
                    </div>
                </div>
            </div>
            <div class="flex-1 flex">
                <div ref="mapContainer" class="flex-1"></div>
                <div v-if="showDetail && regionDetail" class="w-96 bg-white border-l border-gray-200 overflow-y-auto">
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ regionDetail.displayName }}</h3>
                                <p class="text-sm text-gray-500">{{ regionDetail.displayProvince }}</p>
                            </div>
                            <button @click="showDetail = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                        <div v-if="regionDetail.cases && regionDetail.cases.length > 0" class="space-y-3">
                            <h4 class="text-sm font-semibold text-gray-700">Kasus Terkait ({{ regionDetail.cases.length }})</h4>
                            <div v-for="c in regionDetail.cases" :key="c.id" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer transition" @click="viewArticle(c.article)">
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full font-medium mb-2"
                                    :class="{
                                        'bg-green-100 text-green-800': c.category?.slug === 'deforestasi',
                                        'bg-red-100 text-red-800': c.category?.slug === 'tambang-ilegal',
                                        'bg-orange-100 text-orange-800': c.category?.slug === 'kebakaran-hutan',
                                        'bg-blue-100 text-blue-800': c.category?.slug === 'pencemaran-sungai',
                                        'bg-purple-100 text-purple-800': c.category?.slug === 'pencemaran-laut',
                                        'bg-yellow-100 text-yellow-800': c.category?.slug === 'perburuan-satwa',
                                        'bg-teal-100 text-teal-800': c.category?.slug === 'kerusakan-pesisir',
                                        'bg-gray-100 text-gray-800': c.category?.slug === 'limbah-industri',
                                    }">
                                    {{ c.category?.name }}
                                </span>
                                <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ c.article?.title }}</p>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs text-gray-400">{{ c.article?.source }}</span>
                                    <span class="text-xs font-medium" :class="c.severity >= 60 ? 'text-red-600' : c.severity >= 40 ? 'text-orange-600' : 'text-green-600'">Severity: {{ c.severity }}/100</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-400 py-8">
                            <p>Belum ada kasus untuk wilayah ini</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showArticleModal && selectedArticle" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4" @click.self="closeArticleModal">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b border-gray-200 flex justify-between items-start">
                    <div class="flex-1 pr-4">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ selectedArticle.title }}</h2>
                        <div class="flex items-center gap-3 text-sm text-gray-500">
                            <span>{{ selectedArticle.source }}</span>
                            <span>&middot;</span>
                            <span>{{ selectedArticle.published_at }}</span>
                            <span v-if="selectedArticle.author">&middot;</span>
                            <span v-if="selectedArticle.author">{{ selectedArticle.author }}</span>
                        </div>
                    </div>
                    <button @click="closeArticleModal" class="text-gray-400 hover:text-gray-600 text-2xl flex-shrink-0">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <div v-if="selectedArticle.image_url" class="mb-4">
                        <img :src="selectedArticle.image_url" :alt="selectedArticle.title" class="w-full h-48 object-cover rounded-lg" />
                    </div>
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ selectedArticle.content }}</p>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                    <a :href="selectedArticle.url" target="_blank" rel="noopener noreferrer" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1">
                        Baca artikel asli
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.leaflet-container { height: 100%; width: 100%; }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
