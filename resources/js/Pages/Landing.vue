<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const miniMapContainer = ref(null);

onMounted(() => {
    if (miniMapContainer.value) {
        const map = L.map(miniMapContainer.value, {
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            touchZoom: false
        }).setView([-0.7893, 113.9213], 3);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);
    }
});

const features = ref([
    {
        icon: 'map',
        title: 'Peta Interaktif Geospasial',
        desc: 'Visualisasikan tingkat kerawanan dan urgensi lingkungan di 38 Provinsi serta 514 Kabupaten/Kota di seluruh Indonesia.',
        link: '/dashboard',
        color: 'text-primary bg-primary/10'
    },
    {
        icon: 'analytics',
        title: 'Analisis & Statistik Mendalam',
        desc: 'Pantau grafik perkembangan kasus lingkungan, tren skor risiko, dan metrik krusial lainnya secara real-time.',
        link: '/analytics',
        color: 'text-secondary bg-secondary/10'
    },
    {
        icon: 'archive',
        title: 'Arsip Laporan Terpusat',
        desc: 'Akses riwayat kasus lingkungan terstruktur, laporan wilayah, dan dokumentasi historis secara lengkap.',
        link: '/archive',
        color: 'text-tertiary bg-tertiary/10'
    },
    {
        icon: 'smart_toy',
        title: 'AI Asisten Lingkungan',
        desc: 'Ajukan pertanyaan dan dapatkan ringkasan data wilayah instan melalui chatbot kecerdasan buatan terintegrasi.',
        link: '/chat',
        color: 'text-primary bg-primary-fixed/20'
    }
]);

const stats = ref([
    { number: '38', label: 'Provinsi Terpetakan' },
    { number: '514', label: 'Kabupaten/Kota' },
    { number: '24/7', label: 'AI Monitoring' }
]);

const challenges = ref([
    { icon: 'newspaper', title: 'Berita Terkubur', desc: 'Kasus nyata di pelosok sering dimuat media lokal, namun luput dari tingkat nasional.', color: 'text-error bg-error/10' },
    { icon: 'dangerous', title: 'Ancaman Hoaks', desc: 'Informasi palsu memicu tindakan keliru, membuang waktu, biaya, dan berisiko hukum.', color: 'text-error bg-error/10' },
    { icon: 'hourglass_empty', title: 'Proses Manual Lambat', desc: 'Menyortir ratusan berita tiap hari secara manual menyita waktu dan menunda penanganan.', color: 'text-error bg-error/10' }
]);
</script>

