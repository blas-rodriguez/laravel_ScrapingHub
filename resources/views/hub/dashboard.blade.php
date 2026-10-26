@extends('layouts.app')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">ELEGÍ LA HERRAMIENTA PARA CADA DATO</div><h1>Tu próximo dato empieza acá<span>.</span></h1><p>Tres módulos independientes. Un espacio para configurar, ejecutar y comparar.</p></div>
    <span class="outline-tag">✦ Scraping Hub</span>
</div>
<div class="stats-grid">
    <div class="stat"><span class="stat-icon blue">↗</span><div><small>Extracciones totales</small><strong>{{ $total }}</strong></div><span class="stat-detail">Historial local</span></div>
    <div class="stat"><span class="stat-icon green">✓</span><div><small>Completadas</small><strong>{{ $completed }}</strong></div><span class="stat-detail">Demo y API real</span></div>
    <div class="stat"><span class="stat-icon amber">⌘</span><div><small>Ejecuciones API real</small><strong>{{ $live }}</strong></div><span class="stat-detail">Separadas de la demo</span></div>
</div>
<div class="module-grid">
@foreach($providers as $module)
    <a class="module-card {{ $module->value }}" href="{{ route('modules.show', $module) }}">
        <div class="module-card-top"><span class="module-icon">{{ match($module->value) {'firecrawl' => '↯', 'browse-ai' => '▤', default => '✧'} }}</span><span class="api-version">API v2</span></div>
        <h2>{{ $module->label() }}</h2><p>{{ $module->description() }}</p>
        <div class="module-card-capability">{{ match($module->value) {'firecrawl' => 'Markdown · JSON · Links', 'browse-ai' => 'Robots · Tareas · Tablas', default => 'Prompt · Schema · JSON'} }}</div>
        <div class="module-card-footer"><span>{{ $counts[$module->value] ?? 0 }} ejecuciones</span><strong>Abrir módulo ↗</strong></div>
    </a>
@endforeach
</div>
<section class="workflow-banner"><div><span class="tiny-tag">DEMO SIN CREDENCIALES</span><h2>Probá el flujo antes de conectar una API.</h2><p>El modo demo usa datos simulados y no visita la URL. API real requiere tus claves y puede consumir créditos del proveedor.</p></div><div class="workflow-path"><span>Configurar</span><i>→</i><span>Encolar</span><i>→</i><span>Exportar</span></div></section>
<section class="panel history" id="historial"><div class="panel-heading"><div><span class="section-number">01</span><h2>Actividad reciente</h2></div><span class="count-tag">{{ $total }} ejecuciones</span></div>@include('hub.history')</section>
<div class="info-grid"><div><span class="eyebrow">TRES MÓDULOS REALES</span><h3>Una integración por proveedor</h3><p>Firecrawl entrega contenido de una página; Browse AI ejecuta robots entrenados; ScrapeGraphAI extrae datos con instrucciones en lenguaje natural.</p></div><div><span class="eyebrow">CONFIGURACIÓN LOCAL</span><h3>Tus credenciales, en tu entorno</h3><p>Las API keys se cargan en <code>.env</code>. Las pantallas muestran si están configuradas, nunca su valor. Cada ejecución conserva el módulo y el modo elegidos.</p></div></div>
@endsection