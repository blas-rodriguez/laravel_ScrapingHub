@extends('layouts.app')
@section('title', $provider->label())
@section('breadcrumb', $provider->label())
@section('content')
<a class="back-link" href="{{ route('dashboard') }}">← Volver a los módulos</a>
<div class="page-heading"><div><div class="eyebrow">MÓDULO DE EXTRACCIÓN · API V2</div><h1>{{ $provider->label() }}<span>.</span></h1><p>{{ $provider->description() }}</p></div><span class="config-badge {{ $provider->configured() ? 'ready' : '' }}">{{ $provider->configured() ? '✓ API key configurada' : '○ API key pendiente' }}</span></div>
<div class="workbench">
<section class="panel"><div class="panel-heading"><div><span class="section-number">01</span><h2>Nueva extracción</h2></div><span class="muted small">{{ $provider->label() }}</span></div>
@if($errors->any())<div class="notice error" role="alert"><strong>Revisá la configuración</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('extractions.store', $provider) }}" method="post" id="extraction-form">
    @csrf
    <label>Modo de ejecución<select name="mode" id="mode" class="full-select"><option value="demo" @selected(old('mode', 'demo') === 'demo')>Demo local · Datos simulados</option><option value="live" @selected(old('mode') === 'live')>API real · Usa las credenciales de .env</option></select></label>
    <div id="mode-note" class="mode-note" role="status">Demo: datos simulados. No se contacta a la API ni se visita la URL.</div>
    <div class="form-grid">
        <label class="full">Nombre<input id="name" name="name" maxlength="100" required value="{{ old('name', $provider->label().' · Catálogo de libros') }}"></label>
        <label class="full">URL de origen<input id="url" name="url" type="url" maxlength="2000" required value="{{ old('url', 'https://books.toscrape.com/') }}"><small>Una página pública HTTP(S). En modo demo la URL es solo una referencia.</small></label>
        @if($provider === \App\ScrapingProvider::Firecrawl)
            <label class="full">Formato de salida<select id="format" name="format" class="full-select"><option value="markdown" @selected(old('format', 'markdown') === 'markdown')>Markdown + enlaces</option><option value="json" @selected(old('format') === 'json')>JSON estructurado con instrucciones</option></select></label>
        @endif
        @if($provider !== \App\ScrapingProvider::BrowseAi)
            <div class="full" id="json-options" data-firecrawl="{{ $provider === \App\ScrapingProvider::Firecrawl ? '1' : '0' }}">
                <label>Qué datos querés extraer<textarea id="prompt" name="prompt" rows="4" maxlength="4000" class="text-editor">{{ old('prompt', 'Extraé los títulos y precios de los libros visibles en esta página. Devolvé una lista de productos, sin inventar datos.') }}</textarea><small>Las instrucciones guían la extracción real. La demo siempre devuelve los mismos ejemplos.</small></label>
                <details class="schema-details" @if(old('schema_json')) open @endif><summary>Schema JSON opcional</summary><label class="sr-only" for="schema_json">Schema JSON</label><textarea id="schema_json" name="schema_json" class="code-editor" rows="9" maxlength="10000" spellcheck="false" placeholder='{"type":"object","properties":{"titulo":{"type":"string"}}}'>{{ old('schema_json') }}</textarea><p class="small muted">Usá un objeto con <code>type: object</code> y <code>properties</code>. El proveedor valida el schema completo.</p></details>
            </div>
        @else
            <label class="full">Robot ID<input id="robot_id" name="robot_id" maxlength="150" value="{{ old('robot_id', config('integrations.providers.browse-ai.robot_id')) }}" placeholder="ID del robot entrenado en Browse AI"><small>Obligatorio en API real. El entrenamiento se realiza en la plataforma de Browse AI.</small></label>
            <label class="full">Parámetro que recibe la URL<input id="url_parameter" name="url_parameter" required maxlength="80" value="{{ old('url_parameter', config('integrations.providers.browse-ai.url_parameter')) }}"><small>Copiá el nombre desde Integrate → Rest API del robot, por ejemplo <code>originUrl</code>.</small></label>
            <label class="full">Parámetros adicionales <span class="code-tag">JSON</span><textarea id="input_parameters_json" name="input_parameters_json" class="code-editor" rows="5" maxlength="10000" spellcheck="false">{{ old('input_parameters_json', '{}') }}</textarea><small>Un objeto JSON. El parámetro de URL se completa automáticamente con la URL de origen. No pegues contraseñas ni API keys.</small></label>
        @endif
    </div>
    <div class="form-bottom"><span class="small muted">Procesamiento en segundo plano</span><button class="button primary" id="submit-button" type="submit">Iniciar extracción <span>↗</span></button></div>
</form>
</section>
<aside class="guide-column"><section class="dark-card"><span class="tiny-tag">{{ strtoupper($provider->label()) }} MODULE</span><h2>Una herramienta.<br>Un propósito claro.</h2><p>{{ $provider->description() }}</p><div class="module-use-case">{{ match($provider->value) {'firecrawl' => 'Ideal para contenido limpio, documentos y extracción por página.', 'browse-ai' => 'Ideal para repetir una extracción que ya entrenaste en un robot.', default => 'Ideal para pedir campos con lenguaje natural y un schema de salida.'} }}</div><div class="dark-card-footer"><span class="online-dot"></span> Historial independiente para este módulo.</div></section>
<section class="panel guide"><div class="eyebrow">ANTES DE USAR API REAL</div><h2>Conectá tu cuenta</h2><p class="guide-copy">{{ $provider->configured() ? 'La API key está configurada localmente. Su valor no se muestra en la interfaz.' : 'Agregá la API key en .env, limpiá la configuración y reiniciá el worker.' }}</p><code class="env-variable">{{ match($provider->value) {'firecrawl' => 'FIRECRAWL_API_KEY', 'browse-ai' => 'BROWSE_AI_API_KEY', default => 'SCRAPEGRAPH_API_KEY'} }}</code><p class="guide-copy">Los envíos reales pueden consumir créditos. Un fallo de API nunca se reemplaza con resultados de demo.</p><a class="text-link" target="_blank" rel="noopener noreferrer" href="{{ match($provider->value) {'firecrawl' => 'https://docs.firecrawl.dev/api-reference/endpoint/scrape', 'browse-ai' => 'https://developers.browse.ai/', default => 'https://docs.scrapegraphai.com/api-reference/endpoint/extract'} }}">Documentación oficial ↗</a></section></aside>
</div>
<section class="panel history"><div class="panel-heading"><div><span class="section-number">02</span><h2>Historial · {{ $provider->label() }}</h2></div><span class="count-tag">{{ $extractions->total() }} ejecuciones</span></div>@include('hub.history')</section>
@endsection