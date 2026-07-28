@assets
    <script src="https://cdnjs.cloudflare.com/ajax/libs/interact.js/1.10.27/interact.min.js"></script>

    <style>
        .certificate-layout-item {
            position: absolute;
            z-index: 20;
            box-sizing: border-box;
            min-width: 10%;
            padding: 4px 10px;
            cursor: move;
            touch-action: none;
            user-select: none;
            border: 1px dashed transparent;
        }

        .certificate-layout-item:hover,
        .certificate-layout-item.is-active {
            border-color: #2563eb;
            background: rgb(239 246 255 / 35%);
        }

        .certificate-resize-handle {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 14px;
            cursor: ew-resize;
        }

        .certificate-resize-left {
            left: -7px;
        }

        .certificate-resize-right {
            right: -7px;
        }
    </style>
@endassets

@php
    $fontClasses = [
        'sans' => 'font-sans',
        'serif' => 'font-serif',
        'mono' => 'font-mono',
    ];

    $layoutStyle = function (string $key) use ($layout_settings): string {
        $item = $layout_settings[$key];

        return sprintf(
            'left:%s%%;top:%s%%;width:%s%%;font-size:%spx;',
            $item['x'],
            $item['y'],
            $item['width'],
            $item['font_size'],
        );
    };

    $previewElements = [
        'header' => [
            'visible' => $header_text !== '',
            'content' => $header_text,
            'classes' => 'font-bold',
        ],

        'name' => [
            'visible' => true,
            'content' => $preview_values['employee_name'],
            'classes' => 'font-bold text-blue-700',
        ],

        'body' => [
            'visible' => true,
            'content' => $preview_body,
            'classes' => 'whitespace-pre-line leading-relaxed',
        ],

        'signature_line' => [
            'visible' => $signature_line !== '',
            'content' => $signature_line,
            'classes' => 'leading-relaxed',
        ],

        'signer_label' => [
            'visible' => $digital_signature_enabled,
            'content' => $signer_label ?: 'Signed by',
            'classes' => 'font-semibold leading-relaxed',
        ],

        'signer_position' => [
            'visible' => $digital_signature_enabled,
            'content' => $signer_position ?: 'Signer position',
            'classes' => 'leading-relaxed',
        ],
    ];
@endphp

