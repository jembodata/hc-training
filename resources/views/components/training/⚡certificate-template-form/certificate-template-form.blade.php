@assets
    <script src="https://cdnjs.cloudflare.com/ajax/libs/interact.js/1.10.27/interact.min.js"></script>

    <style>
        .certificate-preview-page {
            position: absolute;
            inset: 0 auto auto 0;
            width: 297mm;
            height: 210mm;
            overflow: hidden;
            transform-origin: top left;
            background: #ffffff;
        }

        .certificate-layout-item {
            position: absolute;
            z-index: 20;
            box-sizing: border-box;
            min-width: 10%;
            padding: 1mm 2.5mm;
            overflow-wrap: break-word;
            cursor: move;
            touch-action: none;
            user-select: none;
            outline: 1px dashed transparent;
            outline-offset: -1px;
        }

        .certificate-layout-item:hover,
        .certificate-layout-item.is-active {
            outline-color: #2563eb;
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
            'visible' => $preview_header !== '',
            'content' => $preview_header,
            'classes' => 'font-semibold',
        ],
        'name' => [
            'visible' => true,
            'content' => $preview_values['employee_name'],
            'classes' => 'font-semibold text-blue-700',
        ],
        'body' => [
            'visible' => true,
            'content' => $preview_body,
            'classes' => 'whitespace-pre-line leading-relaxed',
        ],
        'signature_line' => [
            'visible' => $preview_signature_line !== '',
            'content' => $preview_signature_line,
            'classes' => 'leading-relaxed',
        ],
        'signer_label' => [
            'visible' => $digital_signature_enabled,
            'content' => $preview_signer_label ?: 'Signed by',
            'classes' => 'font-medium leading-relaxed',
        ],
        'signer_position' => [
            'visible' => $digital_signature_enabled,
            'content' => $preview_signer_position ?: 'Signer position',
            'classes' => 'leading-relaxed',
        ],
    ];
@endphp

