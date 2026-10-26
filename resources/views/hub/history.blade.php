@if($extractions->isEmpty())
    <div class="empty-state"><span class="empty-icon">⌘</span><h3>Tu primera extracción empieza acá</h3><p>Elegí un módulo y probá el modo demo.<br>Las ejecuciones van a aparecer en este historial.</p></div>
@else
    <div class="table-scroll"><table><thead><tr><th>Extracción</th><th>Módulo</th><th>Modo</th><th>Estado</th><th>Filas</th><th>Creada</th><th><span class="sr-only">Detalle</span></th></tr></thead><tbody>
    @foreach($extractions as $item)
        <tr><td><a class="row-title" href="{{ route('extractions.show', [$item->provider, $item]) }}">{{ $item->name }}</a><small class="table-url">{{ parse_url($item->url, PHP_URL_HOST) }}</small></td><td><span class="provider-text {{ $item->provider->value }}">{{ $item->provider->label() }}</span></td><td><span class="mode-badge {{ $item->mode }}">{{ $item->mode === 'demo' ? 'Demo · Simulado' : 'API real' }}</span></td><td><span class="badge {{ $item->status }}">{{ $item->statusLabel() }}</span></td><td class="mono">{{ count($item->records ?? []) }}</td><td class="muted">{{ $item->created_at->timezone('America/Argentina/Buenos_Aires')->format('d/m H:i') }}</td><td><a class="text-link" href="{{ route('extractions.show', [$item->provider, $item]) }}">Ver ↗</a></td></tr>
    @endforeach
    </tbody></table></div>
    @if($extractions->hasPages())<div class="pagination">@if($extractions->previousPageUrl())<a href="{{ $extractions->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $extractions->currentPage() }} de {{ $extractions->lastPage() }}</span>@if($extractions->nextPageUrl())<a href="{{ $extractions->nextPageUrl() }}">Siguiente →</a>@endif</div>@endif
@endif