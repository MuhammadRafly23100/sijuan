@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    @auth
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-teal-800">SIJUAN</a>

                <nav class="flex gap-1 text-sm" aria-label="Utama">
                    @foreach ([['dashboard', 'dashboard', 'Dashboard'], ['submissions.index', 'submissions.*', 'Pengajuan']] as [$route, $pattern, $label])
                        <a href="{{ route($route) }}"
                           @class([
                               'rounded-md px-3 py-1.5 font-medium',
                               'bg-teal-50 text-teal-800' => request()->routeIs($pattern),
                               'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($pattern),
                           ])
                           @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <span class="text-slate-600">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn">Keluar</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
