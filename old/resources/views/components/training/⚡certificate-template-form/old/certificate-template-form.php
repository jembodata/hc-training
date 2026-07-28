<?php

use App\Models\CertificateTemplate;
use App\Rules\ValidCertificateLayout;
use App\Support\Auth\Permissions;
use App\Support\Certificates\CertificateLayoutSchema;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $template_id = null;

    public string $name = '';

    public string $kind =
    CertificateTemplate::KIND_COMPLETION;

    public string $design =
    CertificateTemplate::DESIGN_MINIMAL_ACADEMIC;

    public mixed $custom_background = null;

    public ?string $existing_custom_background_path = null;

    public string $header_text =
    'Certificate of Completion';

    public string $body_text =
    'This certificate is presented to {{employee_name}} '
        . 'for successfully completing the course '
        . '"{{course_title}}".';

    public string $signature_line =
    'Authorised by Learning and Development Team';

    public bool $digital_signature_enabled = false;

    public string $signer_label = '';

    public string $signer_position = '';

    public array $layout_settings = [];

    public bool $is_default = false;

    public function mount(?int $template = null): void
    {
        $this->layout_settings = CertificateLayoutSchema::defaultLayout();

        if ($template !== null) {
            Gate::authorize(
                Permissions::UPDATE_CERTIFICATE_TEMPLATE
            );

            $this->loadTemplate($template);

            return;
        }

        Gate::authorize(
            Permissions::CREATE_CERTIFICATE_TEMPLATE
        );
    }

    public function updatedCustomBackground(): void
    {
        $this->validateOnly(
            'custom_background',
            $this->rules(),
            $this->validationMessages()
        );
    }

    public function insertVariable(string $variable): void
    {
        if (
            ! in_array(
                $variable,
                CertificateTemplate::SUPPORTED_VARIABLES,
                true
            )
        ) {
            return;
        }

        $placeholder = '{{' . $variable . '}}';
        $body = rtrim($this->body_text);

        $this->body_text = $body === ''
            ? $placeholder
            : $body . ' ' . $placeholder;
    }

    public function resetLayout(): void
    {
        $this->layout_settings = CertificateLayoutSchema::defaultLayout();

        Flux::toast(
            heading: 'Layout reset',
            text: 'Posisi dan style elemen dikembalikan ke default.',
            variant: 'success',
            duration: 2500,
        );
    }

    public function save(): void
    {
        Gate::authorize(
            $this->template_id !== null
                ? Permissions::UPDATE_CERTIFICATE_TEMPLATE
                : Permissions::CREATE_CERTIFICATE_TEMPLATE
        );

        $this->normalizeForm();

        $validated = $this->validate(
            $this->rules(),
            $this->validationMessages()
        );

        /*
         * Setelah lolos validasi ketat, cast dan pembulatan
         * dilakukan secara deterministik sebelum disimpan.
         */
        $validated['layout_settings'] =
            CertificateLayoutSchema::normalizeValidated(
                $validated['layout_settings']
            );

        $this->layout_settings =
            $validated['layout_settings'];

        $oldBackgroundPath =
            $this->existing_custom_background_path;

        $newBackgroundPath = null;

        if (
            $validated['design']
            === CertificateTemplate::DESIGN_CUSTOM_UPLOAD
            && $this->custom_background
        ) {
            $newBackgroundPath =
                $this->custom_background->store(
                    'certificate-templates/backgrounds',
                    'public'
                );
        }

        $backgroundPath = $validated['design']
            === CertificateTemplate::DESIGN_CUSTOM_UPLOAD
            ? ($newBackgroundPath ?: $oldBackgroundPath)
            : null;

        try {
            DB::transaction(function () use (
                $validated,
                $backgroundPath
            ): void {
                $this->removePreviousDefault($validated);

                $payload = $this->buildPayload(
                    $validated,
                    $backgroundPath
                );

                if ($this->template_id !== null) {
                    CertificateTemplate::query()
                        ->findOrFail($this->template_id)
                        ->update($payload);

                    return;
                }

                $payload['created_by'] = auth()->id();

                $template = CertificateTemplate::query()
                    ->create($payload);

                $this->template_id =
                    (int) $template->id;
            });
        } catch (\Throwable $exception) {
            if ($newBackgroundPath) {
                Storage::disk('public')->delete(
                    $newBackgroundPath
                );
            }

            report($exception);

            $this->addError(
                'save',
                'Template gagal disimpan. Silakan coba kembali.'
            );

            Flux::toast(
                heading: 'Save failed',
                text: 'Certificate template gagal disimpan.',
                variant: 'danger',
                duration: 5000,
            );

            return;
        }

        if (
            $oldBackgroundPath
            && $oldBackgroundPath !== $backgroundPath
        ) {
            Storage::disk('public')->delete(
                $oldBackgroundPath
            );
        }

        /*
         * Setelah upload tersimpan, gunakan file permanen
         * sebagai preview dan buang temporary upload.
         */
        $this->existing_custom_background_path =
            $backgroundPath;

        $this->custom_background = null;

        $this->resetErrorBag('save');

        Flux::toast(
            heading: 'Changes saved',
            text: 'Certificate template berhasil disimpan.',
            variant: 'success',
            duration: 3500,
        );
    }

    public function with(): array
    {
        return [
            'kind_options' => [
                CertificateTemplate::KIND_COMPLETION =>
                'Completion',

                CertificateTemplate::KIND_PARTICIPATION =>
                'Participation',
            ],

            'design_options' => [
                CertificateTemplate::DESIGN_MINIMAL_ACADEMIC => [
                    'label' => 'Minimal Academic',
                    'description' =>
                    'Clean and formal certificate.',
                ],

                CertificateTemplate::DESIGN_MODERN_BLUE => [
                    'label' => 'Modern Blue',
                    'description' =>
                    'Modern corporate blue layout.',
                ],

                CertificateTemplate::DESIGN_CUSTOM_UPLOAD => [
                    'label' => 'Custom Upload',
                    'description' =>
                    'A4 landscape JPG or PNG.',
                ],
            ],

            'font_options' =>
                CertificateLayoutSchema::fontOptions(),

            'alignment_options' =>
                CertificateLayoutSchema::alignmentOptions(),

            'element_options' => [
                'header' => [
                    'label' => 'Header Text',
                    'digital_only' => false,
                ],
                'name' => [
                    'label' => 'Name',
                    'digital_only' => false,
                ],
                'body' => [
                    'label' => 'Body',
                    'digital_only' => false,
                ],
                'signature_line' => [
                    'label' => 'Signature Line',
                    'digital_only' => false,
                ],
                'signer_label' => [
                    'label' => 'Signer Label',
                    'digital_only' => true,
                ],
                'signer_position' => [
                    'label' => 'Signer Position',
                    'digital_only' => true,
                ],
            ],

            'supported_variables' =>
            CertificateTemplate::SUPPORTED_VARIABLES,

            'preview_body' =>
            $this->renderPreviewText(
                $this->body_text
            ),

            'preview_values' =>
            $this->previewValues(),

            'preview_background_url' =>
            $this->previewBackgroundUrl(),
        ];
    }

    private function buildPayload(
        array $validated,
        ?string $backgroundPath
    ): array {
        return [
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'design' => $validated['design'],

            'custom_background_path' =>
            $backgroundPath,

            /*
             * Kolom global lama tidak lagi digunakan.
             */
            'font_family' => null,
            'title_font_family' => null,

            'header_text' =>
            (string) (
                $validated['header_text'] ?? ''
            ),

            'body_text' =>
            $validated['body_text'],

            'signature_line' =>
            $validated['signature_line'] ?? null,

            'digital_signature_enabled' =>
            $validated['digital_signature_enabled'],

            'signature_provider' =>
            $validated['digital_signature_enabled']
                ? 'pending'
                : null,

            'signer_label' =>
            $validated['digital_signature_enabled']
                ? (
                    $validated['signer_label']
                    ?? null
                )
                : null,

            'signer_position' =>
            $validated['digital_signature_enabled']
                ? (
                    $validated['signer_position']
                    ?? null
                )
                : null,

            /*
             * Dipertahankan untuk kompatibilitas modul lama.
             */
            'signature_layout' =>
            $this->legacySignatureLayout(
                $validated['layout_settings']['signature_line']['text_align']
            ),

            'layout_settings' =>
            $validated['layout_settings'],

            'is_default' =>
            $validated['is_default'],

            'updated_by' =>
            auth()->id(),
        ];
    }

    private function removePreviousDefault(
        array $validated
    ): void {
        if (! $validated['is_default']) {
            return;
        }

        CertificateTemplate::query()
            ->where('kind', $validated['kind'])
            ->when(
                $this->template_id !== null,
                fn($query) => $query->where(
                    'id',
                    '!=',
                    $this->template_id
                )
            )
            ->where('is_default', true)
            ->update([
                'is_default' => false,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);
    }

    private function loadTemplate(
        int $templateId
    ): void {
        $template = CertificateTemplate::query()
            ->findOrFail($templateId);

        $this->template_id =
            (int) $template->id;

        $this->name =
            (string) $template->name;

        $this->kind =
            (string) $template->kind;

        $this->design =
            (string) $template->design;

        $this->existing_custom_background_path =
            $template->custom_background_path;

        $this->header_text =
            (string) $template->header_text;

        $this->body_text =
            (string) $template->body_text;

        $this->signature_line =
            (string) (
                $template->signature_line ?? ''
            );

        $this->digital_signature_enabled =
            (bool) $template
                ->digital_signature_enabled;

        $this->signer_label =
            (string) (
                $template->signer_label ?? ''
            );

        $this->signer_position =
            (string) (
                $template->signer_position ?? ''
            );

        $savedLayout = is_array(
            $template->layout_settings
        )
            ? $template->layout_settings
            : [];

        $legacyAlignment = match (
            $template->signature_layout
        ) {
            'left' => 'text-left',
            'center' => 'text-center',
            default => 'text-right',
        };

        /*
         * Load data lama secara toleran agar template legacy
         * tetap dapat dibuka. Validasi ketat tetap dilakukan
         * ketika user menyimpan perubahan.
         */
        $this->layout_settings =
            CertificateLayoutSchema::normalizeForEditing(
                $savedLayout,
                $legacyAlignment
            );

        $this->is_default =
            (bool) $template->is_default;
    }

    private function rules(): array
    {
        return [
                'name' => [
                    'required',
                    'string',
                    'min:3',
                    'max:150',
                ],

                'kind' => [
                    'required',
                    Rule::in([
                        CertificateTemplate::KIND_COMPLETION,
                        CertificateTemplate::KIND_PARTICIPATION,
                    ]),
                ],

                'design' => [
                    'required',
                    Rule::in([
                        CertificateTemplate::DESIGN_MINIMAL_ACADEMIC,
                        CertificateTemplate::DESIGN_MODERN_BLUE,
                        CertificateTemplate::DESIGN_CUSTOM_UPLOAD,
                    ]),
                ],

                'custom_background' =>
                $this->backgroundRules(),

                'header_text' => [
                    'nullable',
                    'string',
                    'max:200',
                ],

                'body_text' => [
                    'required',
                    'string',
                    'max:2000',
                    $this->supportedVariableRule(),
                ],

                'signature_line' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'digital_signature_enabled' => [
                    'boolean',
                ],

                'signer_label' => [
                    Rule::requiredIf(
                        $this->digital_signature_enabled
                    ),
                    'nullable',
                    'string',
                    'max:150',
                ],

                'signer_position' => [
                    Rule::requiredIf(
                        $this->digital_signature_enabled
                    ),
                    'nullable',
                    'string',
                    'max:150',
                ],

                'is_default' => [
                    'boolean',
                ],

                'layout_settings' => [
                    'required',
                    new ValidCertificateLayout(),
                ],
            ];
    }

    private function backgroundRules(): array
    {
        return [
            Rule::requiredIf(
                $this->design
                    === CertificateTemplate::DESIGN_CUSTOM_UPLOAD
                    && ! $this->existing_custom_background_path
            ),
            'nullable',
            'image',
            'mimes:jpg,jpeg,png',
            'max:5120',
            'dimensions:min_width=1120,min_height=790',

            function (
                string $attribute,
                mixed $value,
                \Closure $fail
            ): void {
                if (! $value) {
                    return;
                }

                $size = @getimagesize(
                    $value->getRealPath()
                );

                if (! $size || $size[0] <= $size[1]) {
                    $fail(
                        'Custom design harus berorientasi landscape.'
                    );

                    return;
                }

                if (
                    abs(
                        ($size[0] / $size[1])
                            - (297 / 210)
                    ) > 0.08
                ) {
                    $fail(
                        'Rasio custom design harus mendekati A4 landscape.'
                    );
                }
            },
        ];
    }

    private function supportedVariableRule(): \Closure
    {
        return function (
            string $attribute,
            mixed $value,
            \Closure $fail
        ): void {
            preg_match_all(
                '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
                (string) $value,
                $matches
            );

            $variables = array_unique(
                array_map(
                    'strtolower',
                    $matches[1] ?? []
                )
            );

            $unknown = array_diff(
                $variables,
                CertificateTemplate::SUPPORTED_VARIABLES
            );

            if ($unknown !== []) {
                $fail(
                    'Variable tidak didukung: '
                        . implode(', ', $unknown)
                );
            }
        };
    }

    private function validationMessages(): array
    {
        return [
            'name.required' =>
            'Template name wajib diisi.',

            'name.min' =>
            'Template name minimal 3 karakter.',

            'custom_background.required' =>
            'Custom design wajib diunggah.',

            'custom_background.image' =>
            'Custom design harus berupa gambar.',

            'custom_background.mimes' =>
            'Custom design harus berformat JPG atau PNG.',

            'custom_background.max' =>
            'Ukuran custom design maksimal 5 MB.',

            'custom_background.dimensions' =>
            'Resolusi minimal custom design adalah 1120 × 790.',

            'body_text.required' =>
            'Body wajib diisi.',

            'signer_label.required' =>
            'Signer label wajib diisi.',

            'signer_position.required' =>
            'Signer position wajib diisi.',

            'layout_settings.required' =>
            'Pengaturan layout tidak ditemukan.',
        ];
    }

    private function normalizeForm(): void
    {
        $this->name =
            trim($this->name);

        $this->header_text =
            trim($this->header_text);

        $this->signature_line =
            trim($this->signature_line);

        $this->signer_label =
            trim($this->signer_label);

        $this->signer_position =
            trim($this->signer_position);

        $this->body_text = trim(
            preg_replace_callback(
                '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
                fn(array $matches): string =>
                '{{'
                    . strtolower($matches[1])
                    . '}}',
                $this->body_text
            ) ?? $this->body_text
        );

        if (! $this->digital_signature_enabled) {
            $this->signer_label = '';
            $this->signer_position = '';
        }
    }

    private function legacySignatureLayout(
        string $textAlign
    ): string {
        return match ($textAlign) {
            'text-left',
            'text-justify' => 'left',

            'text-center' => 'center',

            default => 'right',
        };
    }

    private function renderPreviewText(
        string $text
    ): string {
        $replacements = [];

        foreach (
            $this->previewValues()
            as $key => $value
        ) {
            $replacements['{{' . $key . '}}'] = $value;
        }

        return strtr($text, $replacements);
    }

    private function previewValues(): array
    {
        return [
            'employee_name' =>
            'Airene Hanaporro',

            'employee_nik' =>
            'EMP-000123',

            'department_name' =>
            'Learning and Development',

            'course_title' =>
            'AWS for Beginners',

            'tanggal_training' =>
            now()->format('d M Y'),

            'certificate_id' =>
            'CERT-PREVIEW-SAMPLE',

            'issued_on' =>
            now()->format('d M Y'),

            'expires_at' =>
            now()->addYear()->format('d M Y'),
        ];
    }

    private function previewBackgroundUrl(): ?string
    {
        if (
            $this->design
            !== CertificateTemplate::DESIGN_CUSTOM_UPLOAD
        ) {
            return null;
        }

        if ($this->custom_background) {
            try {
                return $this->custom_background->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        if (empty($this->existing_custom_background_path)) {
            return null;
        }

        $path = ltrim(
            $this->existing_custom_background_path,
            '/'
        );

        /*
     * Hindari hasil:
     * /storage/storage/certificate-templates/...
     */
        $path = preg_replace(
            '#^storage/#',
            '',
            $path
        );

        return asset('storage/' . $path);
    }
};