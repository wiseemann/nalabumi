<script setup>
import { ref, onMounted, watch, computed } from 'vue';
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

function getRegencyStatus(regencyName) {
    const normalizedName = normalizeRegencyName(regencyName);
    const scoreData = props.urgencyScores.find(s => {
        const locRegency = normalizeRegencyName(s.location?.regency_name);
        const locName = normalizeRegencyName(s.location?.name);
        return locRegency === normalizedName || locName === normalizedName;
    });
    if (scoreData) {
        return {
            level: scoreData.level,
            score: scoreData.score,
            caseCount: scoreData.case_count,
        };
    }
    return { level: 'Rendah', score: 0, caseCount: 0 };
}

function getStatusConfig(level) {
    // Normalize level to match config keys (capitalize first letter)
    const normalizedLevel = level ? level.charAt(0).toUpperCase() + level.slice(1).toLowerCase() : 'Rendah';
    return statusConfig[normalizedLevel] || statusConfig['Rendah'];
}

function provinceStyle(feature) {
    const name = feature.properties.PROVINSI || feature.properties.Propinsi || feature.properties.state || feature.properties.NAME_1 || '';
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
    return {
        fillColor: sc.fillColor,
        weight: 1,
        opacity: 0.6,
        color: '#8a8f82',
        fillOpacity: statusData.caseCount > 0 ? 0.85 : 0.2,
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

onMounted(() => {
    initMap();
    loadAllLayers();
});

function initMap() {
    const initialCenter = [-0.7893, 113.9213];
    const initialZoom = 5;

    map.value = L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: true,
        minZoom: 4,
        maxZoom: 14,
    }).setView(initialCenter, initialZoom);

    L.control.zoom({ position: 'topright' }).addTo(map.value);

    map.value.createPane('regencyPane');
    map.value.getPane('regencyPane').style.zIndex = 200;

    map.value.createPane('provincePane');
    map.value.getPane('provincePane').style.zIndex = 250;
    map.value.getPane('provincePane').style.pointerEvents = 'none';

    map.value.createPane('labelPane');
    map.value.getPane('labelPane').style.zIndex = 300;
    map.value.getPane('labelPane').style.pointerEvents = 'none';

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
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

            layer.bindTooltip(
                `<div style="font-weight:600;font-size:14px;margin-bottom:2px;">${regencyName}</div>` +
                `<div style="color:#43493d;font-size:12px;">Prov. ${provinceName}</div>` +
                `<div style="display:inline-flex;align-items:center;gap:5px;margin-top:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;background:${sc.bg};color:${sc.textColor};">` +
                `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${sc.color};"></span> ${statusData.level}` +
                `</div>`,
                {
                    sticky: true,
                    direction: 'top',
                    offset: [0, -8],
                    className: 'nalabumi-tooltip',
                }
            );

            layer.on('mouseover', function (e) {
                clearTimeout(hideDetailTimeout.value);
                if (activeRegencyLayer.value !== layer) {
                    e.target.setStyle(regencyHighlightStyle(sc));
                }
                showRegencyDetail(regencyName, provinceName, statusData);
            });

            layer.on('mouseout', function (e) {
                if (activeRegencyLayer.value !== layer) {
                    if (regencyLayer.value) regencyLayer.value.resetStyle(e.target);
                }
                hideDetailTimeout.value = setTimeout(() => {
                    hideRegencyDetail();
                }, 300);
            });

            layer.on('click', function (e) {
                if (activeRegencyLayer.value && activeRegencyLayer.value !== layer) {
                    if (regencyLayer.value) regencyLayer.value.resetStyle(activeRegencyLayer.value);
                }
                activeRegencyLayer.value = layer;
                layer.setStyle(regencySelectedStyle(sc));
                clearTimeout(hideDetailTimeout.value);
                showRegencyDetail(regencyName, provinceName, statusData);
            });
        },
    });
}

function showRegencyDetail(regency, province, statusData) {
    const sc = getStatusConfig(statusData.level);
    detailRegencyName.value = regency;
    detailProvinceName.value = `Provinsi ${province}`;
    detailStatusText.value = statusData.level;
    detailStatusColor.value = sc.color;
    detailStatusBg.value = sc.bg;
    detailStatusTextColor.value = sc.textColor;
    detailReports.value = statusData.caseCount;
    detailScore.value = statusData.score + '/100';
    infoDefault.value = false;
}

function hideRegencyDetail() {
    infoDefault.value = true;
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
        const regCount = regData.features ? regData.features.length : 0;
        countProvince.value = provCount;
        countRegency.value = regCount;

        statusText.value = `${provCount} Provinsi · ${regCount} Kab/Kota dimuat`;
        loading.value = false;

    } catch (err) {
        console.error('Error loading GeoJSON layers:', err);
        loadingStatus.value = `Gagal memuat data: ${err.message}`;
        statusText.value = 'Error: Gagal memuat data geospasial.';
    }
}

function resetView() {
    map.value.setView([-0.7893, 113.9213], 5, { animate: true, duration: 0.8 });
}

watch([selectedCategory, selectedDateRange], () => {
    // Reload urgency data when filters change
});
</script>

