<?php

namespace App\Support\Certificates;

final class CertificateLayoutSchema
{
    public const MIN_WIDTH = 10.0;

    public const MAX_WIDTH = 100.0;

    public const MAX_Y = 95.0;

    public const MIN_FONT_SIZE = 8;

    public const MAX_FONT_SIZE = 80;

    private const FONT_FAMILIES = [
        'font-sans',
        'font-serif',
        'font-mono',
        'font-roboto',
        'font-tangerine',
        'font-montserrat',
    ];

    private const TEXT_ALIGNS = [
        'text-left',
        'text-center',
        'text-right',
        'text-justify',
    ];

    private const POSITIONED_ELEMENTS = [
        'header',
        'name',
        'body',
        'signature_line',
        'signer_label',
        'signer_position',
    ];

    private const VISIBILITY_ELEMENTS = [
        'issued_on',
        'expires_at',
    ];

    private const POSITIONED_KEYS = [
        'x',
        'y',
        'width',
        'font_family',
        'font_size',
        'text_align',
    ];

    private const VISIBILITY_KEYS = [
        'enabled',
    ];

    private const DEFAULT_LAYOUT = [
        'header' => [
            'x' => 15.0,
            'y' => 9.0,
            'width' => 70.0,
            'font_family' => 'font-serif',
            'font_size' => 45,
            'text_align' => 'text-center',
        ],
        'name' => [
            'x' => 15.0,
            'y' => 25.0,
            'width' => 70.0,
            'font_family' => 'font-serif',
            'font_size' => 68,
            'text_align' => 'text-center',
        ],
        'body' => [
            'x' => 15.0,
            'y' => 42.0,
            'width' => 70.0,
            'font_family' => 'font-sans',
            'font_size' => 25,
            'text_align' => 'text-center',
        ],
        'signature_line' => [
            'x' => 65.0,
            'y' => 68.0,
            'width' => 27.0,
            'font_family' => 'font-serif',
            'font_size' => 18,
            'text_align' => 'text-right',
        ],
        'signer_label' => [
            'x' => 65.0,
            'y' => 76.0,
            'width' => 27.0,
            'font_family' => 'font-sans',
            'font_size' => 22,
            'text_align' => 'text-right',
        ],
        'signer_position' => [
            'x' => 65.0,
            'y' => 82.0,
            'width' => 27.0,
            'font_family' => 'font-sans',
            'font_size' => 18,
            'text_align' => 'text-right',
        ],
        'issued_on' => [
            'enabled' => true,
        ],
        'expires_at' => [
            'enabled' => true,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaultLayout(): array
    {
        return self::DEFAULT_LAYOUT;
    }

    /**
     * @return list<string>
     */
    public static function elementNames(): array
    {
        return array_keys(
            self::DEFAULT_LAYOUT
        );
    }

    /**
     * @return list<string>
     */
    public static function positionedElements(): array
    {
        return self::POSITIONED_ELEMENTS;
    }

    /**
     * @return list<string>
     */
    public static function visibilityElements(): array
    {
        return self::VISIBILITY_ELEMENTS;
    }

    /**
     * @return list<string>
     */
    public static function keysFor(
        string $element
    ): array {
        return self::isVisibilityElement(
            $element
        )
            ? self::VISIBILITY_KEYS
            : self::POSITIONED_KEYS;
    }

    /**
     * @return list<string>
     */
    public static function fontFamilies(): array
    {
        return self::FONT_FAMILIES;
    }

    /**
     * @return array<string, string>
     */
    public static function fontOptions(): array
    {
        return [
            'font-sans' => 'Plus Jakarta Sans',
            'font-serif' => 'System Serif',
            'font-mono' => 'Monospace',
            'font-roboto' => 'Roboto',
            'font-tangerine' => 'Tangerine',
            'font-montserrat' => 'Montserrat',
        ];
    }

    /**
     * @return list<string>
     */
    public static function textAligns(): array
    {
        return self::TEXT_ALIGNS;
    }

    /**
     * @return array<string, string>
     */
    public static function alignmentOptions(): array
    {
        return [
            'text-left' => 'Left',
            'text-center' => 'Center',
            'text-right' => 'Right',
            'text-justify' => 'Justify',
        ];
    }

    public static function isPositionedElement(
        string $element
    ): bool {
        return in_array(
            $element,
            self::POSITIONED_ELEMENTS,
            true
        );
    }

    public static function isVisibilityElement(
        string $element
    ): bool {
        return in_array(
            $element,
            self::VISIBILITY_ELEMENTS,
            true
        );
    }

    /**
     * Strict normalization after validation succeeds.
     *
     * @param array<string, mixed> $layout
     *
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeValidated(
        array $layout
    ): array {
        $normalized = [];

        foreach (
            self::DEFAULT_LAYOUT
            as $element => $default
        ) {
            if (
                self::isVisibilityElement(
                    $element
                )
            ) {
                $normalized[$element] = [
                    'enabled' => filter_var(
                        $layout[$element]['enabled'],
                        FILTER_VALIDATE_BOOL
                    ),
                ];

                continue;
            }

            $normalized[$element] = [
                'x' => round(
                    (float) $layout[$element]['x'],
                    3
                ),
                'y' => round(
                    (float) $layout[$element]['y'],
                    3
                ),
                'width' => round(
                    (float) $layout[$element]['width'],
                    3
                ),
                'font_family' =>
                    (string) $layout[$element]['font_family'],
                'font_size' =>
                    (int) $layout[$element]['font_size'],
                'text_align' =>
                    (string) $layout[$element]['text_align'],
            ];
        }

        return $normalized;
    }
}
