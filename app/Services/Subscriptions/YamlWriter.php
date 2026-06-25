<?php

namespace App\Services\Subscriptions;

use Illuminate\Support\Arr;

class YamlWriter
{
    /**
     * @param  array<string, mixed>|array<int, mixed>  $data
     */
    public function dump(array $data): string
    {
        return rtrim($this->renderValue($data, 0))."\n";
    }

    private function renderValue(mixed $value, int $level): string
    {
        if (is_array($value)) {
            return $this->isList($value)
                ? $this->renderList($value, $level)
                : $this->renderMap($value, $level);
        }

        return $this->renderScalar($value);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function renderList(array $items, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $lines = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                if ($this->isList($item)) {
                    $lines[] = $indent.'-';
                    $lines[] = $this->renderList($item, $level + 1);
                    continue;
                }

                $firstKey = Arr::first(array_keys($item));
                $firstValue = $item[$firstKey];

                if (is_array($firstValue)) {
                    $lines[] = $indent.'- '.$firstKey.':';
                    $remaining = $item;
                    unset($remaining[$firstKey]);
                    $lines[] = $this->renderValue($firstValue, $level + 2);

                    if ($remaining !== []) {
                        $lines[] = $this->renderMap($remaining, $level + 1);
                    }

                    continue;
                }

                $lines[] = $indent.'- '.$firstKey.': '.$this->renderScalar($firstValue);
                $remaining = $item;
                unset($remaining[$firstKey]);

                if ($remaining !== []) {
                    $lines[] = $this->renderMap($remaining, $level + 1);
                }

                continue;
            }

            $lines[] = $indent.'- '.$this->renderScalar($item);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function renderMap(array $map, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $lines = [];

        foreach ($map as $key => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    $lines[] = $indent.$key.': []';
                    continue;
                }

                $lines[] = $indent.$key.':';
                $lines[] = $this->renderValue($value, $level + 1);
                continue;
            }

            $lines[] = $indent.$key.': '.$this->renderScalar($value);
        }

        return implode("\n", $lines);
    }

    private function renderScalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => $this->quote((string) $value),
        };
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $value
     */
    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
