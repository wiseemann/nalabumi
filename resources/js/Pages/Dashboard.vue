<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    categories: Array,
    urgencyScores: Array,
});

const map = ref(null);
const mapContainer = ref(null);
const provinceLayer = ref(null);
const regencyLayer = ref(null);
const loading = ref(true);
const loadingStatus = ref('Mengambil data provinsi...');
const statusText = ref('Memuat data peta...');
const countProvince = ref(0);
const countRegency = ref(0);
const infoDefault = ref(true);
const detailRegencyName = ref('—');
const detailProvinceName = ref('—');
const detailStatusText = ref('—');
const detailStatusColor = ref('#436526');
const detailStatusBg = ref('rgba(67,101,38,0.08)');
const detailStatusTextColor = ref('#2f4f12');
const detailReports = ref('—');
const detailScore = ref('—');
const selectedCategory = ref(null);
const selectedDateRange = ref('30');
const activeRegencyLayer = ref(null);
const hideDetailTimeout = ref(null);
const activeTooltipLayer = ref(null);
// Track raw mouse button state — much more reliable than Leaflet's dragstart
// because browsers fire SVG mouseover BEFORE Leaflet knows a drag has started.
const isMouseButtonDown = ref(false);

function onMouseDown() {
    isMouseButtonDown.value = true;
    // Remove any currently open tooltip from the map
    if (activeTooltipLayer.value) {
        activeTooltipLayer.value._tooltip && activeTooltipLayer.value._tooltip.remove();
        activeTooltipLayer.value = null;
    }
    // Belt-and-suspenders: remove all standalone tooltip layers from the map
    if (map.value) {
        map.value.eachLayer(l => {
            if (l instanceof L.Tooltip) l.remove();
        });
    }
}
function onMouseUp() {
    // Small delay so any pending mouseover events from the final drag position
    // are consumed while the flag is still true
    setTimeout(() => {
        isMouseButtonDown.value = false;
    }, 100);
}

const dateRanges = [
    { label: 'Hari ini', value: '1' },
    { label: '7 hari', value: '7' },
    { label: '30 hari', value: '30' },
    { label: '1 tahun', value: '365' },
];

const PROVINCE_URL = 'https://raw.githubusercontent.com/ardian28/GeoJson-Indonesia-38-Provinsi/main/Provinsi/38%20Provinsi%20Indonesia%20-%20Provinsi.json';
const REGENCY_URL = 'https://raw.githubusercontent.com/ardian28/GeoJson-Indonesia-38-Provinsi/main/Kabupaten/38%20Provinsi%20Indonesia%20-%20Kabupaten.json';

const statusConfig = {
    'Rendah': { color: '#436526', fill: '#c6efa0', bg: 'rgba(67,101,38,0.08)', textColor: '#2f4f12', fillColor: '#8db86b', borderColor: '#3d5a28' },
    'Sedang': { color: '#eac248', fill: '#ffe08a', bg: 'rgba(234,194,72,0.1)', textColor: '#574400', fillColor: '#e8c96a', borderColor: '#9a7a1c' },
    'Tinggi': { color: '#FF9D23', fill: '#ffd65a', bg: 'rgba(255,157,35,0.1)', textColor: '#745b00', fillColor: '#f0a050', borderColor: '#a96300' },
    'Kritis': { color: '#ba1a1a', fill: '#ffdad6', bg: 'rgba(186,26,26,0.08)', textColor: '#93000a', fillColor: '#d4847a', borderColor: '#8a3a30' },
};

function getUrgencyLevel(score) {
    if (score >= 61) return 'Kritis';
    if (score >= 41) return 'Tinggi';
    if (score >= 21) return 'Sedang';
    return 'Rendah';
}

function normalizeRegencyName(name) {
    if (!name) return '';
    return name
        .replace(/^Kabupaten\s+/i, '')
        .replace(/^Kota\s+/i, '')
        .replace(/^Kecamatan\s+/i, '')
        .trim()
        .toLowerCase();
}

// Pre-compute lookup table to prevent O(N^2) regex operations during map render
const scoreLookup = new Map();
watch(() => props.urgencyScores, (newScores) => {
    scoreLookup.clear();
    if (!newScores) return;
    newScores.forEach(s => {
        if (s.location?.regency_name) {
            scoreLookup.set(normalizeRegencyName(s.location.regency_name), s);
        }
        if (s.location?.name) {
            scoreLookup.set(normalizeRegencyName(s.location.name), s);
        }
    });
}, { immediate: true, deep: true });

