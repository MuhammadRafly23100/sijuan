<x-layout title="Masuk">
    <div class="mx-auto mt-10 max-w-sm">
        <h1 class="text-2xl font-semibold tracking-tight text-teal-800">SIJUAN</h1>
        <p class="mt-1 text-sm text-slate-600">Sistem Pengajuan: cuti, reimbursement, dan surat keterangan kerja.</p>

        <form method="POST" action="{{ route('login') }}" class="card mt-6 space-y-4 p-6">
            @csrf

            <x-field name="email" label="Email">
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            </x-field>

            <x-field name="password" label="Kata sandi">
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </x-field>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 accent-teal-700">
                Ingat saya
            </label>

            <button class="btn btn-primary w-full">Masuk</button>
        </form>

        @env('local')
            <p class="mt-4 text-xs text-slate-500">
                Akun demo dibuat oleh seeder (<code>php artisan migrate:fresh --seed</code>). Daftar akun ada di README.
            </p>
        @endenv
    </div>
</x-layout>
