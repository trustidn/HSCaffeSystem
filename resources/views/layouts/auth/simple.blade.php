<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&family=space-grotesk:400,500,600,700" rel="stylesheet" />
        <style>
            body { font-family: 'Inter', system-ui, sans-serif; }
            .font-display { font-family: 'Space Grotesk', 'Inter', system-ui, sans-serif; }
            .mesh-bg {
                background-color: #0c0a1a;
                background-image:
                    radial-gradient(at 20% 20%, rgba(99, 102, 241, 0.15) 0, transparent 50%),
                    radial-gradient(at 80% 10%, rgba(139, 92, 246, 0.12) 0, transparent 50%),
                    radial-gradient(at 50% 60%, rgba(14, 165, 233, 0.08) 0, transparent 50%),
                    radial-gradient(at 90% 80%, rgba(168, 85, 247, 0.1) 0, transparent 50%),
                    radial-gradient(at 10% 90%, rgba(59, 130, 246, 0.08) 0, transparent 50%);
            }
            .noise-overlay::before {
                content: '';
                position: fixed;
                inset: 0;
                z-index: 0;
                opacity: 0.03;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
                pointer-events: none;
            }
            @keyframes orbit1 { 0% { transform: translate(0, 0) scale(1); } 33% { transform: translate(60px, -40px) scale(1.1); } 66% { transform: translate(-30px, 30px) scale(0.95); } 100% { transform: translate(0, 0) scale(1); } }
            @keyframes orbit2 { 0% { transform: translate(0, 0) scale(1); } 33% { transform: translate(-40px, 60px) scale(1.05); } 66% { transform: translate(40px, -20px) scale(0.9); } 100% { transform: translate(0, 0) scale(1); } }
            .orb-1 { animation: orbit1 20s ease-in-out infinite; }
            .orb-2 { animation: orbit2 25s ease-in-out infinite; }

            /* Override Flux & Tailwind colors for dark mesh background */
            .auth-card { color: #e4e4e7; }
            .auth-card h1, .auth-card h2, .auth-card h3,
            .auth-card [data-flux-heading] { color: #ffffff !important; }
            .auth-card [data-flux-subheading],
            .auth-card p { color: #a1a1aa !important; }
            .auth-card label,
            .auth-card [data-flux-label] { color: #d4d4d8 !important; }
            .auth-card a:not([class*="bg-"]) { color: #a5b4fc !important; }
            .auth-card a:not([class*="bg-"]):hover { color: #c7d2fe !important; }
            .auth-card input[type="email"],
            .auth-card input[type="password"],
            .auth-card input[type="text"] {
                background-color: rgba(255, 255, 255, 0.06) !important;
                border-color: rgba(255, 255, 255, 0.12) !important;
                color: #ffffff !important;
            }
            .auth-card input::placeholder { color: #71717a !important; }
            .auth-card input:focus {
                border-color: rgba(129, 140, 248, 0.5) !important;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
            }
            .auth-card [data-flux-checkbox] { border-color: rgba(255, 255, 255, 0.2) !important; }
            .auth-card .text-zinc-600,
            .auth-card .dark\:text-zinc-400,
            .auth-card .text-zinc-400 { color: #a1a1aa !important; }
            .auth-card [data-flux-button][data-variant="primary"] {
                background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
                border: none !important;
                box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
            }
            .auth-card [data-flux-button][data-variant="primary"]:hover {
                filter: brightness(1.1) !important;
                box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
            }
        </style>
    </head>
    <body class="mesh-bg noise-overlay min-h-screen text-zinc-200 antialiased">
        {{-- Animated orbs --}}
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="orb-1 absolute -left-32 top-1/4 h-[400px] w-[400px] rounded-full bg-indigo-600/[0.10] blur-[120px]"></div>
            <div class="orb-2 absolute -right-32 bottom-1/4 h-[350px] w-[350px] rounded-full bg-violet-500/[0.08] blur-[120px]"></div>
        </div>

        <div class="relative z-10 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                {{-- Logo --}}
                @php
                    $customLogo = \App\Models\SystemSetting::get('site_logo');
                    $customName = \App\Models\SystemSetting::get('site_name', config('app.name', 'HsCaffeSystem'));
                @endphp
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 font-medium mb-2" wire:navigate>
                    @if ($customLogo)
                        <img src="{{ Storage::url($customLogo) }}" alt="{{ $customName }}" class="h-12 w-12 rounded-xl object-contain" />
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 shadow-lg shadow-violet-500/30">
                            <x-app-logo-icon class="size-7 fill-current text-white" />
                        </div>
                    @endif
                    <span class="font-display text-lg font-semibold tracking-tight text-white">{{ $customName }}</span>
                </a>

                <div class="auth-card flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