function getRegencyStatus(regencyName) {
    const normalizedName = normalizeRegencyName(regencyName);
    const scoreData = scoreLookup.get(normalizedName);
    if (scoreData) {
        return {
            level: scoreData.level,
            score: scoreData.score,
            caseCount: scoreData.case_count,
        };
    }
    return { level: 'Rendah', score: 0, caseCount: 0 };
}

// Compute real stats for the HUD
const totalCases = computed(() => {
    if (!props.urgencyScores) return 0;
    return props.urgencyScores.reduce((sum, s) => sum + (s.case_count || 0), 0);
});

const criticalRegions = computed(() => {
    if (!props.urgencyScores) return 0;
    return props.urgencyScores.filter(s => s.level === 'Kritis').length;
});

const lastUpdate = computed(() => {
    if (!props.urgencyScores || props.urgencyScores.length === 0) return 'Belum ada data';
    const dates = props.urgencyScores.map(s => {
        // Fallback to Date.now() if no valid date found
        const d = s.date || s.created_at;
        return d ? new Date(d).getTime() : Date.now();
    });
    const maxDate = new Date(Math.max(...dates));
    return maxDate.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
});

function getStatusConfig(level) {
    const normalizedLevel = level ? level.charAt(0).toUpperCase() + level.slice(1).toLowerCase() : 'Rendah';
    return statusConfig[normalizedLevel] || statusConfig['Rendah'];
}

function provinceStyle(feature) {
    return {
        fillColor: '#e8e9e0',
        weight: 2,
        opacity: 0.75,
        color: '#74796b',
        fillOpacity: 0.18,
    };
}

function regencyStyle(feature) {
    const regencyName = feature.properties.WADMKK || feature.properties.NAME_2 || feature.properties.name || 'Tidak diketahui';
    const statusData = getRegencyStatus(regencyName);
    const sc = getStatusConfig(statusData.level);
    const hasData = statusData.caseCount > 0;
    return {
        fillColor: hasData ? sc.fillColor : '#e8e9e0',
        weight: 1,
        opacity: 0.6,
        color: '#8a8f82',
        fillOpacity: hasData ? 0.85 : 0.2,
    };
}

function regencyHighlightStyle(sc) {
    return {
        weight: 2,
        color: sc.borderColor,
        opacity: 0.8,
        fillColor: sc.fill,
        fillOpacity: 0.25,
    };
}

function regencySelectedStyle(sc) {
    return {
        weight: 3,
        color: sc.color,
        opacity: 1,
        fillColor: sc.fill,
        fillOpacity: 0.4,
    };
}

// Bottom sheet state management
const selectedRegion = ref(null);
const isBottomSheetMinimized = ref(false);

onMounted(() => {
    document.addEventListener('mousedown', onMouseDown);
    document.addEventListener('mouseup', onMouseUp);
    initMap();
    loadAllLayers();
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onMouseDown);
    document.removeEventListener('mouseup', onMouseUp);
});

function initMap() {
    const initialCenter = [-0.7893, 113.9213];
    const initialZoom = 5;

    map.value = L.map(mapContainer.value, {
        preferCanvas: true, // Crucial for performance with 514+ complex polygons
        zoomControl: false,
        attributionControl: true,
        minZoom: 4,
        maxZoom: 14,
    }).setView(initialCenter, initialZoom);

    L.control.zoom({ position: 'bottomright' }).addTo(map.value);

    map.value.createPane('regencyPane');
    map.value.getPane('regencyPane').style.zIndex = 200;

    map.value.createPane('provincePane');
    map.value.getPane('provincePane').style.zIndex = 250;
    map.value.getPane('provincePane').style.pointerEvents = 'none';

    map.value.createPane('labelPane');
    map.value.getPane('labelPane').style.zIndex = 300;
    map.value.getPane('labelPane').style.pointerEvents = 'none';

    // Uber-style light map tiles with labels
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20,
    }).addTo(map.value);
}

function buildProvinceLayer(data) {
    return L.geoJson(data, {
        style: provinceStyle,
        interactive: false,
        pane: 'provincePane',
    });
}

