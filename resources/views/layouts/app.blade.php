<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Workspace') · Scraping Hub</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Saltar al contenido</a>
<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-icon">s<span>↗</span></span>scraping<span class="brand-light">hub</span><span class="brand-dot">.</span></a>
    <div class="workspace-label">TU ESPACIO DE TRABAJO</div>
    <nav aria-label="Navegación principal">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span> Vista general</a>
        <div class="nav-caption">MÓDULOS DE EXTRACCIÓN</div>
        @foreach(\App\ScrapingProvider::cases() as $module)
            <a class="nav-link {{ ($provider ?? null) === $module ? 'active' : '' }}" href="{{ route('modules.show', $module) }}"><span class="provider-dot {{ $module->value }}"></span>{{ $module->label() }}<span class="nav-arrow">↗</span></a>
        @endforeach
    </nav>
    <div class="sidebar-note"><span class="tiny-tag">THREE TOOLS. ONE WORKSPACE.</span><p>Tres maneras de<br>entender la web.</p><div class="stack-tags"><span>Laravel 13</span><span>Cloud APIs</span></div></div>
    <div class="sidebar-footer"><span class="avatar">BR</span><div><strong>Portfolio workspace</strong><small>Blas Rodríguez · Local demo</small></div><span class="online-dot"></span></div>
</aside>
<div class="app-shell">
    <header class="topbar"><div><a class="breadcrumb" href="{{ route('dashboard') }}">Workspace</a><span class="slash">/</span>@yield('breadcrumb', 'Vista general')</div><div class="environment"><span class="online-dot"></span> Entorno local <span class="version">v1.0</span></div></header>
    <main id="main">
        @if(session('message'))<div class="notice success" role="status">{{ session('message') }}</div>@endif
        @yield('content')
        <footer class="page-footer"><span>Laravel + Firecrawl + Browse AI + ScrapeGraphAI</span><span>Un flujo claro. Tres motores.</span></footer>
    </main>
</div>
</body>
</html>