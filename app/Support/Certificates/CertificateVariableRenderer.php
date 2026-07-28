<?php

namespace App\Support\Certificates;

final class CertificateVariableRenderer
{
    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(string $text, array $variables): string
    {
        $normalized = [];

        foreach ($variables as $key => $value) {
            $normalized[strtolower($key)] = (string) ($value ?? '');
        }

        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            static fn (array $matches): string =>
                $normalized[strtolower($matches[1])]
                    ?? $matches[0],
            $text
        ) ?? $text;
    }
}