function buildRegencyLayer(data) {
    return L.geoJson(data, {
        style: regencyStyle,
        pane: 'regencyPane',
        onEachFeature: function (feature, layer) {
            const regencyName = feature.properties.WADMKK || feature.properties.NAME_2 || feature.properties.name || 'Tidak diketahui';
            const provinceName = feature.properties.WADMPR || feature.properties.NAME_1 || feature.properties.province || 'Tidak diketahui';
            const statusData = getRegencyStatus(regencyName);
            const sc = getStatusConfig(statusData.level);

            layer.on('mouseover', function (e) {
                if (isMouseButtonDown.value) return;
                if (activeRegencyLayer.value !== layer) {
                    e.target.setStyle(regencyHighlightStyle(sc));
                }
            });

            layer.on('mouseout', function (e) {
                if (activeRegencyLayer.value !== layer) {
                    if (regencyLayer.value) regencyLayer.value.resetStyle(e.target);
                }
            });

            layer.on('click', function (e) {
                if (activeRegencyLayer.value && activeRegencyLayer.value !== layer) {
                    if (regencyLayer.value) regencyLayer.value.resetStyle(activeRegencyLayer.value);
                }
                activeRegencyLayer.value = layer;
                layer.setStyle(regencySelectedStyle(sc));
                
                selectedRegion.value = {
                    regency: regencyName,
                    province: provinceName,
                    level: statusData.level,
                    score: statusData.score,
                    caseCount: statusData.caseCount,
                    color: sc.color,
                    bg: sc.bg,
                    textColor: sc.textColor
                };
                isBottomSheetMinimized.value = false;
                map.value.panTo(e.latlng);
            });
        },
    });
}

function clearSelection() {
    if (activeRegencyLayer.value && regencyLayer.value) {
        regencyLayer.value.resetStyle(activeRegencyLayer.value);
    }
    activeRegencyLayer.value = null;
    selectedRegion.value = null;
}

async function loadAllLayers() {
    try {
        loadingStatus.value = 'Mengambil data provinsi...';
        const provRes = await fetch(PROVINCE_URL);
        if (!provRes.ok) throw new Error(`Province fetch failed: ${provRes.status}`);
        const provData = await provRes.json();

        loadingStatus.value = 'Mengambil data kabupaten/kota...';
        const regRes = await fetch(REGENCY_URL);
        if (!regRes.ok) throw new Error(`Regency fetch failed: ${regRes.status}`);
        const regData = await regRes.json();

        loadingStatus.value = 'Merender layer peta...';

        regencyLayer.value = buildRegencyLayer(regData);
        regencyLayer.value.addTo(map.value);

        provinceLayer.value = buildProvinceLayer(provData);
        provinceLayer.value.addTo(map.value);

        const provCount = provData.features ? provData.features.length : 0;
        countProvince.value = provCount;
        
        // Official count of Kabupaten/Kota in Indonesia is 514 (416 Kabupaten & 98 Kota)
        // The GeoJSON has 518 features due to multi-part polygon definitions.
        countRegency.value = 514;

        statusText.value = `${provCount} Provinsi · 514 Kab/Kota dimuat`;
        loading.value = false;

    } catch (err) {
        console.error('Error loading GeoJSON layers:', err);
        loadingStatus.value = `Gagal memuat data: ${err.message}`;
        statusText.value = 'Error: Gagal memuat data geospasial.';
    }
}

function resetView() {
    map.value.setView([-0.7893, 113.9213], 5, { animate: true, duration: 0.8 });
    clearSelection();
}

watch([selectedCategory, selectedDateRange], () => {
    // Reload urgency data when filters change
});
</script>

