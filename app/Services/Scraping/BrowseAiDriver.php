<?php

namespace App\Services\Scraping;

use App\Models\Extraction;
use App\ScrapingProvider;
use RuntimeException;

class BrowseAiDriver implements ScrapingDriver
{
    public function __construct(private ApiTransport $api) {}

    public function start(Extraction $extraction): ProviderResult
    {
        $parameters = $extraction->options['input_parameters'] ?? [];
        $parameters[$extraction->options['url_parameter']] = $extraction->url;
        $payload = $this->api->send(ScrapingProvider::BrowseAi, 'POST', $this->path($extraction), [
            'inputParameters' => $parameters,
        ]);

        return $this->result($payload);
    }

    public function poll(Extraction $extraction): ProviderResult
    {
        $id = ResultData::opaqueId($extraction->remote_id);
        $payload = $this->api->send(ScrapingProvider::BrowseAi, 'GET', $this->path($extraction).'/'.rawurlencode($id));

        return $this->result($payload);
    }

    private function path(Extraction $extraction): string
    {
        return '/robots/'.rawurlencode(ResultData::opaqueId($extraction->options['robot_id'] ?? null)).'/tasks';
    }

    private function result(array $payload): ProviderResult
    {
        $task = $payload['result'] ?? [];
        $status = $task['status'] ?? null;
        if (in_array($status, ['failed', 'canceled', 'cancelled'], true)) {
            throw new RuntimeException('El robot de Browse AI no pudo completar la tarea. Revisá su historial y entrenamiento.');
        }
        if (in_array($status, ['running', 'queued', 'pending', 'in-progress'], true)) {
            return new ProviderResult(false, payload: $payload, remoteId: ResultData::opaqueId($task['id'] ?? null));
        }
        if ($status !== 'successful') {
            throw new RuntimeException('Browse AI devolvió un estado de tarea no reconocido.');
        }
        $rows = [];
        $lists = $task['capturedLists'] ?? [];
        foreach ($task['capturedTexts'] ?? [] as $name => $value) {
            if (is_array($value) && array_is_list($value) && ! array_key_exists($name, $lists)) {
                $lists[$name] = $value;
            }
        }
        foreach ($lists as $name => $list) {
            foreach (ResultData::rows($list) as $row) {
                $rows[] = [...$row, '_list' => $name];
            }
        }
        if (count($rows) > 500) {
            throw new RuntimeException('La respuesta supera el límite local de 500 filas.');
        }
        if ($rows === []) {
            $rows = ResultData::rows($task['capturedTexts'] ?? []);
        }

        return new ProviderResult(true, $rows, $payload, isset($task['id']) ? ResultData::opaqueId($task['id']) : null);
    }
}
