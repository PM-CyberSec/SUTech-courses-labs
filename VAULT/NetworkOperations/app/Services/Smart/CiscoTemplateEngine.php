<?php

namespace App\Services\Smart;

class CiscoTemplateEngine
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables): string
    {
        $flattened = $this->flatten($variables);

        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9._-]+)\s*\}\}/', function ($matches) use ($flattened) {
            $key = $matches[1];

            if (! array_key_exists($key, $flattened)) {
                return $matches[0];
            }

            $value = $flattened[$key];
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_SLASHES);
            }

            return (string) $value;
        }, $template);

        return trim((string) $rendered);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  string  $prefix
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }
}