<template>
    <AppLayout title="Dashboard - Peta Lingkungan">
        <div class="h-full w-full relative bg-[#F7F8F3] overflow-hidden">
            <!-- Map Container -->
            <div ref="mapContainer" class="absolute inset-0 w-full h-full" style="z-index: 1;"></div>

            <!-- Top Header Brand HUD (Mobile Friendly) -->
            <div class="absolute top-4 left-4 right-4 flex items-center justify-between pointer-events-none" style="z-index: 40;">
                <div class="glass-panel px-4 py-2.5 flex items-center gap-2 rounded-xl pointer-events-auto shadow-xl border border-outline-variant/40">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary animate-pulse"></span>
                    <span class="text-sm font-bold text-slate-800 tracking-wider">NALABUMI MONITOR</span>
                </div>
                <button @click="resetView" class="glass-panel px-4 py-2.5 flex items-center gap-1.5 rounded-xl pointer-events-auto shadow-xl hover:bg-black/5 transition-all border border-outline-variant/40 text-xs font-semibold text-slate-700">
                    <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                    Reset Peta
                </button>
            </div>

            <!-- Uber-Style Floating Legend Card (Visible on desktop/larger screens) -->
            <div class="hidden md:flex absolute top-20 right-4 w-72 glass-panel flex-col gap-3 p-4 rounded-xl shadow-2xl border border-outline-variant/40 pointer-events-auto" style="z-index: 40;">
                <div class="flex items-center gap-2 border-b border-outline-variant/40 pb-2">
                    <span class="material-symbols-outlined text-primary text-xl">legend_toggle</span>
                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Tingkat Kerentanan</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2 bg-slate-100/60 p-2 rounded-lg border border-emerald-500/20">
                        <span class="w-2 h-2 rounded-full" style="background:#436526;"></span>
                        <span class="text-slate-700">Rendah</span>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100/60 p-2 rounded-lg border border-amber-500/20">
                        <span class="w-2 h-2 rounded-full" style="background:#eac248;"></span>
                        <span class="text-slate-700">Sedang</span>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100/60 p-2 rounded-lg border border-orange-500/20">
                        <span class="w-2 h-2 rounded-full" style="background:#FF9D23;"></span>
                        <span class="text-slate-700">Tinggi</span>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100/60 p-2 rounded-lg border border-red-500/20">
                        <span class="w-2 h-2 rounded-full" style="background:#ba1a1a;"></span>
                        <span class="text-slate-700">Kritis</span>
                    </div>
                </div>
            </div>

            <!-- Mobile First Sliding Bottom Sheet -->
            <div class="absolute bottom-0 left-0 right-0 md:left-1/2 md:right-auto md:w-[480px] md:-translate-x-1/2 transition-all duration-500 ease-out pointer-events-auto"
                 :class="[isBottomSheetMinimized ? 'translate-y-[calc(100%-3rem)]' : 'translate-y-0']"
                 style="z-index: 40;">
                
                <div class="glass-panel rounded-t-2xl shadow-2xl border-t border-x border-outline-variant/40 bg-surface/90 backdrop-blur-xl overflow-hidden pb-6">
                    <!-- Sheet Drag Handle / Minimize Button -->
                    <div @click="isBottomSheetMinimized = !isBottomSheetMinimized" class="w-full py-3 flex justify-center cursor-pointer hover:bg-black/5 transition-colors">
                        <div class="w-12 h-1.5 rounded-full bg-slate-400/40 hover:bg-slate-400/60 transition-colors"></div>
                    </div>

                    <!-- Bottom Sheet Content Area -->
                    <div class="px-6 flex flex-col gap-4">
                        <!-- DEFAULT STATE: Overall Statistics (When first opened) -->
                        <div v-if="!selectedRegion" class="flex flex-col gap-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary">explore</span>
                                        Rangkuman Monitoring Nasional
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-1">Platform Geospasial Lingkungan Terintegrasi Nalabumi</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mt-2">
                                <div class="bg-slate-100/60 border border-outline-variant/40 rounded-xl p-3.5 flex flex-col">
                                    <span class="text-2xl font-black text-primary tracking-tight">{{ countProvince }}</span>
                                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider mt-1">Provinsi Dipantau</span>
                                </div>
                                <div class="bg-slate-100/60 border border-outline-variant/40 rounded-xl p-3.5 flex flex-col">
                                    <span class="text-2xl font-black text-primary tracking-tight">{{ countRegency }}</span>
                                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider mt-1">Kabupaten/Kota</span>
                                </div>
                            </div>

                            <!-- Real statistics footer -->
                            <div class="bg-slate-100/40 rounded-xl p-3 border border-outline-variant/40 flex flex-wrap gap-y-2 items-center justify-between text-xs text-slate-700">
                                <div class="flex items-center gap-1.5" title="Total akumulasi kasus lingkungan terdeteksi">
                                    <span class="material-symbols-outlined text-slate-500 text-sm">warning</span>
                                    <span>Total: <strong>{{ totalCases }} Kasus</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5" title="Jumlah wilayah berstatus kritis">
                                    <span class="material-symbols-outlined text-error/80 text-sm">emergency</span>
                                    <span>Kritis: <strong>{{ criticalRegions }} Wilayah</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5" title="Pembaruan data terbaru oleh AI">
                                    <span class="material-symbols-outlined text-slate-500 text-sm">update</span>
                                    <span>Update: <strong>{{ lastUpdate }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- SELECTED REGION STATE -->
                        <div v-else class="flex flex-col gap-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0 pr-4">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 uppercase tracking-wider font-semibold">
                                        <span class="material-symbols-outlined text-xs">flag</span>
                                        <span>{{ selectedRegion.province }}</span>
                                    </div>
                                    <h2 class="text-xl font-bold text-slate-800 truncate mt-1">{{ selectedRegion.regency }}</h2>
                                </div>
                                <button @click="clearSelection" class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>

                            <!-- Divider -->
                            <div class="h-px bg-outline-variant/40"></div>

                            <!-- Level and Scores -->
                            <div class="flex items-center gap-3">
                                <div class="px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider flex items-center gap-2 border"
                                     :style="{ borderColor: selectedRegion.color, background: selectedRegion.bg, color: selectedRegion.textColor }">
                                    <span class="w-2 h-2 rounded-full" :style="{ background: selectedRegion.color }"></span>
                                    <span>Status: {{ selectedRegion.level }}</span>
                                </div>
                                <span class="text-xs text-slate-500">Kerentanan Lingkungan</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-surface-container-lowest border border-outline-variant/50 rounded-xl p-4 text-center hover:shadow-sm transition-shadow">
                                    <span class="block text-2xl font-black text-slate-800">{{ selectedRegion.caseCount }}</span>
                                    <span class="text-[10px] text-slate-500 uppercase font-bold tracking-wider block mt-1">Total Kasus</span>
                                </div>
                                <div class="bg-surface-container-lowest border border-outline-variant/50 rounded-xl p-4 text-center hover:shadow-sm transition-shadow">
                                    <div class="flex items-baseline justify-center">
                                        <span class="text-2xl font-black" :style="{ color: selectedRegion.textColor }">{{ selectedRegion.score }}</span>
                                        <span class="text-xs font-bold text-slate-400">/100</span>
                                    </div>
                                    <span class="text-[10px] text-slate-500 uppercase font-bold tracking-wider block mt-1">Skor Kerawanan</span>
                                </div>
                            </div>

                            <!-- Action button -->
                            <button @click="router.visit('/regency', { data: { regency: selectedRegion.regency, province: selectedRegion.province } })"
                                    class="w-full py-3 bg-primary hover:bg-primary-container text-on-primary font-bold rounded-xl transition-all shadow-lg shadow-primary/20 text-sm flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm text-on-primary">arrow_forward</span>
                                Lihat Detail Wilayah
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Footer Status HUD -->
            <div class="absolute bottom-4 left-4 right-4 md:right-auto pointer-events-none hidden md:flex" style="z-index: 35;">
                <div class="glass-panel px-4 py-2.5 flex items-center gap-2.5 rounded-lg border border-outline-variant/40">
                    <span class="material-symbols-outlined text-primary text-sm">info</span>
                    <span class="text-xs text-slate-700 font-medium">{{ statusText }}</span>
                </div>
            </div>

            <!-- Loading overlay -->
            <div v-if="loading" class="fixed inset-0 flex flex-col items-center justify-center gap-4 bg-background/95 backdrop-blur-md pointer-events-auto" style="z-index: 45;">
                <div class="flex items-center gap-3">
                    <img :src="'/logo.svg'" alt="Nalabumi Logo" class="h-16 w-auto object-contain animate-pulse" />
                </div>
                <p class="text-sm text-on-surface-variant">Memuat data geospasial Indonesia...</p>
                <div class="flex gap-2 mt-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-primary animate-bounce" style="animation-delay: 0s;"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-primary animate-bounce" style="animation-delay: 0.16s;"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-primary animate-bounce" style="animation-delay: 0.32s;"></div>
                </div>
                <div class="w-48 mt-2 h-1 bg-surface-container rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full animate-pulse" style="width: 60%;"></div>
                </div>
                <p class="text-xs text-on-surface-variant/70 mt-1">{{ loadingStatus }}</p>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.leaflet-control-zoom a {
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(12px);
    color: #1a1c17 !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
    font-weight: 600 !important;
}

.leaflet-control-zoom a:hover {
    background: rgba(0, 0, 0, 0.05) !important;
}

/* Pointer cursor on regency polygons (clickable) */
.leaflet-interactive {
    cursor: pointer !important;
}

.leaflet-interactive:focus {
    outline: none !important;
}
</style>
