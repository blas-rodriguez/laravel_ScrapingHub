<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtractionRequest;
use App\Jobs\ExecuteExtraction;
use App\Models\Extraction;
use App\ScrapingProvider;
use App\Services\Scraping\SensitiveData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtractionController extends Controller
{
    public function dashboard(): View
    {
        return view('hub.dashboard', [
            'providers' => ScrapingProvider::cases(), 'total' => Extraction::count(),
            'live' => Extraction::where('mode', 'live')->count(),
            'completed' => Extraction::where('status', 'completed')->count(),
            'counts' => Extraction::selectRaw('provider, count(*) as total')->groupBy('provider')->pluck('total', 'provider'),
            'extractions' => Extraction::latest('id')->paginate(12),
        ]);
    }

    public function module(ScrapingProvider $provider): View
    {
        return view('hub.module', [
            'provider' => $provider,
            'extractions' => Extraction::where('provider', $provider->value)->latest('id')->paginate(10),
        ]);
    }

    public function store(StoreExtractionRequest $request, ScrapingProvider $provider): RedirectResponse
    {
        $data = $request->validated();
        $extraction = Extraction::create([
            'name' => $data['name'], 'url' => $data['url'], 'provider' => $provider,
            'mode' => $data['mode'], 'options' => $request->options(),
        ]);
        ExecuteExtraction::dispatch($extraction->id)->afterCommit();

        return to_route('extractions.show', [$provider, $extraction])->with('message', 'Extracción enviada a la cola.');
    }

    public function show(ScrapingProvider $provider, Extraction $extraction): View
    {
        abort_unless($extraction->provider === $provider, 404);

        return view('hub.show', compact('provider', 'extraction'));
    }

    public function status(ScrapingProvider $provider, Extraction $extraction): JsonResponse
    {
        abort_unless($extraction->provider === $provider, 404);

        return response()->json(['status' => $extraction->status, 'label' => $extraction->statusLabel(), 'active' => $extraction->isActive()]);
    }

    public function export(ScrapingProvider $provider, Extraction $extraction, string $format): StreamedResponse
    {
        abort_unless($extraction->provider === $provider && $extraction->status === 'completed' && in_array($format, ['json', 'csv'], true), 404);
        $label = $extraction->mode === 'demo' ? 'DEMO_DATOS_SIMULADOS' : 'API_REAL';

        return response()->streamDownload(function () use ($extraction, $format): void {
            if ($format === 'json') {
                echo json_encode(SensitiveData::clean([
                    'provider' => $extraction->provider->value, 'mode' => $extraction->mode,
                    'simulated' => $extraction->mode === 'demo', 'url' => $extraction->url,
                    'records' => $extraction->records, 'provider_response' => $extraction->provider_payload,
                ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                return;
            }
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $columns = $extraction->columns();
            $safe = function (mixed $value): string {
                $value = SensitiveData::clean($value);
                $text = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $value;

                return preg_match('/^[\s]*[=+@\-]/u', $text) ? "'".$text : $text;
            };
            fputcsv($stream, ['_mode', '_provider', ...array_map($safe, $columns)], escape: '');
            foreach ($extraction->records ?? [] as $record) {
                fputcsv($stream, [$extraction->mode, $extraction->provider->value, ...array_map(fn (string $column): string => $safe($record[$column] ?? null), $columns)], escape: '');
            }
            fclose($stream);
        }, $provider->value.'-'.$extraction->id.'-'.$label.'.'.$format, [
            'Content-Type' => $format === 'json' ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
        ]);
    }
}
