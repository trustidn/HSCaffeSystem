@use('Illuminate\Support\Facades\Storage')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName ?? 'HsCaffeSystem' }} - Sistem Manajemen Cafe Multi-Tenant</title>
    @if (! empty($siteFavicon))
        <link rel="icon" href="{{ Storage::url($siteFavicon) }}" type="image/png">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&family=space-grotesk:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .font-display { font-family: 'Space Grotesk', 'Inter', system-ui, sans-serif; }
        .gradient-text {
            background: linear-gradient(135deg, #a5b4fc 0%, #818cf8 30%, #c084fc 70%, #e879f9 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .card-hover { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-6px); }

        /* Animated mesh gradient background */
        .mesh-bg {
            background-color: #0c0a1a;
            background-image:
                radial-gradient(at 20% 20%, rgba(99, 102, 241, 0.15) 0, transparent 50%),
                radial-gradient(at 80% 10%, rgba(139, 92, 246, 0.12) 0, transparent 50%),
                radial-gradient(at 50% 60%, rgba(14, 165, 233, 0.08) 0, transparent 50%),
                radial-gradient(at 90% 80%, rgba(168, 85, 247, 0.1) 0, transparent 50%),
                radial-gradient(at 10% 90%, rgba(59, 130, 246, 0.08) 0, transparent 50%);
        }

        /* Animated noise overlay */
        .noise-overlay::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        /* Animated gradient orbs */
        @keyframes orbit1 { 0% { transform: translate(0, 0) scale(1); } 33% { transform: translate(80px, -60px) scale(1.1); } 66% { transform: translate(-40px, 40px) scale(0.95); } 100% { transform: translate(0, 0) scale(1); } }
        @keyframes orbit2 { 0% { transform: translate(0, 0) scale(1); } 33% { transform: translate(-60px, 80px) scale(1.05); } 66% { transform: translate(50px, -30px) scale(0.9); } 100% { transform: translate(0, 0) scale(1); } }
        @keyframes orbit3 { 0% { transform: translate(0, 0) scale(1); } 33% { transform: translate(40px, 50px) scale(1.15); } 66% { transform: translate(-70px, -40px) scale(0.95); } 100% { transform: translate(0, 0) scale(1); } }
        .orb-1 { animation: orbit1 20s ease-in-out infinite; }
        .orb-2 { animation: orbit2 25s ease-in-out infinite; }
        .orb-3 { animation: orbit3 18s ease-in-out infinite; }

        /* Section dividers */
        .section-glow { position: relative; }
        .section-glow::before {
            content: '';
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.3), rgba(99, 102, 241, 0.3), transparent);
        }
    </style>
