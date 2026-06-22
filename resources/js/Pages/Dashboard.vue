<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    stats: Object,
    casesByCategory: Array,
    topRegions: Array,
    dailyTrend: Array,
});

const trendChartOptions = {
    chart: {
        type: 'area',
        height: 300,
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
        categories: props.dailyTrend.map(t => {
            const d = new Date(t.date);
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        }),
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
    name: 'Kasus',
    data: props.dailyTrend.map(t => t.count),
}];

const categoryChartOptions = {
    chart: {
        type: 'bar',
        height: 300,
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
        categories: props.casesByCategory.map(c => c.name),
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
    name: 'Kasus',
    data: props.casesByCategory.map(c => c.cases_count),
}];
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="py-8 px-6 md:px-10 max-w-[1440px] mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-semibold text-on-surface">Dashboard Pemantauan Lingkungan</h1>
                <p class="text-base text-on-surface-variant mt-1">Tinjauan komprehensif terhadap data perusakan lingkungan.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Artikel</span>
                        <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-full">description</span>
                    </div>
                    <span class="text-4xl font-bold text-on-surface">{{ stats.totalArticles }}</span>
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
                    <span class="text-4xl font-bold text-on-surface">{{ stats.totalCases }}</span>
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
                    <span class="text-4xl font-bold text-on-surface">{{ stats.totalLocations }}</span>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-2 bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-on-surface mb-4">Tren Kasus Harian</h3>
                    <VueApexCharts type="area" height="300" :options="trendChartOptions" :series="trendChartSeries" />
                </div>
                <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-on-surface mb-4">Kasus per Kategori</h3>
                    <VueApexCharts type="bar" height="300" :options="categoryChartOptions" :series="categoryChartSeries" />
                </div>
            </div>

            <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-error">flag</span>
                        Daerah Paling Terdampak
                    </h3>
                    <Link href="/map" class="text-primary text-xs font-semibold hover:underline">Lihat Peta</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline-variant">
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Wilayah</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Provinsi</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Jumlah Kasus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="region in topRegions" :key="region.id" class="border-b border-outline-variant/50 hover:bg-surface-container-lowest transition-colors">
                                <td class="py-4 px-4 font-medium text-on-surface">{{ region.regency_name || region.name }}</td>
                                <td class="py-4 px-4 text-on-surface-variant">{{ region.province_name }}</td>
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
                                <td class="py-4 px-4 text-right font-semibold text-on-surface">{{ region.cases_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
