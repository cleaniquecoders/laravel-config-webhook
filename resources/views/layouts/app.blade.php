<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Webhooks' }}</title>

    {{--
        This is the package's MINIMAL fallback layout — used only when the host
        does not point `config-webhook.ui.layout` at its own application layout.
        It must be self-contained: it cannot assume the host's Vite/asset pipeline
        (referencing the host's resources/css/app.css here would 500 on a fresh
        install). For production, set `config-webhook.ui.layout` to a layout that
        already compiles Tailwind + Flux; this CDN build is only for the bundled
        demo / first-run experience.
    --}}
    <script src="https://cdn.tailwindcss.com"></script>

    @fluxAppearance
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-white">
    {{ $slot }}
    @fluxScripts
</body>
</html>