</head>
<body class="mesh-bg noise-overlay min-h-screen text-zinc-200 antialiased">

    {{-- Navigation --}}
    <nav class="sticky top-0 z-50 border-b border-white/[0.04] bg-[#0c0a1a]/60 backdrop-blur-2xl backdrop-saturate-150">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                @if (! empty($siteLogo))
                    <img src="{{ Storage::url($siteLogo) }}" alt="{{ $siteName ?? 'HsCaffeSystem' }}" class="h-9 w-9 rounded-xl object-contain" />
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 text-sm font-bold text-white shadow-lg shadow-violet-500/30">HS</div>
                @endif
                <span class="font-display text-lg font-semibold tracking-tight text-white">{{ $siteName ?? 'HsCaffeSystem' }}</span>
            </a>
            <div class="flex items-center gap-3">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 px-5 py-2.5 text-sm font-medium text-white shadow-lg shadow-violet-500/25 transition-all hover:shadow-xl hover:shadow-violet-500/35 hover:brightness-110">
                            Dashboard
                            <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.06] px-5 py-2.5 text-sm font-medium text-zinc-200 backdrop-blur-sm transition-all hover:border-white/20 hover:bg-white/10 hover:text-white">
                            Masuk
                            <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative overflow-hidden">
        {{-- Animated gradient orbs --}}
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="orb-1 absolute -left-32 top-10 h-[550px] w-[550px] rounded-full bg-indigo-600/[0.12] blur-[130px]"></div>
            <div class="orb-2 absolute -right-32 top-32 h-[450px] w-[450px] rounded-full bg-violet-500/[0.10] blur-[130px]"></div>
            <div class="orb-3 absolute left-1/3 top-72 h-[350px] w-[350px] rounded-full bg-sky-500/[0.08] blur-[130px]"></div>
        </div>

        <div class="mx-auto max-w-6xl px-6 pb-32 pt-28 text-center lg:pt-44 lg:pb-40">
            {{-- Badge --}}
            <div class="inline-flex items-center gap-2.5 rounded-full border border-violet-400/20 bg-violet-400/[0.08] px-5 py-2 text-xs font-medium text-violet-300 backdrop-blur-sm">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-violet-400"></span>
                </span>
                Platform Multi-Tenant Cafe Management
            </div>

            {{-- Heading --}}
            <h1 class="font-display mx-auto mt-10 max-w-4xl text-5xl font-bold leading-[1.08] tracking-tight text-white sm:text-6xl lg:text-[5.25rem]">
                Kelola Cafe Anda<br>
                <span class="gradient-text">Lebih Cerdas</span>
            </h1>

            {{-- Description --}}
            <p class="mx-auto mt-7 max-w-2xl text-lg leading-relaxed text-zinc-400 sm:text-xl">
                {{ $siteTagline ?? 'Sistem manajemen cafe all-in-one untuk pemesanan, dapur, inventaris, dan laporan keuangan. Satu platform untuk semua cafe Anda.' }}
            </p>

            {{-- CTAs --}}
            <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                @if (! empty($whatsappNumber))
                    <a href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode('Halo, saya tertarik menggunakan ' . ($siteName ?? 'HsCaffeSystem') . ' untuk cafe saya.') }}" target="_blank" class="group inline-flex items-center gap-2.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-9 py-4 text-sm font-semibold text-white shadow-xl shadow-emerald-500/20 transition-all hover:shadow-2xl hover:shadow-emerald-500/30 hover:brightness-110">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Hubungi Kami
                        <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                @endif
                <a href="#fitur" class="inline-flex items-center gap-2 rounded-2xl border border-white/[0.08] bg-white/[0.04] px-9 py-4 text-sm font-semibold text-zinc-300 backdrop-blur-sm transition-all hover:border-white/15 hover:bg-white/[0.08] hover:text-white">
                    Lihat Fitur
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </a>
            </div>

            {{-- Stats --}}
            <div class="mx-auto mt-24 grid max-w-3xl grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/[0.06] bg-white/[0.03]">
                <div class="bg-white/[0.02] px-6 py-8 text-center backdrop-blur-sm">
                    <div class="font-display text-3xl font-bold text-white sm:text-4xl">8+</div>
                    <div class="mt-1.5 text-sm text-zinc-500">Fitur Utama</div>
                </div>
                <div class="border-x border-white/[0.06] bg-white/[0.02] px-6 py-8 text-center backdrop-blur-sm">
                    <div class="font-display text-3xl font-bold text-white sm:text-4xl">7</div>
                    <div class="mt-1.5 text-sm text-zinc-500">Peran Pengguna</div>
                </div>
                <div class="bg-white/[0.02] px-6 py-8 text-center backdrop-blur-sm">
                    <div class="font-display text-3xl font-bold text-white sm:text-4xl">24/7</div>
                    <div class="mt-1.5 text-sm text-zinc-500">Real-time</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="fitur" class="section-glow relative py-28">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="orb-2 absolute -right-20 top-1/3 h-[500px] w-[500px] rounded-full bg-indigo-500/[0.06] blur-[140px]"></div>
            <div class="orb-3 absolute -left-20 bottom-1/4 h-[400px] w-[400px] rounded-full bg-purple-500/[0.05] blur-[140px]"></div>
        </div>

        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-400/20 bg-indigo-400/[0.08] px-4 py-1.5 text-xs font-medium text-indigo-300">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>
                    Fitur Lengkap
                </div>
                <h2 class="font-display mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">Semua yang Anda Butuhkan</h2>
                <p class="mx-auto mt-4 max-w-xl text-zinc-400">Dari pesanan masuk hingga laporan keuangan, semuanya dalam satu platform terintegrasi.</p>
            </div>

            <div class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Feature 1: POS --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-emerald-400/20 hover:shadow-xl hover:shadow-emerald-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/20 to-emerald-600/10 text-emerald-400 ring-1 ring-emerald-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Point of Sale</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Kasir yang cepat dan intuitif dengan dukungan varian, modifier, catatan item, dan pembayaran.</p>
                </div>

                {{-- Feature 3: QR Code --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-amber-400/20 hover:shadow-xl hover:shadow-amber-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500/20 to-amber-600/10 text-amber-400 ring-1 ring-amber-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">QR Code Self-Order</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Pelanggan pesan langsung dari meja via QR code. Pesanan masuk ke dapur secara otomatis.</p>
                </div>

                {{-- Feature 4: Kitchen --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-red-400/20 hover:shadow-xl hover:shadow-red-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-red-500/20 to-red-600/10 text-red-400 ring-1 ring-red-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Kitchen Display</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Layar dapur real-time dengan status pesanan: Pending, Dikonfirmasi, Diproses, Siap, Disajikan.</p>
                </div>

                {{-- Feature 5: Inventory --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-violet-400/20 hover:shadow-xl hover:shadow-violet-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500/20 to-violet-600/10 text-violet-400 ring-1 ring-violet-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Manajemen Inventaris</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Kelola bahan baku, resep menu, dan pergerakan stok. Alert otomatis saat stok menipis.</p>
                </div>

                {{-- Feature 6: Reports --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-cyan-400/20 hover:shadow-xl hover:shadow-cyan-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500/20 to-cyan-600/10 text-cyan-400 ring-1 ring-cyan-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Laporan & Analisis</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Laporan penjualan harian, mingguan, bulanan. Menu terlaris dan breakdown pembayaran.</p>
                </div>

                {{-- Feature 7: Staff --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-pink-400/20 hover:shadow-xl hover:shadow-pink-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-pink-500/20 to-pink-600/10 text-pink-400 ring-1 ring-pink-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Manajemen Staff</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">7 peran berbeda: Owner, Manager, Kasir, Waiter, Kitchen, Customer, dan Super Admin.</p>
                </div>

                {{-- Feature 8: Menu & Modifier --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-teal-400/20 hover:shadow-xl hover:shadow-teal-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500/20 to-teal-600/10 text-teal-400 ring-1 ring-teal-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Menu & Modifier</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Kategori, varian (S/M/L), modifier (Extra Shot, Less Sugar), gambar default per kategori.</p>
                </div>

                {{-- Feature 9: Subscription --}}
                <div class="card-hover group rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 backdrop-blur-sm hover:border-orange-400/20 hover:shadow-xl hover:shadow-orange-500/[0.05]">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500/20 to-orange-600/10 text-orange-400 ring-1 ring-orange-400/20">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                    </div>
                    <h3 class="font-display mt-5 text-lg font-semibold text-white">Sistem Langganan</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500 group-hover:text-zinc-400">Paket langganan fleksibel: Bulanan, 3 bulan, 6 bulan, dan tahunan dengan harga custom.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing Section --}}
    @if ($plans->isNotEmpty())
    <section id="harga" class="section-glow relative py-28">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="orb-3 absolute right-1/4 top-1/3 h-[500px] w-[500px] rounded-full bg-emerald-500/[0.05] blur-[140px]"></div>
            <div class="orb-1 absolute -left-20 bottom-1/4 h-[400px] w-[400px] rounded-full bg-indigo-500/[0.04] blur-[140px]"></div>
        </div>

        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/[0.08] px-4 py-1.5 text-xs font-medium text-emerald-300">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                    Paket Harga
                </div>
                <h2 class="font-display mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">Pilih Paket yang Tepat</h2>
                <p class="mx-auto mt-4 max-w-xl text-zinc-400">Paket fleksibel sesuai kebutuhan cafe Anda. Semua paket sudah termasuk semua fitur.</p>
            </div>

            @php
                $planCount = $plans->count();
                $gridCols = match(true) {
                    $planCount === 1 => 'max-w-md mx-auto',
                    $planCount === 2 => 'max-w-3xl mx-auto grid-cols-1 sm:grid-cols-2',
                    $planCount === 3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
                };
                // Find the "best value" plan (lowest price per month)
                $bestValue = $plans->count() > 1 ? $plans->sortBy(fn ($p) => $p->pricePerMonth())->first() : null;
            @endphp

            <div class="mt-16 grid gap-6 {{ $gridCols }}">
                @foreach ($plans as $plan)
                    @php
                        $isBest = $bestValue && $plan->id === $bestValue->id;
                    @endphp
                    <div class="card-hover relative flex flex-col rounded-2xl border {{ $isBest ? 'border-violet-400/30 bg-gradient-to-br from-violet-500/[0.08] via-indigo-500/[0.04] to-transparent shadow-xl shadow-violet-500/[0.05]' : 'border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent' }} p-8 backdrop-blur-sm">
                        {{-- Best value badge --}}
                        @if ($isBest)
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-violet-500 to-indigo-500 px-4 py-1.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/25">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                                    Paling Hemat
                                </span>
                            </div>
                        @endif

                        {{-- Plan name & duration --}}
                        <div class="mb-6">
                            <h3 class="font-display text-xl font-bold text-white">{{ $plan->name }}</h3>
                            <p class="mt-1 text-sm text-zinc-500">{{ $plan->durationLabel() }}</p>
                        </div>

                        {{-- Price --}}
                        <div class="mb-6">
                            <div class="flex items-baseline gap-1">
                                <span class="font-display text-4xl font-bold tracking-tight {{ $isBest ? 'gradient-text' : 'text-white' }}">{{ $plan->formattedPrice() }}</span>
                            </div>
                            @if ($plan->duration_months > 1)
                                <p class="mt-1.5 text-sm text-zinc-500">
                                    Rp {{ number_format($plan->pricePerMonth(), 0, ',', '.') }} / bulan
                                </p>
                            @endif
                        </div>

                        {{-- Description --}}
                        @if ($plan->description)
                            <p class="mb-6 text-sm leading-relaxed text-zinc-400">{{ $plan->description }}</p>
                        @endif

                        {{-- Features --}}
                        @if ($plan->features && count($plan->features) > 0)
                            <ul class="mb-8 flex-1 space-y-3">
                                @foreach ($plan->features as $feature)
                                    <li class="flex items-start gap-3 text-sm text-zinc-400">
                                        <svg class="mt-0.5 size-4 shrink-0 {{ $isBest ? 'text-violet-400' : 'text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex-1"></div>
                        @endif

                        {{-- CTA --}}
                        @if (! empty($whatsappNumber))
                            <a href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode('Halo, saya tertarik dengan paket ' . $plan->name . ' (' . $plan->durationLabel() . ') seharga ' . $plan->formattedPrice() . ' untuk cafe saya.') }}" target="_blank" class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-xl {{ $isBest ? 'bg-gradient-to-r from-violet-500 to-indigo-500 text-white shadow-lg shadow-violet-500/20 hover:shadow-xl hover:shadow-violet-500/30 hover:brightness-110' : 'border border-white/10 bg-white/[0.06] text-zinc-200 hover:border-white/20 hover:bg-white/10 hover:text-white' }} px-6 py-3.5 text-sm font-semibold transition-all">
                                Pilih Paket
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Note --}}
            <p class="mt-10 text-center text-sm text-zinc-600">Semua harga sudah termasuk akses penuh ke seluruh fitur. Tanpa biaya tersembunyi.</p>
        </div>
    </section>
    @endif

    {{-- How It Works --}}
    <section class="section-glow relative py-28">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="orb-1 absolute left-1/4 bottom-1/3 h-[500px] w-[500px] rounded-full bg-purple-500/[0.06] blur-[140px]"></div>
        </div>

        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-purple-400/20 bg-purple-400/[0.08] px-4 py-1.5 text-xs font-medium text-purple-300">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    Cara Kerja
                </div>
                <h2 class="font-display mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">Mulai dalam 3 Langkah</h2>
            </div>

            <div class="relative mt-16 grid gap-6 lg:grid-cols-3">
                {{-- Connector line (desktop) --}}
                <div class="pointer-events-none absolute left-0 right-0 top-14 -z-10 hidden h-px bg-gradient-to-r from-transparent via-violet-500/20 to-transparent lg:block"></div>

                <div class="rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 text-center backdrop-blur-sm">
                    <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl border border-violet-400/20 bg-gradient-to-br from-indigo-500/20 via-violet-500/15 to-purple-500/10">
                        <span class="font-display text-3xl font-bold gradient-text">01</span>
                    </div>
                    <h3 class="font-display mt-6 text-lg font-semibold text-white">Hubungi Kami</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500">Hubungi via WhatsApp untuk mendaftarkan cafe Anda di platform kami.</p>
                </div>

                <div class="rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 text-center backdrop-blur-sm">
                    <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl border border-violet-400/20 bg-gradient-to-br from-indigo-500/20 via-violet-500/15 to-purple-500/10">
                        <span class="font-display text-3xl font-bold gradient-text">02</span>
                    </div>
                    <h3 class="font-display mt-6 text-lg font-semibold text-white">Atur Menu & Meja</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500">Tambahkan kategori, menu item, varian, dan modifier. Atur meja dengan QR code.</p>
                </div>

                <div class="rounded-2xl border border-white/[0.06] bg-gradient-to-br from-white/[0.04] to-transparent p-8 text-center backdrop-blur-sm">
                    <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl border border-violet-400/20 bg-gradient-to-br from-indigo-500/20 via-violet-500/15 to-purple-500/10">
                        <span class="font-display text-3xl font-bold gradient-text">03</span>
                    </div>
                    <h3 class="font-display mt-6 text-lg font-semibold text-white">Mulai Operasional</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-zinc-500">Terima pesanan dari POS, QR code, atau online. Kelola dan pantau real-time.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="section-glow">
        <div class="mx-auto max-w-6xl px-6 py-28">
            <div class="relative overflow-hidden rounded-3xl border border-white/[0.08] p-12 text-center sm:p-20">
                {{-- Rich animated background --}}
                <div class="pointer-events-none absolute inset-0 -z-10">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/25 via-violet-600/15 to-purple-600/20"></div>
                    <div class="orb-1 absolute -right-16 -top-16 h-72 w-72 rounded-full bg-indigo-500/25 blur-[100px]"></div>
                    <div class="orb-2 absolute -bottom-16 -left-16 h-72 w-72 rounded-full bg-violet-500/25 blur-[100px]"></div>
                    <div class="orb-3 absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-48 w-48 rounded-full bg-purple-400/15 blur-[80px]"></div>
                </div>

                <h2 class="font-display text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">Siap Mengelola Cafe Anda?</h2>
                <p class="mx-auto mt-4 max-w-xl text-zinc-300/80">Hubungi kami sekarang dan rasakan kemudahan mengelola cafe dengan sistem terintegrasi.</p>

                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @if (! empty($whatsappNumber))
                        <a href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode('Halo, saya tertarik menggunakan ' . ($siteName ?? 'HsCaffeSystem') . ' untuk cafe saya.') }}" target="_blank" class="group inline-flex items-center gap-2.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-9 py-4 text-sm font-semibold text-white shadow-xl shadow-emerald-500/20 transition-all hover:shadow-2xl hover:shadow-emerald-500/30 hover:brightness-110">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            Hubungi via WhatsApp
                            <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/[0.08] px-9 py-4 text-sm font-semibold text-white backdrop-blur-sm transition-all hover:border-white/25 hover:bg-white/[0.12]">
                            Masuk ke Dashboard
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-white/[0.04]">
        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-3">
                    @if (! empty($siteLogo))
                        <img src="{{ Storage::url($siteLogo) }}" alt="{{ $siteName ?? 'HsCaffeSystem' }}" class="h-7 w-7 rounded-lg object-contain" />
                    @else
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 text-xs font-bold text-white">HS</div>
                    @endif
                    <span class="font-display text-sm font-medium text-zinc-400">{{ $siteName ?? 'HsCaffeSystem' }}</span>
                </div>
                <p class="text-sm text-zinc-600">&copy; {{ date('Y') }} {{ $siteName ?? 'HsCaffeSystem' }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>