<template>
    <AppLayout title="Nalabumi - Solusi Monitoring Lingkungan Geospasial">
        <div class="text-on-surface overflow-x-hidden">
            <!-- HERO FULL HEIGHT SECTION -->
            <div class="bg-background relative flex flex-col justify-center min-h-[calc(100vh-4rem)] py-12">
                <!-- Decorative blur objects -->
                <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute top-1/2 -left-48 w-[400px] h-[400px] bg-secondary/8 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-20 right-1/4 w-[350px] h-[350px] bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Hero Section -->
                <div class="max-w-7xl mx-auto px-6 md:px-12 w-full grid md:grid-cols-12 gap-12 items-center relative z-10">
                <div class="md:col-span-7 flex flex-col justify-center text-left relative z-10">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 self-start px-3 py-1.5 rounded-full bg-primary/8 border border-primary/20 text-xs font-bold text-primary uppercase tracking-wider mb-6 animate-fade-in">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        Platform Geospasial Lingkungan Terintegrasi
                    </div>

                    <!-- Heading -->
                    <h1 class="text-4xl md:text-6xl font-extrabold text-on-surface tracking-tight leading-none mb-6">
                        Menjaga Kelestarian <br />
                        <span class="text-primary bg-gradient-to-r from-primary to-primary-container bg-clip-text text-transparent">Bumi Indonesia</span>
                    </h1>

                    <!-- Description -->
                    <p class="text-lg text-on-surface-variant leading-relaxed mb-8 max-w-2xl">
                        Nalabumi menghadirkan analisis data lingkungan yang cerdas, visualisasi geospasial real-time, dan asisten AI interaktif untuk mendeteksi kerentanan wilayah di seluruh pelosok nusantara secara akurat.
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-wrap gap-4 items-center mb-10">
                        <Link href="/dashboard" class="px-8 py-4 bg-primary text-on-primary font-bold text-sm rounded-xl hover:bg-primary-container shadow-lg hover:shadow-primary/25 transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] filled">public</span>
                            Eksplor Peta
                        </Link>
                        <Link href="/chat" class="px-8 py-4 bg-surface border border-outline-variant/60 text-on-surface font-semibold text-sm rounded-xl hover:bg-surface-container transition-all duration-300 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">chat</span>
                            Konsultasi AI
                        </Link>
                    </div>

                    <!-- Small Brand Stat Row -->
                    <div class="grid grid-cols-3 gap-6 pt-6 border-t border-outline-variant/30">
                        <div v-for="(stat, idx) in stats" :key="idx" class="flex flex-col">
                            <span class="text-2xl md:text-3xl font-extrabold text-primary">{{ stat.number }}</span>
                            <span class="text-xs text-on-surface-variant font-medium mt-1">{{ stat.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Graphic/Visual Side -->
                <div class="md:col-span-5 relative w-full h-[320px] md:h-[450px] flex items-center justify-center">
                    <div class="w-full h-full glass-panel rounded-2xl p-6 relative flex flex-col justify-between overflow-hidden shadow-xl border border-outline-variant/40">
                        <!-- Abstract Grid Background -->
                        <div class="absolute inset-0 bg-[radial-gradient(#c3c8b9_1px,transparent_1px)] [background-size:16px_16px] opacity-20"></div>
                        
                        <!-- Header of card -->
                        <div class="flex items-center justify-between border-b border-outline-variant/40 pb-4 relative z-10">
                            <div class="flex items-center gap-2.5">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400"></div>
                            </div>
                            <span class="text-xs font-semibold text-on-surface-variant/80 tracking-wider">PREVIEW PETA</span>
                        </div>

                        <!-- Card Body (Real Leaflet Map) -->
                        <div class="flex-1 w-full flex flex-col items-center justify-center py-4 relative z-10">
                            <!-- Real Leaflet Mini Map -->
                            <div ref="miniMapContainer" class="w-full h-full rounded-xl overflow-hidden border border-primary/20 shadow-inner opacity-90" style="min-height: 220px;"></div>
                        </div>

                        <!-- Footer/Data Row of card -->
                        <div class="bg-surface-container/60 border border-outline-variant/35 rounded-xl p-3 flex justify-between items-center relative z-10">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary text-[20px]">radar</span>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-on-surface">Pemantauan Berkala</span>
                                    <span class="text-[10px] text-on-surface-variant">Berita Nasional & Internasional</span>
                                </div>
                            </div>
                            <div class="px-2.5 py-1 bg-primary-fixed/20 text-on-primary-fixed-variant rounded-md text-[10px] font-extrabold uppercase tracking-wider animate-pulse">
                                LIVE
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Tantangan Section -->
            <div class="border-t border-outline-variant/30 py-16 relative bg-surface-container-lowest">
                <div class="max-w-7xl mx-auto px-6 md:px-12">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl font-bold text-on-surface">Tantangan yang Kami Hadapi</h2>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-6">
                        <div v-for="(challenge, idx) in challenges" :key="idx" class="glass-panel hover:bg-surface rounded-xl p-6 transition-all duration-300 hover:shadow-lg border border-outline-variant/30 flex flex-col items-center text-center group hover:-translate-y-1">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300" :class="challenge.color">
                                <span class="material-symbols-outlined text-[24px] filled">{{ challenge.icon }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-on-surface mb-2.5">{{ challenge.title }}</h3>
                            <p class="text-xs text-on-surface-variant leading-relaxed">{{ challenge.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Grid Section -->
            <div class="bg-surface-container-low border-t border-outline-variant/40 py-16">
                <div class="max-w-7xl mx-auto px-6 md:px-12">
                    <div class="text-center max-w-2xl mx-auto mb-12">
                        <h2 class="text-3xl font-bold text-on-surface mb-4">Fitur Utama Nalabumi</h2>
                        <p class="text-on-surface-variant text-sm">
                            Platform serba ada untuk memantau, mendokumentasikan, dan menganalisis metrik kualitas lingkungan hidup di seluruh Indonesia.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="(feat, idx) in features" :key="idx" class="glass-panel hover:bg-surface rounded-xl p-6 transition-all duration-300 hover:shadow-lg border border-outline-variant/30 flex flex-col justify-between group hover:-translate-y-1">
                            <div>
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300" :class="feat.color">
                                    <span class="material-symbols-outlined text-[24px] filled">{{ feat.icon }}</span>
                                </div>
                                <h3 class="text-lg font-bold text-on-surface mb-2.5">{{ feat.title }}</h3>
                                <p class="text-xs text-on-surface-variant leading-relaxed">{{ feat.desc }}</p>
                            </div>
                            <Link :href="feat.link" class="mt-6 text-xs font-bold text-primary flex items-center gap-1 hover:gap-2 transition-all">
                                Buka Fitur
                                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Call to Action -->
            <div class="border-t border-outline-variant/30 py-12 bg-surface text-center">
                <div class="max-w-4xl mx-auto px-6">
                    <h3 class="text-xl md:text-2xl font-bold text-on-surface mb-3">Siap untuk memantau kelestarian bumi kita?</h3>
                    <p class="text-xs text-on-surface-variant mb-6 max-w-md mx-auto">
                        Mulai jelajahi data lingkungan, lihat tingkat kerentanan daerahmu, dan berkontribusi untuk pelestarian masa depan.
                    </p>
                    <Link href="/dashboard" class="px-8 py-3.5 bg-primary text-on-primary font-bold text-xs rounded-xl hover:bg-primary-container transition-colors inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">rocket_launch</span>
                        Masuk ke Dashboard Peta
                    </Link>
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-surface-container-highest/40 border-t border-outline-variant/20 py-6 text-center text-xs text-on-surface-variant/80">
                <p>&copy; 2026 Nalabumi AI. Hak Cipta Dilindungi. Dibuat dengan cinta untuk Bumi Indonesia.</p>
            </footer>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fadeIn 0.6s ease-out forwards;
}
</style>