<div id="certificate-template-form" class="w-full space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <flux:breadcrumbs class="mb-2">
                <flux:breadcrumbs.item
                    href="{{ route('certificate-templates.index') }}"
                    wire:navigate
                >
                    Certificate templates
                </flux:breadcrumbs.item>

                <flux:breadcrumbs.item>
                    {{ $template_id ? 'Edit template' : 'Buat template' }}
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:heading size="xl" level="1">
                {{ $template_id
                    ? 'Edit certificate template'
                    : 'Buat certificate template' }}
            </flux:heading>

            <flux:subheading class="mt-1">
                Atur isi, desain, posisi elemen, dan digital signature sambil melihat preview secara langsung.
            </flux:subheading>
        </div>

        <flux:badge
            size="sm"
            :color="$template_id ? 'zinc' : 'blue'"
        >
            {{ $template_id ? 'Edit template' : 'Template baru' }}
        </flux:badge>
    </div>

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(32rem,0.95fr)]">
            <div class="min-w-0 space-y-5">
                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Informasi template
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Identitas, jenis certificate, dan desain dasar.
                        </flux:text>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label class="text-sm font-medium">
                                Nama template
                            </flux:label>

                            <flux:input
                                wire:model.live.debounce.300ms="name"
                                placeholder="Contoh: Corporate Completion"
                                size="sm"
                                class="text-sm"
                            />

                            <flux:error name="name" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="text-sm font-medium">
                                Jenis certificate
                            </flux:label>

                            <flux:select
                                wire:model.live="kind"
                                size="sm"
                                class="text-sm"
                            >
                                @foreach ($kind_options as $value => $label)
                                    <flux:select.option value="{{ $value }}">
                                        {{ $label }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:error name="kind" />
                        </flux:field>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <flux:label class="text-sm font-medium">
                                Desain
                            </flux:label>

                            <flux:text class="mt-1 text-xs">
                                Gunakan desain bawaan atau unggah background sendiri.
                            </flux:text>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            @foreach ($design_options as $value => $option)
                                <button
                                    type="button"
                                    wire:click="$set('design', '{{ $value }}')"
                                    @class([
                                        'rounded-xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-blue-500/20',
                                        'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950/30' => $design === $value,
                                        'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-600' => $design !== $value,
                                    ])
                                >
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $option['label'] }}
                                    </div>

                                    <div class="mt-1 text-xs leading-relaxed text-zinc-500">
                                        {{ $option['description'] }}
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        <flux:error name="design" />
                    </div>

                    @if ($design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD)
                        <flux:field>
                            <flux:label class="text-sm font-medium">
                                Custom background
                            </flux:label>

                            <flux:input
                                type="file"
                                wire:model="custom_background"
                                accept="image/jpeg,image/png"
                                class="text-sm"
                            />

                            <flux:text class="mt-1 text-xs">
                                JPG atau PNG, maksimal 5 MB, orientasi A4 landscape.
                            </flux:text>

                            @if ($existing_custom_background_path && !$custom_background)
                                <flux:badge size="sm" color="emerald" class="mt-2">
                                    Background tersimpan
                                </flux:badge>
                            @endif

                            <div
                                wire:loading
                                wire:target="custom_background"
                                class="mt-2 text-xs text-blue-600"
                            >
                                Menyiapkan preview...
                            </div>

                            <flux:error name="custom_background" />
                        </flux:field>
                    @endif
                </flux:card>

                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Konten certificate
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Header dan signature line boleh dikosongkan.
                        </flux:text>
                    </div>

                    <flux:field>
                        <flux:label class="text-sm font-medium">
                            Header
                        </flux:label>

                        <flux:input
                            wire:model.live.debounce.300ms="header_text"
                            placeholder="Contoh: Certificate of Completion"
                            size="sm"
                            class="text-sm"
                        />

                        <flux:text class="mt-1 text-xs">
                            Kosongkan untuk menyembunyikan header.
                        </flux:text>

                        <flux:error name="header_text" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-sm font-medium">
                            Isi certificate
                        </flux:label>

                        <flux:textarea
                            wire:model.live.debounce.300ms="body_text"
                            rows="6"
                            resize="vertical"
                            class="text-sm"
                        />

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($supported_variables as $variable)
                                <flux:button
                                    type="button"
                                    variant="subtle"
                                    size="sm"
                                    wire:click="insertVariable('{{ $variable }}')"
                                    class="font-mono text-xs"
                                >
                                    &#123;&#123;{{ $variable }}&#125;&#125;
                                </flux:button>
                            @endforeach
                        </div>

                        <flux:text class="mt-2 text-xs">
                            Klik variable untuk menambahkannya ke bagian akhir isi certificate.
                        </flux:text>

                        <flux:error name="body_text" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-sm font-medium">
                            Signature line
                        </flux:label>

                        <flux:input
                            wire:model.live.debounce.300ms="signature_line"
                            placeholder="Contoh: Authorised by Learning Team"
                            size="sm"
                            class="text-sm"
                        />

                        <flux:text class="mt-1 text-xs">
                            Kosongkan untuk menyembunyikan signature line.
                        </flux:text>

                        <flux:error name="signature_line" />
                    </flux:field>
                </flux:card>

                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Digital signature
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Tampilkan label dan jabatan signer sebagai elemen terpisah.
                        </flux:text>
                    </div>

                    <flux:switch
                        wire:model.live="digital_signature_enabled"
                        label="Aktifkan digital signature"
                        description="Signer label dan signer position akan ditampilkan pada certificate."
                    />

                    @if ($digital_signature_enabled)
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:field>
                                <flux:label class="text-sm font-medium">
                                    Signer label
                                </flux:label>

                                <flux:input
                                    wire:model.live.debounce.300ms="signer_label"
                                    placeholder="Contoh: Signed by"
                                    size="sm"
                                    class="text-sm"
                                />

                                <flux:error name="signer_label" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium">
                                    Signer position
                                </flux:label>

                                <flux:input
                                    wire:model.live.debounce.300ms="signer_position"
                                    placeholder="Contoh: HR Director"
                                    size="sm"
                                    class="text-sm"
                                />

                                <flux:error name="signer_position" />
                            </flux:field>
                        </div>
                    @endif
                </flux:card>

                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Style elemen
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Font, ukuran, dan alignment dapat diatur untuk setiap elemen.
                        </flux:text>
                    </div>

                    <div class="space-y-3">
                        @foreach ($element_options as $key => $element)
                            @if (!$element['digital_only'] || $digital_signature_enabled)
                                <div
                                    wire:key="style-control-{{ $key }}"
                                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                                >
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $element['label'] }}
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <flux:field>
                                            <flux:label class="text-xs font-medium">
                                                Font family
                                            </flux:label>

                                            <flux:select
                                                wire:model.live="layout_settings.{{ $key }}.font_family"
                                                size="sm"
                                                class="text-sm"
                                            >
                                                @foreach ($font_options as $value => $label)
                                                    <flux:select.option value="{{ $value }}">
                                                        {{ $label }}
                                                    </flux:select.option>
                                                @endforeach
                                            </flux:select>

                                            <flux:error name="layout_settings.{{ $key }}.font_family" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label class="text-xs font-medium">
                                                Font size
                                            </flux:label>

                                            <flux:input
                                                type="number"
                                                min="8"
                                                max="80"
                                                step="1"
                                                wire:model.live.debounce.250ms="layout_settings.{{ $key }}.font_size"
                                                size="sm"
                                                class="text-sm"
                                            />

                                            <flux:error name="layout_settings.{{ $key }}.font_size" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label class="text-xs font-medium">
                                                Text align
                                            </flux:label>

                                            <flux:select
                                                wire:model.live="layout_settings.{{ $key }}.text_align"
                                                size="sm"
                                                class="text-sm"
                                            >
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
                </flux:card>

                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Tanggal dan default template
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Atur informasi tanggal serta template default untuk jenis certificate ini.
                        </flux:text>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:switch
                            wire:model.live="layout_settings.issued_on.enabled"
                            label="Tampilkan issued on"
                            description="Tanggal penerbitan akan dicetak pada certificate."
                        />

                        <flux:switch
                            wire:model.live="layout_settings.expires_at.enabled"
                            label="Tampilkan expires at"
                            description="Tanggal kedaluwarsa akan dicetak pada certificate."
                        />
                    </div>

                    <flux:error name="layout_settings.issued_on.enabled" />
                    <flux:error name="layout_settings.expires_at.enabled" />

                    <flux:separator variant="subtle" />

                    <flux:checkbox
                        wire:model="is_default"
                        label="Jadikan default untuk jenis certificate ini"
                        description="Template default sebelumnya akan digantikan."
                    />
                </flux:card>

                <flux:error name="layout_settings" />
                <flux:error name="save" />

                <div class="sticky bottom-0 z-10 -mx-1 border-t border-zinc-200 bg-white/95 px-1 py-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                        <flux:text class="text-xs">
                            Perubahan preview mengikuti input secara langsung.
                        </flux:text>

                        <flux:spacer />

                        <flux:button
                            href="{{ route('certificate-templates.index') }}"
                            wire:navigate
                            variant="ghost"
                            wire:loading.attr="disabled"
                            wire:target="save,custom_background"
                        >
                            Batal
                        </flux:button>

                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="save,custom_background"
                        >
                            <span wire:loading.remove wire:target="save">
                                {{ $template_id
                                    ? 'Simpan perubahan'
                                    : 'Buat template' }}
                            </span>

                            <span wire:loading wire:target="save">
                                Menyimpan...
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>

            <flux:card class="space-y-4 xl:sticky xl:top-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading size="lg">
                            Live preview
                        </flux:heading>

                        <flux:text class="mt-1 text-xs">
                            Drag untuk memindahkan elemen. Tarik sisi kiri atau kanan untuk mengubah lebarnya.
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            wire:click="resetLayout"
                        >
                            Reset layout
                        </flux:button>

                        <flux:badge size="sm" color="blue">
                            A4 landscape
                        </flux:badge>
                    </div>
                </div>

                <div
                    data-layout-viewport
                    @class([
                        'relative aspect-[297/210] overflow-hidden rounded-xl border bg-white shadow-sm',
                        'border-blue-700' => $design === \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE,
                        'border-zinc-300' => $design !== \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE,
                    ])
                >
                    <div
                        data-layout-canvas
                        class="certificate-preview-page font-sans text-slate-900"
                    >
                        @if (
                            $design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD
                            && $preview_background_url
                        )
                            <img
                                src="{{ $preview_background_url }}"
                                alt=""
                                class="pointer-events-none absolute inset-0 h-full w-full object-cover"
                            >
                        @endif

                        @if ($design === \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE)
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-8 bg-blue-700"></div>
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-3 bg-blue-700"></div>
                        @endif

                        @if ($design === \App\Models\CertificateTemplate::DESIGN_MINIMAL_ACADEMIC)
                            <div class="pointer-events-none absolute inset-3 rounded border-4 border-double border-amber-700"></div>
                        @endif

                        @if (
                            $design === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD
                            && !$preview_background_url
                        )
                            <div class="pointer-events-none absolute inset-0 grid place-items-center bg-zinc-100">
                                <div class="text-center text-zinc-400">
                                    <div class="text-sm font-medium">
                                        Background belum dipilih
                                    </div>

                                    <div class="mt-1 text-xs">
                                        Unggah gambar A4 landscape.
                                    </div>
                                </div>
                            </div>
                        @endif

                        @foreach ($previewElements as $key => $element)
                            @if ($element['visible'])
                                <div
                                    wire:key="preview-element-{{ $key }}"
                                    data-layout-item="{{ $key }}"
                                    class="
                                        certificate-layout-item
                                        {{ $element['classes'] }}
                                        {{ $layout_settings[$key]['font_family'] }}
                                        {{ $layout_settings[$key]['text_align'] }}
                                    "
                                    style="{{ $layoutStyle($key) }}"
                                >
                                    {{ $element['content'] }}

                                    <span class="certificate-resize-handle certificate-resize-left"></span>
                                    <span class="certificate-resize-handle certificate-resize-right"></span>
                                </div>
                            @endif
                        @endforeach

                        @if (
                            $layout_settings['issued_on']['enabled']
                            || $layout_settings['expires_at']['enabled']
                        )
                            <div class="pointer-events-none absolute inset-x-0 bottom-9 text-center text-xs">
                                @if ($layout_settings['issued_on']['enabled'])
                                    <div>
                                        Issued: {{ $preview_values['issued_on'] }}
                                    </div>
                                @endif

                                @if ($layout_settings['expires_at']['enabled'])
                                    <div>
                                        Valid until: {{ $preview_values['expires_at'] }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="pointer-events-none absolute inset-x-0 bottom-4 text-center text-xs text-zinc-500">
                            {{ $preview_values['certificate_id'] }}
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs leading-relaxed text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400">
                    Posisi dan lebar elemen disimpan sebagai persentase sehingga tetap konsisten pada hasil certificate.
                </div>
            </flux:card>
        </div>
    </form>
</div>

@script
    <script>
        const root = $wire.$el
        const gridSize = 10
        const minimumWidth = 80

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

        function canvasScale(element) {
            const canvas =
                element.closest('[data-layout-canvas]')

            return Math.max(
                0.0001,
                toNumber(canvas?.dataset.scale) || 1
            )
        }

        function dragMove(event) {
            const element = event.target
            const scale = canvasScale(element)

            const x =
                toNumber(element.dataset.x) +
                event.dx / scale

            const y =
                toNumber(element.dataset.y) +
                event.dy / scale

            element.dataset.x = x
            element.dataset.y = y

            element.style.transform =
                `translate(${x}px, ${y}px)`
        }

        function resizeMove(event) {
            const element = event.target
            const scale = canvasScale(element)

            const x =
                toNumber(element.dataset.x) +
                event.deltaRect.left / scale

            const y =
                toNumber(element.dataset.y)

            element.dataset.x = x
            element.style.width =
                `${event.rect.width / scale}px`

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
            y = clamp(y, 0, 100)

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
            const viewport =
                root.querySelector('[data-layout-viewport]')

            const canvas =
                root.querySelector('[data-layout-canvas]')

            if (
                !viewport ||
                !canvas ||
                typeof interact === 'undefined'
            ) {
                return
            }

            const scale =
                viewport.clientWidth /
                canvas.offsetWidth

            canvas.dataset.scale = scale
            canvas.style.transform =
                `scale(${scale})`

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
                        canvas.offsetWidth

                    const y =
                        settings.y /
                        100 *
                        canvas.offsetHeight

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
                                                x: gridSize * scale,
                                                y: gridSize * scale,
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
                                                width: gridSize * scale,
                                                height: scale,
                                            }),
                                        ],
                                        range: Infinity,
                                    }),
                                    interact.modifiers.restrictEdges({
                                        outer: canvas,
                                    }),
                                    interact.modifiers.restrictSize({
                                        min: {
                                            width: minimumWidth * scale,
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
