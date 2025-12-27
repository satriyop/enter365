<x-layouts.guest>
    {{-- Navigation --}}
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100 dark:bg-gray-900/80 dark:border-gray-800">
        <div class="container-lg">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">E</span>
                    </div>
                    <span class="font-bold text-xl text-gray-900 dark:text-white">Enter365</span>
                </a>

                {{-- Auth Links --}}
                <div class="flex items-center gap-3">
                    @if (Route::has('login'))
                        @auth
                            <x-ui.button href="{{ url('/dashboard') }}" variant="primary" size="sm">
                                Dashboard
                            </x-ui.button>
                        @else
                            <x-ui.button href="{{ route('login') }}" variant="ghost" size="sm">
                                Masuk
                            </x-ui.button>
                            @if (Route::has('register'))
                                <x-ui.button href="{{ route('register') }}" variant="primary" size="sm">
                                    Daftar Gratis
                                </x-ui.button>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <x-ui.section bg="white" padding="pt-32 pb-16 md:pt-40 md:pb-24">
        <div class="max-w-4xl mx-auto text-center">
            {{-- Badge --}}
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-medium mb-6 dark:bg-emerald-900/30 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Dibuat khusus untuk industri manufaktur Indonesia</span>
            </div>

            {{-- Headline --}}
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-6 leading-tight dark:text-white">
                Kelola Proyek & Keuangan Bisnis Anda dalam
                <span class="text-gradient">Satu Platform</span>
            </h1>

            {{-- Subheadline --}}
            <p class="text-lg md:text-xl text-gray-600 mb-8 max-w-2xl mx-auto dark:text-gray-400">
                Platform ERP terintegrasi untuk <strong class="text-gray-900 dark:text-white">manufaktur panel listrik</strong> dan <strong class="text-gray-900 dark:text-white">kontraktor EPC solar panel</strong>. Dari quotation hingga laporan keuangan SAK EMKM.
            </p>

            {{-- CTA Buttons --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                @if (Route::has('register'))
                    <x-ui.button href="{{ route('register') }}" variant="primary" size="lg">
                        Mulai Gratis Sekarang
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </x-ui.button>
                @endif
                <x-ui.button href="#features" variant="secondary" size="lg">
                    Pelajari Lebih Lanjut
                </x-ui.button>
            </div>

            {{-- Trust Indicators --}}
            <div class="mt-12 pt-8 border-t border-gray-100 dark:border-gray-800">
                <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Dipercaya oleh perusahaan manufaktur terkemuka</p>
                <div class="flex items-center justify-center gap-8 opacity-60">
                    <span class="text-lg font-semibold text-gray-400">Vahana</span>
                    <span class="text-lg font-semibold text-gray-400">NEX Solar</span>
                </div>
            </div>
        </div>
    </x-ui.section>

    {{-- Pain Points Section --}}
    <x-ui.section bg="gray" id="pain-points">
        <x-slot:header>
            <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide dark:text-emerald-400">Masalah yang Kami Selesaikan</span>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 dark:text-white">
                Akhiri Kekacauan Spreadsheet
            </h2>
            <p class="text-gray-600 mt-4 max-w-2xl mx-auto dark:text-gray-400">
                Kami memahami tantangan yang dihadapi bisnis manufaktur dan EPC setiap hari.
            </p>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Pain Point 1 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Spreadsheet Chaos</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">Tracking proyek, material, dan keuangan terpisah-pisah di banyak file Excel.</p>
                <p class="text-emerald-600 text-sm font-medium dark:text-emerald-400">→ Sistem terintegrasi dalam satu platform</p>
            </x-ui.card>

            {{-- Pain Point 2 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Tidak Ada Visibilitas</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">Tidak bisa melihat profitabilitas real per proyek atau work order.</p>
                <p class="text-emerald-600 text-sm font-medium dark:text-emerald-400">→ Dashboard profitabilitas real-time</p>
            </x-ui.card>

            {{-- Pain Point 3 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Inventori Menebak</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">Tidak tahu stok material yang tersedia atau kebutuhan mendatang.</p>
                <p class="text-emerald-600 text-sm font-medium dark:text-emerald-400">→ Smart MRP dengan reorder alerts</p>
            </x-ui.card>

            {{-- Pain Point 4 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container-amber mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Subkontraktor Berantakan</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">Sulit tracking biaya tenaga kerja external dan retensi pembayaran.</p>
                <p class="text-amber-600 text-sm font-medium dark:text-amber-400">→ Tracking lengkap dengan retensi otomatis</p>
            </x-ui.card>

            {{-- Pain Point 5 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container-amber mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Cash Flow Buta</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">AR/AP aging tersebar, reminder pembayaran manual.</p>
                <p class="text-amber-600 text-sm font-medium dark:text-amber-400">→ Dashboard AR/AP dengan aging otomatis</p>
            </x-ui.card>

            {{-- Pain Point 6 --}}
            <x-ui.card class="group hover:shadow-lg transition-shadow">
                <div class="icon-container mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 dark:text-white">Beban Compliance</h3>
                <p class="text-gray-600 text-sm mb-3 dark:text-gray-400">Butuh laporan keuangan SAK EMKM tapi tidak punya sistem yang mendukung.</p>
                <p class="text-emerald-600 text-sm font-medium dark:text-emerald-400">→ Laporan SAK EMKM otomatis</p>
            </x-ui.card>
        </div>
    </x-ui.section>

    {{-- Features Section --}}
    <x-ui.section bg="white" id="features">
        <x-slot:header>
            <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide dark:text-emerald-400">Fitur Unggulan</span>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 dark:text-white">
                Solusi untuk Industri Anda
            </h2>
            <p class="text-gray-600 mt-4 max-w-2xl mx-auto dark:text-gray-400">
                Dibangun khusus untuk kebutuhan manufaktur panel listrik dan kontraktor solar EPC.
            </p>
        </x-slot:header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Electrical Panel Manufacturing --}}
            <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-8 border border-amber-100 dark:from-amber-900/20 dark:to-orange-900/20 dark:border-amber-800/50">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Manufaktur Panel Listrik</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Switchboard, MCC, Switchgear</p>
                    </div>
                </div>

                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">Multi-Brand BOM</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Buat quotation dengan alternatif ABB, Siemens, Schneider dalam satu proposal</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">Work Order Tracking</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Monitor progress produksi dari cutting hingga FAT/SAT</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">IEC 61439 Compliance</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Dokumentasi standar untuk type-tested assemblies</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- Solar EPC --}}
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-8 border border-emerald-100 dark:from-emerald-900/20 dark:to-teal-900/20 dark:border-emerald-800/50">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Kontraktor Solar EPC</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Instalasi & Lease-to-Own</p>
                    </div>
                </div>

                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">Proposal Generator</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Quotation lengkap dengan proyeksi penghematan energi dan ROI</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">ESG Metrics</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Kalkulasi CO₂ reduction dan sustainability impact untuk laporan ESG klien</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong class="text-gray-900 dark:text-white">Lease Management</strong>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Kelola kontrak lease-to-own dengan pembayaran otomatis</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Core Features --}}
        <div class="mt-16 pt-16 border-t border-gray-100 dark:border-gray-800">
            <h3 class="text-2xl font-bold text-center text-gray-900 mb-8 dark:text-white">Fitur Inti untuk Semua Industri</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="icon-container mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Quotation</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Profesional & cepat</p>
                </div>
                <div class="text-center">
                    <div class="icon-container mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Inventory & MRP</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Smart reorder</p>
                </div>
                <div class="text-center">
                    <div class="icon-container mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Akuntansi</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">SAK EMKM ready</p>
                </div>
                <div class="text-center">
                    <div class="icon-container mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Reporting</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Insight real-time</p>
                </div>
            </div>
        </div>
    </x-ui.section>

    {{-- CTA Section --}}
    <x-ui.section bg="primary" padding="py-16 md:py-20">
        <div class="text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                Siap Mengubah Cara Anda Mengelola Bisnis?
            </h2>
            <p class="text-emerald-100 text-lg mb-8 max-w-2xl mx-auto">
                Bergabung dengan perusahaan manufaktur Indonesia yang sudah menggunakan Enter365 untuk meningkatkan efisiensi operasional.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 text-lg font-semibold rounded-lg bg-white text-emerald-600 hover:bg-gray-100 transition-colors shadow-lg">
                        Daftar Gratis Sekarang
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @endif
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 text-lg font-semibold rounded-lg border-2 border-white text-white hover:bg-white/10 transition-colors">
                        Sudah Punya Akun? Masuk
                    </a>
                @endif
            </div>
        </div>
    </x-ui.section>

    {{-- Footer --}}
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="container-lg">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">E</span>
                    </div>
                    <span class="font-bold text-xl text-white">Enter365</span>
                </div>
                <p class="text-sm">
                    &copy; {{ date('Y') }} Enter365. Dibuat untuk industri manufaktur Indonesia.
                </p>
            </div>
        </div>
    </footer>
</x-layouts.guest>
