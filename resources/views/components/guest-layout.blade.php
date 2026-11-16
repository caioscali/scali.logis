<x-layouts.app :title="$title ?? 'Acesso | Scali Logis'">
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <a href="{{ route('login') }}" class="mb-8 block text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Scali <span class="text-sky-500">Logis</span></a>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">{{ $slot }}</div>
        </div>
    </main>
</x-layouts.app>
