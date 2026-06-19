<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: Object,
    casesByCategory: Array,
    topRegions: Array,
    dailyTrend: Array,
});
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Dashboard Pemantauan Lingkungan</h1>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm font-medium text-gray-500">Total Artikel</div>
                        <div class="mt-2 text-3xl font-bold text-emerald-600">{{ stats.totalArticles }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm font-medium text-gray-500">Total Kasus</div>
                        <div class="mt-2 text-3xl font-bold text-orange-600">{{ stats.totalCases }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm font-medium text-gray-500">Kabupaten Terdampak</div>
                        <div class="mt-2 text-3xl font-bold text-red-600">{{ stats.totalLocations }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Kasus per Kategori</h2>
                        <div class="space-y-3">
                            <div v-for="cat in casesByCategory" :key="cat.id" class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">{{ cat.name }}</span>
                                <span class="text-sm font-semibold text-gray-900">{{ cat.cases_count }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Daerah Paling Terdampak</h2>
                        <div class="space-y-3">
                            <div v-for="region in topRegions" :key="region.id" class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">{{ region.regency_name || region.name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ region.province_name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="region.latest_urgency_score" class="px-2 py-1 text-xs rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-800': region.latest_urgency_score.level === 'rendah',
                                            'bg-yellow-100 text-yellow-800': region.latest_urgency_score.level === 'sedang',
                                            'bg-orange-100 text-orange-800': region.latest_urgency_score.level === 'tinggi',
                                            'bg-red-100 text-red-800': region.latest_urgency_score.level === 'kritis',
                                        }">
                                        {{ region.latest_urgency_score.level }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900">{{ region.cases_count }} kasus</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
