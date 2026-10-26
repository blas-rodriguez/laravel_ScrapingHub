<?php

namespace App\Http\Requests;

use App\Rules\PublicTargetUrl;
use App\ScrapingProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->route('provider');
        $graph = $provider === ScrapingProvider::ScrapeGraphAi;
        $fire = $provider === ScrapingProvider::Firecrawl;
        $browse = $provider === ScrapingProvider::BrowseAi;

        return [
            'name' => ['required', 'string', 'max:100'],
            'mode' => ['required', Rule::in(['demo', 'live'])],
            'url' => ['bail', 'required', 'string', 'max:2000', 'url:http,https', new PublicTargetUrl],
            'format' => [Rule::excludeIf(! $fire), 'required', Rule::in(['markdown', 'json'])],
            'prompt' => [Rule::excludeIf($browse || ($fire && $this->input('format') !== 'json')), Rule::requiredIf($graph || ($fire && $this->input('format') === 'json')), 'nullable', 'string', 'max:4000'],
            'schema_json' => [Rule::excludeIf($browse || ($fire && $this->input('format') !== 'json')), 'nullable', 'string', 'max:10000', function (string $attribute, mixed $value, \Closure $fail): void {
                $schema = json_decode($value, true);
                if (! is_array($schema) || ($schema['type'] ?? null) !== 'object' || ! is_array($schema['properties'] ?? null)) {
                    $fail('El schema debe ser un objeto JSON con type: object y properties.');
                }
            }],
            'robot_id' => [Rule::excludeIf(! $browse), Rule::requiredIf($browse && $this->input('mode') === 'live'), 'nullable', 'string', 'regex:/^[a-zA-Z0-9_-]{1,150}$/'],
            'url_parameter' => [Rule::excludeIf(! $browse), 'required', 'string', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{0,79}$/'],
            'input_parameters_json' => [Rule::excludeIf(! $browse), 'nullable', 'string', 'max:10000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! (json_decode($value) instanceof \stdClass)) {
                    $fail('Los parámetros adicionales deben ser un objeto JSON válido.');
                }
            }],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $provider = $this->route('provider');
            if ($this->input('mode') === 'live' && $provider instanceof ScrapingProvider && ! $provider->configured()) {
                $validator->errors()->add('mode', 'Falta la API key de '.$provider->label().'. Configurala en .env y reiniciá el worker.');
            }
        }];
    }

    public function options(): array
    {
        $data = $this->validated();
        $options = array_intersect_key($data, array_flip(['format', 'prompt', 'robot_id', 'url_parameter']));
        if (! empty($data['schema_json'])) {
            $options['schema'] = json_decode($data['schema_json'], true, flags: JSON_THROW_ON_ERROR);
        }
        if (! empty($data['input_parameters_json'])) {
            $options['input_parameters'] = json_decode($data['input_parameters_json'], true, flags: JSON_THROW_ON_ERROR);
        }

        return $options;
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'url.url' => 'Ingresá una URL HTTP(S) válida.', 'in' => 'El valor de :attribute no es válido.'];
    }
}