<div id="certificate-template-form" class="w-full space-y-6">
    {{-- Header tanpa tombol aksi --}}
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item href="{{ route('certificate-templates.index') }}">Certificate Templates</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $template_id ? 'Edit' : 'New' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">
            {{ $template_id ? 'Edit Certificate Template' : 'New Certificate Template' }}
        </flux:heading>

        {{-- <flux:subheading class="mt-1">
            Perubahan form akan langsung tampil pada preview.
        </flux:subheading> --}}
    </div>

    <flux:separator variant="subtle" />

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2">
            <flux:card class="space-y-7">
                <div>
                    <flux:heading size="lg">
                        Template Content
                    </flux:heading>

                    <flux:text class="mt-1 text-xs">
                        Digunakan oleh training yang mengaktifkan
                        sertifikat.
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Template Name
                    </flux:label>

                    <flux:input wire:model.live.debounce.300ms="name" placeholder="Corporate Completion"
                        class="text-sm" />

                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Kind
                    </flux:label>

                    <flux:select wire:model.live="kind" class="text-sm">
                        @foreach ($kind_options as $value => $label)
                            <flux:select.option value="{{ $value }}">
                                {{ $label }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:error name="kind" />
                </flux:field>

                <div class="space-y-3">
                    <div>
                        <flux:label class="font-bold text-xs">
                            Design
                        </flux:label>

                        <flux:text class="mt-1 text-xs">
                            Pilih desain bawaan atau unggah
                            background sendiri.
                        </flux:text>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        @foreach ($design_options as $value => $option)
                            <button type="button" wire:click="$set('design', '{{ $value }}')"
                                @class([
                                    'rounded-lg border p-4 text-left transition',
                                
                                    'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950/30' =>
                                        $design === $value,
                                
                                    'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700' =>
                                        $design !== $value,
                                ])>
                                <div class="font-bold text-sm">
                                    {{ $option['label'] }}
                                </div>

                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $option['description'] }}
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <flux:error name="design" />
                </div>

                @if ($design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD)
                    <flux:field>
                        <flux:label class="font-bold text-xs">
                            Custom Background
                        </flux:label>

                        <flux:input type="file" wire:model="custom_background" accept="image/jpeg,image/png"
                            class="text-sm" />

                        <flux:text class="mt-1 text-xs">
                            JPG/PNG, maksimal 5 MB, rasio A4
                            landscape.
                        </flux:text>

                        @if ($existing_custom_background_path && !$custom_background)
                            <flux:badge size="sm" color="emerald" class="mt-2">
                                Existing background available
                            </flux:badge>
                        @endif

                        <div wire:loading wire:target="custom_background" class="mt-2 text-xs text-blue-600">
                            Uploading preview...
                        </div>

                        <flux:error name="custom_background" />
                    </flux:field>
                @endif

                <flux:separator variant="subtle" />

                <div>
                    <flux:heading size="sm">
                        Certificate Text
                    </flux:heading>

                    <flux:text class="mt-1 text-xs">
                        Header dan Signature Line boleh dikosongkan.
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Header Text
                    </flux:label>

                    <flux:input wire:model.live.debounce.300ms="header_text" placeholder="Optional" class="text-sm" />

                    <flux:text class="mt-1 text-xs">
                        Kosongkan untuk menyembunyikan header.
                    </flux:text>

                    <flux:error name="header_text" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Body
                    </flux:label>

                    <flux:textarea wire:model.live.debounce.300ms="body_text" rows="5" resize="vertical"
                        class="text-sm" />

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($supported_variables as $variable)
                            <flux:button type="button" variant="subtle" size="sm"
                                wire:click="insertVariable('{{ $variable }}')" class="font-mono text-xs">
                                &#123;&#123;{{ $variable }}&#125;&#125;
                            </flux:button>
                        @endforeach
                    </div>

                    <flux:error name="body_text" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Signature Line
                    </flux:label>

                    <flux:input wire:model.live.debounce.300ms="signature_line"
                        placeholder="Authorised by Learning Team" class="text-sm" />

                    <flux:text class="mt-1 text-xs">
                        Kosongkan untuk menyembunyikan Signature Line.
                    </flux:text>

                    <flux:error name="signature_line" />
                </flux:field>

                <flux:separator variant="subtle" />

                <div>
                    <flux:heading size="sm">
                        Digital Signature
                    </flux:heading>

                    <flux:text class="mt-1 text-xs">
                        Informasi signer ditampilkan sebagai
                        elemen terpisah.
                    </flux:text>
                </div>

                <flux:switch wire:model.live="digital_signature_enabled" label="Enable digital signature integration"
                    description="Tampilkan Signer Label dan Signer Position." />

                @if ($digital_signature_enabled)
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label class="font-bold text-xs">
                                Signer Label
                            </flux:label>

                            <flux:input wire:model.live.debounce.300ms="signer_label" placeholder="Signed by"
                                class="text-sm" />

                            <flux:error name="signer_label" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="font-bold text-xs">
                                Signer Position
                            </flux:label>

                            <flux:input wire:model.live.debounce.300ms="signer_position" placeholder="HR Director"
                                class="text-sm" />

                            <flux:error name="signer_position" />
                        </flux:field>
                    </div>
                @endif

                <flux:separator variant="subtle" />

                <div>
                    <flux:heading size="sm">
                        Element Styles
                    </flux:heading>

                    <flux:text class="mt-1 text-xs">
                        Font dan text alignment diatur secara
                        independen untuk setiap elemen.
                    </flux:text>
                </div>

                <div class="space-y-3">
                    @foreach ($element_options as $key => $element)
                        @if (!$element['digital_only'] || $digital_signature_enabled)
                            <div wire:key="style-control-{{ $key }}"
                                class="rounded-lg border border-zinc-200
                                       p-4 dark:border-zinc-700">
                                <div class="mb-3 font-semibold text-sm">
                                    {{ $element['label'] }}
                                </div>

                                <div
                                    class="grid grid-cols-1 gap-3
                                           md:grid-cols-3">
                                    <flux:field>
                                        <flux:label class="text-xs">
                                            Font Family
                                        </flux:label>

                                        <flux:select wire:model.live="layout_settings.{{ $key }}.font_family"
                                            class="text-sm">
                                            @foreach ($font_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">
                                                    {{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:error name="layout_settings.{{ $key }}.font_family" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs">
                                            Font Size
                                        </flux:label>

                                        <flux:input type="number" min="8" max="72" step="1"
                                            wire:model.live.debounce.250ms="layout_settings.{{ $key }}.font_size"
                                            class="text-sm" />

                                        <flux:error name="layout_settings.{{ $key }}.font_size" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs">
                                            Text Align
                                        </flux:label>

                                        <flux:select wire:model.live="layout_settings.{{ $key }}.text_align"
                                            class="text-sm">
                                            @foreach ($alignment_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">
                                                    {{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:error name="layout_settings.{{ $key }}.text_align" />
                                    </flux:field>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="rounded-lg border border-zinc-200
           p-4 dark:border-zinc-700">
                    <div class="mb-3">
                        <div class="font-semibold text-sm">
                            Certificate Dates
                        </div>

                        <div class="mt-1 text-xs text-zinc-500">
                            Atur informasi tanggal yang ditampilkan
                            pada sertifikat.
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:switch wire:model.live="layout_settings.issued_on.enabled" label="Show Issued On"
                            description="Tampilkan tanggal penerbitan." />

                        <flux:switch wire:model.live="layout_settings.expires_at.enabled" label="Show Expires At"
                            description="Tampilkan tanggal kedaluwarsa." />
                    </div>

                    <flux:error name="layout_settings.issued_on.enabled" />

                    <flux:error name="layout_settings.expires_at.enabled" />
                </div>

                <flux:checkbox wire:model="is_default" label="Make this the default for this certificate kind"
                    description="Replaces the current default." />

                <flux:error name="save" />

                <div
                    class="flex items-center gap-2 border-t
                           border-zinc-200 pt-5 dark:border-zinc-800">
                    <flux:spacer />

                    <flux:button href="{{ route('certificate-templates.index') }}" wire:navigate variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled"
                        wire:target="save,custom_background">
                        <span wire:loading.remove wire:target="save">
                            Save Template
                        </span>

                        <span wire:loading wire:target="save">
                            Saving...
                        </span>
                    </flux:button>
                </div>
            </flux:card>

            <flux:card class="space-y-4 xl:sticky xl:top-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="sm">
                            Live Preview
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Drag untuk memindahkan. Tarik sisi
                            kiri atau kanan untuk mengubah width.
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button type="button" size="sm" variant="ghost" wire:click="resetLayout">
                            Reset
                        </flux:button>

                        <flux:badge size="sm" color="blue">
                            A4 Landscape
                        </flux:badge>
                    </div>
                </div>

                <div data-layout-canvas @class([
                    'relative aspect-[297/210] overflow-hidden rounded-lg border bg-white text-slate-900 shadow-sm',
                
                    'border-blue-700' =>
                        $design === \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE,
                
                    'border-zinc-300' =>
                        $design !== \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE,
                ])>
                    {{-- Custom background memakai img agar stabil saat edit --}}
                    @if ($design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD && $preview_background_url)
                        <img src="{{ $preview_background_url }}" alt=""
                            class="pointer-events-none absolute inset-0
                                   h-full w-full object-cover">
                    @endif

                    @if ($design === \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE)
                        <div
                            class="pointer-events-none absolute
                                   inset-x-0 top-0 h-8 bg-blue-700">
                        </div>

                        <div
                            class="pointer-events-none absolute
                                   inset-x-0 bottom-0 h-3 bg-blue-700">
                        </div>
                    @endif

                    @if ($design === \App\Models\CertificateTemplate::DESIGN_MINIMAL_ACADEMIC)
                        <div
                            class="pointer-events-none absolute inset-3
                                   rounded border-4 border-double
                                   border-amber-700">
                        </div>
                    @endif

                    @if ($design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD && !$preview_background_url)
                        <div
                            class="pointer-events-none absolute inset-0
                                   grid place-items-center bg-zinc-100">
                            <div class="text-center text-zinc-400">
                                <flux:icon.photo class="mx-auto h-10 w-10" />

                                <div class="mt-2 text-xs">
                                    Upload an A4 landscape background
                                </div>
                            </div>
                        </div>
                    @endif

                    @foreach ($previewElements as $key => $element)
                        @if ($element['visible'])
                            <div wire:key="preview-element-{{ $key }}"
                                data-layout-item="{{ $key }}"
                                class="
                                    certificate-layout-item
                                    {{ $element['classes'] }}
                                    {{ $fontClasses[$layout_settings[$key]['font_family']] }}
                                    {{ $layout_settings[$key]['text_align'] }}
                                "
                                style="{{ $layoutStyle($key) }}">
                                {{ $element['content'] }}

                                <span
                                    class="certificate-resize-handle
                                           certificate-resize-left"></span>

                                <span
                                    class="certificate-resize-handle
                                           certificate-resize-right"></span>
                            </div>
                        @endif
                    @endforeach

                    @if ($layout_settings['issued_on']['enabled'] || $layout_settings['expires_at']['enabled'])
                        <div class="pointer-events-none absolute inset-x-0 bottom-9 text-center text-xs">
                            @if ($layout_settings['issued_on']['enabled'])
                                <div>
                                    Issued:
                                    {{ $preview_values['issued_on'] }}
                                </div>
                            @endif

                            @if ($layout_settings['expires_at']['enabled'])
                                <div>
                                    Valid until:
                                    {{ $preview_values['expires_at'] }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div
                        class="pointer-events-none absolute inset-x-0
                               bottom-4 text-center text-xs
                               text-zinc-500">
                        {{ $preview_values['certificate_id'] }}
                    </div>
                </div>

                <flux:error name="layout_settings" />

                {{-- <flux:callout icon="information-circle" color="blue">
                    <flux:callout.heading>
                        Preview only
                    </flux:callout.heading>

                    <flux:callout.text class="text-xs">
                        Posisi dan width disimpan sebagai persentase.
                        Font size disimpan dalam pixel.
                    </flux:callout.text>
                </flux:callout> --}}
            </flux:card>
        </div>
    </form>
</div>

@script
    <script>
        const root = $wire.$el
        const gridSize = 10
        const minimumWidth = 80
        const maximumYPercent = 95

        let interactables = []
        let frame = null

        const toNumber = value =>
            Number.parseFloat(value) || 0

        const clamp = (value, min, max) =>
            Math.max(min, Math.min(max, value))

        const round = value =>
            Number(value.toFixed(3))

        function destroyInteractables() {
            interactables.forEach(item => item.unset())
            interactables = []
        }

        function dragMove(event) {
            const element = event.target
            const x = toNumber(element.dataset.x) + event.dx
            const y = toNumber(element.dataset.y) + event.dy

            element.dataset.x = x
            element.dataset.y = y

            element.style.transform =
                `translate(${x}px, ${y}px)`
        }

        function resizeMove(event) {
            const element = event.target

            const x =
                toNumber(element.dataset.x) +
                event.deltaRect.left

            const y =
                toNumber(element.dataset.y)

            element.dataset.x = x
            element.style.width = `${event.rect.width}px`

            element.style.transform =
                `translate(${x}px, ${y}px)`
        }

        function persist(element) {
            const canvas =
                element.closest('[data-layout-canvas]')

            if (!canvas) {
                return
            }

            const canvasRect =
                canvas.getBoundingClientRect()

            const elementRect =
                element.getBoundingClientRect()

            const key =
                element.dataset.layoutItem

            let width =
                elementRect.width /
                canvasRect.width *
                100

            let x =
                (
                    elementRect.left -
                    canvasRect.left
                ) /
                canvasRect.width *
                100

            let y =
                (
                    elementRect.top -
                    canvasRect.top
                ) /
                canvasRect.height *
                100

            width = clamp(width, 10, 100)
            x = clamp(x, 0, 100 - width)
            y = clamp(
                y,
                0,
                maximumYPercent
            )

            const layout = JSON.parse(
                JSON.stringify(
                    $wire.get('layout_settings') || {}
                )
            )

            layout[key] = {
                ...layout[key],
                x: round(x),
                y: round(y),
                width: round(width),
            }

            $wire.set('layout_settings', layout)
        }

        function initialize() {
            const canvas =
                root.querySelector('[data-layout-canvas]')

            if (
                !canvas ||
                typeof interact === 'undefined'
            ) {
                return
            }

            destroyInteractables()

            canvas
                .querySelectorAll('[data-layout-item]')
                .forEach(element => {
                    const key =
                        element.dataset.layoutItem

                    const settings =
                        $wire.get(
                            `layout_settings.${key}`
                        )

                    if (!settings) {
                        return
                    }

                    const x =
                        settings.x /
                        100 *
                        canvas.clientWidth

                    const y =
                        settings.y /
                        100 *
                        canvas.clientHeight

                    element.style.left = '0'
                    element.style.top = '0'
                    element.style.width =
                        `${settings.width}%`

                    element.style.transform =
                        `translate(${x}px, ${y}px)`

                    element.dataset.x = x
                    element.dataset.y = y

                    const instance =
                        interact(element)
                        .draggable({
                            inertia: true,

                            ignoreFrom: '.certificate-resize-handle',

                            modifiers: [
                                interact.modifiers.snap({
                                    targets: [
                                        interact.snappers.grid({
                                            x: gridSize,
                                            y: gridSize,
                                        }),
                                    ],
                                    range: Infinity,
                                }),

                                interact.modifiers.restrict({
                                    restriction: canvas,

                                    elementRect: {
                                        top: 0,
                                        left: 0,
                                        bottom: 1,
                                        right: 1,
                                    },

                                    endOnly: true,
                                }),
                            ],

                            listeners: {
                                start(event) {
                                    event.target.classList.add(
                                        'is-active'
                                    )
                                },

                                move: dragMove,

                                end(event) {
                                    event.target.classList.remove(
                                        'is-active'
                                    )

                                    persist(event.target)
                                },
                            },
                        })
                        .resizable({
                            inertia: true,

                            edges: {
                                left: '.certificate-resize-left',

                                right: '.certificate-resize-right',

                                top: false,
                                bottom: false,
                            },

                            modifiers: [
                                interact.modifiers.snapSize({
                                    targets: [
                                        interact.snappers.grid({
                                            width: gridSize,
                                            height: 1,
                                        }),
                                    ],
                                    range: Infinity,
                                }),

                                interact.modifiers.restrictEdges({
                                    outer: canvas,
                                }),

                                interact.modifiers.restrictSize({
                                    min: {
                                        width: minimumWidth,
                                    },
                                }),
                            ],

                            listeners: {
                                start(event) {
                                    event.target.classList.add(
                                        'is-active'
                                    )
                                },

                                move: resizeMove,

                                end(event) {
                                    event.target.classList.remove(
                                        'is-active'
                                    )

                                    persist(event.target)
                                },
                            },
                        })

                    interactables.push(instance)
                })
        }

        function scheduleInitialize() {
            if (frame !== null) {
                cancelAnimationFrame(frame)
            }

            frame = requestAnimationFrame(() => {
                frame = null
                initialize()
            })
        }

        scheduleInitialize()

        Livewire.hook('morph.updated', ({
            el
        }) => {
            if (
                el === root ||
                root.contains(el)
            ) {
                scheduleInitialize()
            }
        })

        window.addEventListener(
            'resize',
            scheduleInitialize
        )
    </script>
@endscript