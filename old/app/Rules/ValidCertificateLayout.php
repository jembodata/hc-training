<?php

namespace App\Rules;

use App\Support\Certificates\CertificateLayoutSchema;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCertificateLayout implements ValidationRule
{
    private const EPSILON = 0.0001;

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! is_array($value)) {
            $fail(
                'Pengaturan layout harus berupa object.'
            );

            return;
        }

        $expectedElements =
            CertificateLayoutSchema::elementNames();

        $providedElements =
            array_keys($value);

        $unknownElements = array_values(
            array_diff(
                $providedElements,
                $expectedElements
            )
        );

        if ($unknownElements !== []) {
            $fail(
                'Elemen layout tidak didukung: '
                    . implode(', ', $unknownElements)
                    . '.'
            );

            return;
        }

        $missingElements = array_values(
            array_diff(
                $expectedElements,
                $providedElements
            )
        );

        if ($missingElements !== []) {
            $fail(
                'Elemen layout belum lengkap: '
                    . implode(', ', $missingElements)
                    . '.'
            );

            return;
        }

        foreach (
            $expectedElements
            as $element
        ) {
            $settings = $value[$element];

            if (! is_array($settings)) {
                $fail(
                    "Pengaturan elemen {$element} "
                        . 'harus berupa object.'
                );

                return;
            }

            if (
                ! $this->hasExactKeys(
                    $element,
                    $settings,
                    $fail
                )
            ) {
                return;
            }

            if (
                CertificateLayoutSchema::isVisibilityElement(
                    $element
                )
            ) {
                if (
                    ! $this->isBooleanLike(
                        $settings['enabled']
                    )
                ) {
                    $fail(
                        "Nilai enabled pada {$element} "
                            . 'harus boolean.'
                    );

                    return;
                }

                continue;
            }

            if (
                ! $this->validatePositionedElement(
                    $element,
                    $settings,
                    $fail
                )
            ) {
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function hasExactKeys(
        string $element,
        array $settings,
        Closure $fail
    ): bool {
        $expectedKeys =
            CertificateLayoutSchema::keysFor(
                $element
            );

        $providedKeys =
            array_keys($settings);

        $unknownKeys = array_values(
            array_diff(
                $providedKeys,
                $expectedKeys
            )
        );

        if ($unknownKeys !== []) {
            $fail(
                "Pengaturan {$element} tidak didukung: "
                    . implode(', ', $unknownKeys)
                    . '.'
            );

            return false;
        }

        $missingKeys = array_values(
            array_diff(
                $expectedKeys,
                $providedKeys
            )
        );

        if ($missingKeys !== []) {
            $fail(
                "Pengaturan {$element} belum lengkap: "
                    . implode(', ', $missingKeys)
                    . '.'
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function validatePositionedElement(
        string $element,
        array $settings,
        Closure $fail
    ): bool {
        foreach (
            [
                'x',
                'y',
                'width',
            ] as $key
        ) {
            if (
                ! $this->isFiniteNumber(
                    $settings[$key]
                )
            ) {
                $fail(
                    "Nilai {$key} pada {$element} "
                        . 'harus berupa angka finite.'
                );

                return false;
            }
        }

        $x = (float) $settings['x'];
        $y = (float) $settings['y'];
        $width = (float) $settings['width'];

        if ($x < 0.0 || $x > 100.0) {
            $fail(
                "Posisi x pada {$element} "
                    . 'harus antara 0 dan 100.'
            );

            return false;
        }

        if (
            $y < 0.0
            || $y > CertificateLayoutSchema::MAX_Y
        ) {
            $fail(
                "Posisi y pada {$element} "
                    . 'harus antara 0 dan '
                    . CertificateLayoutSchema::MAX_Y
                    . '.'
            );

            return false;
        }

        if (
            $width
            < CertificateLayoutSchema::MIN_WIDTH
            || $width
            > CertificateLayoutSchema::MAX_WIDTH
        ) {
            $fail(
                "Width pada {$element} harus antara "
                    . CertificateLayoutSchema::MIN_WIDTH
                    . ' dan '
                    . CertificateLayoutSchema::MAX_WIDTH
                    . '.'
            );

            return false;
        }

        if (
            $x + $width
            > 100.0 + self::EPSILON
        ) {
            $fail(
                "Elemen {$element} melewati "
                    . 'batas kanan canvas.'
            );

            return false;
        }

        if (
            ! in_array(
                $settings['font_family'],
                CertificateLayoutSchema::fontFamilies(),
                true
            )
        ) {
            $fail(
                "Font family pada {$element} "
                    . 'tidak didukung.'
            );

            return false;
        }

        $fontSize = filter_var(
            $settings['font_size'],
            FILTER_VALIDATE_INT
        );

        if (
            $fontSize === false
            || $fontSize
            < CertificateLayoutSchema::MIN_FONT_SIZE
            || $fontSize
            > CertificateLayoutSchema::MAX_FONT_SIZE
        ) {
            $fail(
                "Font size pada {$element} harus "
                    . 'berupa integer antara '
                    . CertificateLayoutSchema::MIN_FONT_SIZE
                    . ' dan '
                    . CertificateLayoutSchema::MAX_FONT_SIZE
                    . '.'
            );

            return false;
        }

        if (
            ! in_array(
                $settings['text_align'],
                CertificateLayoutSchema::textAligns(),
                true
            )
        ) {
            $fail(
                "Text alignment pada {$element} "
                    . 'tidak didukung.'
            );

            return false;
        }

        return true;
    }

    private function isFiniteNumber(
        mixed $value
    ): bool {
        if (! is_numeric($value)) {
            return false;
        }

        return is_finite(
            (float) $value
        );
    }

    private function isBooleanLike(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return true;
        }

        return in_array(
            $value,
            [
                0,
                1,
                '0',
                '1',
            ],
            true
        );
    }
}