<template>
    <AppLayout title="Peta Lingkungan">
        <div class="h-[calc(100vh-4rem)] relative">
            <div ref="mapContainer" class="absolute inset-0 w-full h-full" style="z-index: 1;"></div>
        </div>

        <Teleport to="body">
            <div class="fixed top-28 left-6 flex flex-col gap-4 pointer-events-none" style="z-index: 40;">
                <div class="w-[340px] glass-panel rounded-xl overflow-hidden transition-all duration-300 pointer-events-auto">
                    <div class="p-4 flex flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary filled text-[22px]">public</span>
                            <h2 class="font-semibold text-lg text-on-surface">Peta Administrasi</h2>
                        </div>
                        <p class="text-sm text-on-surface-variant leading-relaxed">
                            Menampilkan batas provinsi dan kabupaten/kota seluruh Indonesia beserta status lingkungan secara interaktif.
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-surface-container rounded-lg p-3 text-center">
                                <span class="block text-xl font-bold text-primary">{{ countProvince }}</span>
                                <span class="text-xs text-on-surface-variant uppercase mt-0.5 block">Provinsi</span>
                            </div>
                            <div class="bg-surface-container rounded-lg p-3 text-center">
                                <span class="block text-xl font-bold text-primary">{{ countRegency }}</span>
                                <span class="text-xs text-on-surface-variant uppercase mt-0.5 block">Kab/Kota</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-[340px] glass-panel rounded-xl px-4 py-3 flex items-center justify-between pointer-events-auto">
                    <span class="text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Status:</span>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <div class="w-2.5 h-2.5 rounded-sm" style="background:#436526;"></div>
                            <span class="text-xs text-on-surface font-medium">Rendah</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2.5 h-2.5 rounded-sm" style="background:#eac248;"></div>
                            <span class="text-xs text-on-surface font-medium">Sedang</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2.5 h-2.5 rounded-sm" style="background:#FF9D23;"></div>
                            <span class="text-xs text-on-surface font-medium">Tinggi</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2.5 h-2.5 rounded-sm" style="background:#ba1a1a;"></div>
                            <span class="text-xs text-on-surface font-medium">Kritis</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!infoDefault" class="fixed top-28 right-6 w-80 glass-panel rounded-xl overflow-hidden transition-all duration-300 pointer-events-auto" style="z-index: 40;">
                <div class="p-4 flex flex-col gap-2.5">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-primary text-[20px]">location_on</span>
                        <span class="text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Detail Wilayah</span>
                    </div>
                    <h2 class="font-semibold text-lg text-on-surface leading-tight">{{ detailRegencyName }}</h2>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">flag</span>
                        <span class="text-sm text-on-surface-variant">{{ detailProvinceName }}</span>
                    </div>
                    <div class="h-px bg-outline-variant/40"></div>
                    <div class="inline-flex items-center gap-2 self-start px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider"
                        :style="{ background: detailStatusBg, color: detailStatusTextColor }">
                        <div class="w-2 h-2 rounded-full" :style="{ background: detailStatusColor }"></div>
                        <span>{{ detailStatusText }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <div class="bg-surface-container rounded-lg p-2.5 text-center">
                            <span class="block text-lg font-bold text-on-surface">{{ detailReports }}</span>
                            <span class="text-xs text-on-surface-variant uppercase block">Laporan</span>
                        </div>
                        <div class="bg-surface-container rounded-lg p-2.5 text-center">
                            <span class="block text-lg font-bold text-on-surface">{{ detailScore }}</span>
                            <span class="text-xs text-on-surface-variant uppercase block">Skor</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fixed bottom-6 left-6 w-80 glass-panel rounded-lg px-4 py-2.5 flex items-center gap-3 pointer-events-auto" style="z-index: 40;">
                <span class="material-symbols-outlined text-primary text-[18px]">info</span>
                <span class="text-sm text-on-surface-variant flex-1 truncate">{{ statusText }}</span>
            </div>

            <div v-if="loading" class="fixed inset-0 flex flex-col items-center justify-center gap-4 bg-background/92 backdrop-blur-sm pointer-events-auto" style="z-index: 45;">
                <div class="flex items-center gap-3">
                    <img :src="'/logo.svg'" alt="Nalabumi Logo" class="h-16 w-auto object-contain" />
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
                <p class="text-xs text-on-surface-variant/60 mt-1">{{ loadingStatus }}</p>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style>
.nalabumi-tooltip {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(195, 200, 185, 0.6) !important;
    border-radius: 10px !important;
    padding: 10px 14px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.06) !important;
    font-family: 'Inter', sans-serif !important;
    font-size: 13px !important;
    line-height: 1.5 !important;
    color: #1a1c17 !important;
}

.nalabumi-tooltip::before {
    border-top-color: rgba(255, 255, 255, 0.95) !important;
}

.leaflet-control-zoom a {
    background: rgba(255, 255, 255, 0.92) !important;
    backdrop-filter: blur(8px);
    color: #1a1c17 !important;
    border-color: rgba(195, 200, 185, 0.5) !important;
    font-weight: 600 !important;
}

.leaflet-control-zoom a:hover {
    background: rgba(67, 101, 38, 0.1) !important;
}
</style>
