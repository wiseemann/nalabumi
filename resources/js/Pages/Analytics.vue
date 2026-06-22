<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    filters: Object,
    stats: Object,
    monthlyTrend: Array,
    categoryBreakdown: Array,
    topEntities: Array,
});

const period = ref(props.filters.period);
const region = ref(props.filters.region);

const periodOptions = [
    { label: '7 Hari', value: '7' },
    { label: '30 Hari', value: '30' },
    { label: '90 Hari', value: '90' },
    { label: '1 Tahun', value: '365' },
];

function applyFilters() {
    router.get('/analytics', { period: period.value, region: region.value }, { preserveState: true });
}

const trendChartOptions = {
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
    },
    colors: ['#436526'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.3,
            opacityTo: 0.05,
            stops: [0, 100],
        },
    },
    stroke: {
        curve: 'smooth',
        width: 2,
    },
    xaxis: {
        categories: props.monthlyTrend.map(t => t.month),
        labels: {
            style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' },
        },
        beginAtZero: true,
    },
    grid: {
        borderColor: '#e2e3da',
        strokeDashArray: 3,
    },
    tooltip: {
        style: { fontFamily: 'Inter, sans-serif' },
    },
};

const trendChartSeries = [{
    name: 'Laporan Kerusakan',
    data: props.monthlyTrend.map(t => t.count),
}];

const categoryChartOptions = {
    chart: {
        type: 'bar',
        height: 350,
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
    },
    colors: ['#ba1a1a', '#a96300', '#745b00', '#ffb870', '#74796b'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%',
        },
    },
    xaxis: {
        categories: props.categoryBreakdown.map(c => c.name),
        labels: {
            style: { fontFamily: 'Inter, sans-serif', fontSize: '10px', colors: '#43493d' },
            rotate: -45,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' },
        },
        beginAtZero: true,
    },
    grid: {
        borderColor: '#e2e3da',
        strokeDashArray: 3,
    },
    legend: { show: false },
    tooltip: {
        style: { fontFamily: 'Inter, sans-serif' },
    },
};

const categoryChartSeries = [{
    name: 'Jumlah Kasus',
    data: props.categoryBreakdown.map(c => c.cases_count),
}];

function getStatusBadge(severity) {
    if (severity >= 61) return { text: 'Kritis', class: 'bg-error-container text-on-error-container' };
    if (severity >= 41) return { text: 'Tinggi', class: 'bg-tertiary-container text-on-tertiary-container' };
    if (severity >= 21) return { text: 'Sedang', class: 'bg-secondary-container text-on-secondary-container' };
    return { text: 'Rendah', class: 'bg-primary-fixed-dim text-on-primary-fixed-variant' };
}
</script>

<template>
    <AppLayout title="Analytics">
        <div class="py-8 px-6 md:px-10 max-w-[1440px] mx-auto flex flex-col gap-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-semibold text-on-surface">Analitik Laporan</h1>
                    <p class="text-base text-on-surface-variant mt-1">Tinjauan komprehensif terhadap data perusakan lingkungan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative bg-surface border border-outline-variant rounded-lg px-4 py-2 flex items-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-on-surface-variant text-sm">calendar_today</span>
                        <select v-model="period" class="bg-transparent border-none focus:ring-0 text-xs font-semibold text-on-surface p-0 cursor-pointer appearance-none pr-6">
                            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <span class="material-symbols-outlined text-on-surface-variant text-sm absolute right-3 pointer-events-none">expand_more</span>
                    </div>
                    <button @click="applyFilters" class="bg-primary text-on-primary text-xs font-semibold px-4 py-2 rounded-lg hover:bg-primary-container transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">filter_list</span>
                        Terapkan
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Laporan</span>
                        <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-full">description</span>
                    </div>
                    <span class="text-4xl font-bold text-on-surface">{{ stats.totalReports }}</span>
                    <div class="flex items-center gap-1 text-primary">
                        <span class="material-symbols-outlined text-sm">trending_up</span>
                        <span class="text-xs font-semibold">Periode aktif</span>
                    </div>
                </div>
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tingkat Kritis</span>
                        <span class="material-symbols-outlined text-error bg-error-container/20 p-2 rounded-full">warning</span>
                    </div>
                    <span class="text-4xl font-bold text-on-surface">{{ stats.criticalCount }}</span>
                    <div class="flex items-center gap-1 text-error">
                        <span class="material-symbols-outlined text-sm">flag</span>
                        <span class="text-xs font-semibold">Kasus kritis</span>
                    </div>
                </div>
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Area Terdampak</span>
                        <span class="material-symbols-outlined text-tertiary bg-tertiary-container/20 p-2 rounded-full">public</span>
                    </div>
                    <span class="text-4xl font-bold text-on-surface">{{ stats.affectedAreas }}</span>
                    <div class="flex items-center gap-1 text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">location_on</span>
                        <span class="text-xs font-semibold">Wilayah</span>
                    </div>
                </div>
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori</span>
                        <span class="material-symbols-outlined text-secondary bg-secondary-container/20 p-2 rounded-full">category</span>
                    </div>
                    <span class="text-4xl font-bold text-on-surface">{{ categoryBreakdown.length }}</span>
                    <div class="flex items-center gap-1 text-primary">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span class="text-xs font-semibold">Jenis kerusakan</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col h-[400px]">
                    <h3 class="text-lg font-semibold text-on-surface mb-4">Tren Laporan Bulanan</h3>
                    <div class="flex-grow relative w-full h-full">
                        <VueApexCharts type="line" height="100%" :options="trendChartOptions" :series="trendChartSeries" />
                    </div>
                </div>
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col h-[400px]">
                    <h3 class="text-lg font-semibold text-on-surface mb-4">Kategori Perusakan</h3>
                    <div class="flex-grow relative w-full h-full">
                        <VueApexCharts type="bar" height="100%" :options="categoryChartOptions" :series="categoryChartSeries" />
                    </div>
                </div>
            </div>

            <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-error">flag</span>
                        Leaderboard Daftar Merah
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline-variant">
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Entitas / Lokasi</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori Utama</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Jumlah Laporan</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(entity, i) in topEntities" :key="i" class="border-b border-outline-variant/50 hover:bg-surface-container-lowest transition-colors" :class="{ 'bg-surface-container-low/50': i % 2 === 1 }">
                                <td class="py-4 px-4 flex items-center gap-3">
                                    <div class="w-10 h-10 rounded bg-surface-container flex items-center justify-center text-on-surface font-semibold">
                                        {{ entity.regency_name ? entity.regency_name.charAt(0) : '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-on-surface">{{ entity.regency_name || 'Tidak diketahui' }}</p>
                                        <p class="text-xs text-on-surface-variant">{{ entity.province_name }}</p>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-on-surface">{{ entity.category_name }}</td>
                                <td class="py-4 px-4 text-right font-semibold text-on-surface">{{ entity.report_count }}</td>
                                <td class="py-4 px-4 text-center">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold" :class="getStatusBadge(entity.avg_severity).class">
                                        {{ getStatusBadge(entity.avg_severity).text }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
