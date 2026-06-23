<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    // Dashboard (overview) data
    overview: Object,
    casesByCategory: Array,
    topRegions: Array,
    dailyTrend: Array,
});

// --- Daily Trend Chart ---
const dailyTrendChartOptions = {
    chart: {
        type: 'area',
        height: 280,
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
    stroke: { curve: 'smooth', width: 2 },
    xaxis: {
        categories: props.dailyTrend.map(t => {
            const d = new Date(t.date);
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        }),
        labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' } },
    },
    grid: { borderColor: '#e2e3da', strokeDashArray: 3 },
    tooltip: { style: { fontFamily: 'Inter, sans-serif' } },
};

const dailyTrendChartSeries = [{
    name: 'Kasus',
    data: props.dailyTrend.map(t => t.count),
}];

// --- Category Chart ---
const overviewCategoryChartOptions = {
    chart: {
        type: 'bar',
        height: 280,
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
    },
    colors: ['#ba1a1a', '#a96300', '#745b00', '#ffb870', '#74796b'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
    xaxis: {
        categories: props.casesByCategory.map(c => c.name),
        labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '10px', colors: '#43493d' }, rotate: -45 },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#43493d' } },
    },
    grid: { borderColor: '#e2e3da', strokeDashArray: 3 },
    legend: { show: false },
    tooltip: { style: { fontFamily: 'Inter, sans-serif' } },
};

const overviewCategoryChartSeries = [{
    name: 'Kasus',
    data: props.casesByCategory.map(c => c.cases_count),
}];
</script>

<template>
    <AppLayout title="Analitik">
        <div class="py-8 px-6 md:px-10 max-w-[1440px] mx-auto flex flex-col gap-10">

            <!-- ===== SECTION 1: RINGKASAN UMUM (dari Dashboard lama) ===== -->
            <div>
                <div class="mb-6">
                    <h1 class="text-3xl font-semibold text-on-surface">Analitik & Pemantauan Lingkungan</h1>
                    <p class="text-base text-on-surface-variant mt-1">Tinjauan komprehensif terhadap data perusakan lingkungan.</p>
                </div>

                <!-- Overview Stats (dari Dashboard lama) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Artikel</span>
                            <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-full">description</span>
                        </div>
                        <span class="text-4xl font-bold text-on-surface">{{ overview.totalArticles }}</span>
                        <div class="flex items-center gap-1 text-primary">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                            <span class="text-xs font-semibold">Data terkini</span>
                        </div>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Kasus</span>
                            <span class="material-symbols-outlined text-error bg-error-container/20 p-2 rounded-full">warning</span>
                        </div>
                        <span class="text-4xl font-bold text-on-surface">{{ overview.totalCases }}</span>
                        <div class="flex items-center gap-1 text-error">
                            <span class="material-symbols-outlined text-sm">flag</span>
                            <span class="text-xs font-semibold">Kasus teridentifikasi</span>
                        </div>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kabupaten Terdampak</span>
                            <span class="material-symbols-outlined text-tertiary bg-tertiary-container/20 p-2 rounded-full">public</span>
                        </div>
                        <span class="text-4xl font-bold text-on-surface">{{ overview.totalLocations }}</span>
                        <div class="flex items-center gap-1 text-on-surface-variant">
                            <span class="material-symbols-outlined text-sm">location_on</span>
                            <span class="text-xs font-semibold">Wilayah teridentifikasi</span>
                        </div>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Kategori</span>
                            <span class="material-symbols-outlined text-secondary bg-secondary-container/20 p-2 rounded-full">category</span>
                        </div>
                        <span class="text-4xl font-bold text-on-surface">{{ casesByCategory.length }}</span>
                        <div class="flex items-center gap-1 text-primary">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            <span class="text-xs font-semibold">Jenis kerusakan</span>
                        </div>
                    </div>
                </div>

                <!-- Charts dari Dashboard lama -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="lg:col-span-2 bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-on-surface mb-4">Tren Kasus Harian</h3>
                        <VueApexCharts type="area" height="280" :options="dailyTrendChartOptions" :series="dailyTrendChartSeries" />
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-on-surface mb-4">Kasus per Kategori</h3>
                        <VueApexCharts type="bar" height="280" :options="overviewCategoryChartOptions" :series="overviewCategoryChartSeries" />
                    </div>
                </div>

                <!-- Tabel Daerah Terdampak dari Dashboard lama -->
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-error">flag</span>
                            Daerah Paling Terdampak
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-outline-variant">
                                    <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider w-12 text-center">#</th>
                                    <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Wilayah</th>
                                    <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Provinsi</th>
                                    <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                                    <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Jumlah Kasus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(region, index) in topRegions" :key="region.id" 
                                    @click="router.visit(`/regency?regency=${encodeURIComponent(region.regency_name || region.name)}&province=${encodeURIComponent(region.province_name || '')}&from=analytics`)"
                                    class="border-b border-outline-variant/50 hover:bg-surface-container transition-colors cursor-pointer group">
                                    <td class="py-4 px-4 text-center">
                                        <div class="inline-flex items-center justify-center w-7 h-7 rounded-full font-bold text-xs"
                                            :class="{
                                                'bg-primary text-on-primary shadow-sm': index === 0,
                                                'bg-secondary text-on-secondary shadow-sm': index === 1,
                                                'bg-tertiary text-on-tertiary shadow-sm': index === 2,
                                                'bg-surface-variant text-on-surface-variant': index > 2
                                            }">
                                            {{ index + 1 }}
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-on-surface group-hover:text-primary transition-colors">
                                        {{ region.regency_name || region.name }}
                                    </td>
                                    <td class="py-4 px-4 text-on-surface-variant text-sm">{{ region.province_name }}</td>
                                    <td class="py-4 px-4 text-center">
                                        <span v-if="region.latest_urgency_score" class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                            :class="{
                                                'bg-primary-fixed-dim text-on-primary-fixed-variant': region.latest_urgency_score.level === 'rendah',
                                                'bg-secondary-container text-on-secondary-container': region.latest_urgency_score.level === 'sedang',
                                                'bg-tertiary-container text-on-tertiary-container': region.latest_urgency_score.level === 'tinggi',
                                                'bg-error-container text-on-error-container': region.latest_urgency_score.level === 'kritis',
                                            }">
                                            {{ region.latest_urgency_score.level }}
                                        </span>
                                        <span v-else class="text-on-surface-variant text-xs">—</span>
                                    </td>
                                    <td class="py-4 px-4 text-right font-bold text-on-surface text-lg">{{ region.cases_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

