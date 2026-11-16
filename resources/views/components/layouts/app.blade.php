<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'Scali Logis' }}</title>
    <script>document.documentElement.classList.toggle('dark', localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches));</script>
    @vite(['resources/css/app.css', 'resources/js/guest.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased transition-colors dark:bg-slate-950 dark:text-slate-100">{{ $slot }}</body>
</html>
