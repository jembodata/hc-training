<div>
    <flux:modal
        wire:model.self="show_modal"
        wire:close="closeBatchModal"
        :dismissible="false"
        class="w-[96vw] max-w-[96vw] md:w-[72rem] xl:w-[80rem] !max-w-[80rem]"
    >
        <form
            wire:submit.prevent="save"
            class="flex max-h-[88vh] min-h-0 flex-col"
        >
            @php
                $usesSimpleSchedule =
                    in_array($mode, ['edit-standalone', 'edit-single-group'], true)
                    || ($mode === 'create' && count($batches) === 1);
            @endphp
            <div class="shrink-0 border-b border-zinc-200 pb-4 dark:border-zinc-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <flux:heading
                            size="lg"
                            class="flex items-center gap-2 font-semibold"
                        >
                            <flux:icon.academic-cap class="h-5 w-5 shrink-0 text-blue-600" />

                            @if ($mode === 'create')
                                Buat training
                            @elseif (in_array($mode, ['add', 'append-standalone'], true))
                                Tambah sesi training
                            @elseif (in_array($mode, ['edit-standalone', 'edit-single-group'], true))
                                Edit training
                            @else
                                Edit sesi training
                            @endif
                        </flux:heading>

                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            @if ($mode === 'create')
                                Lengkapi informasi training dan jadwal pelaksanaan.
                            @elseif ($mode === 'append-standalone')
                                Tambahkan sesi baru. Data lama akan otomatis dipindahkan ke training group.
                            @elseif ($mode === 'add')
                                Tambahkan jadwal baru pada training yang dipilih.
                            @elseif (in_array($mode, ['edit-standalone', 'edit-single-group'], true))
                                Perbarui informasi, jadwal, trainer, dan certificate training.
                            @else
                                Perbarui jadwal sesi, trainer, biaya, dan certificate.
                            @endif
                        </flux:text>
                    </div>

                    <div class="mr-6 flex shrink-0 flex-wrap items-center gap-2">
                        @if ($mode === 'create')
                            <flux:badge size="sm" color="blue">
                                Training baru
                            </flux:badge>
                        @elseif (in_array($mode, ['add', 'append-standalone'], true))
                            <flux:badge size="sm" color="emerald">
                                Tambah sesi
                            </flux:badge>
                        @elseif ($mode === 'edit-standalone')
                            <flux:badge size="sm" color="amber">
                                Legacy standalone
                            </flux:badge>
                        @elseif ($mode === 'edit-single-group')
                            <flux:badge size="sm" color="blue">
                                Single session
                            </flux:badge>
                        @else
                            <flux:badge size="sm" color="indigo">
                                Multi-session
                            </flux:badge>
                        @endif

                        @if ($is_certified === 'Yes')
                            <flux:badge size="sm" color="emerald">
                                Certified
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-5 pr-2">
                <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.85fr)]">
                    <div class="min-w-0 space-y-5">
                        <flux:card size="sm" class="space-y-5">
                            <div>
                                <flux:heading size="sm" class="font-semibold">
                                    Informasi training
                                </flux:heading>

                                <flux:text class="mt-1 text-xs">
                                    Identitas dan kategori utama training.
                                </flux:text>
                            </div>

                            <flux:field>
                                <flux:label class="text-sm font-medium">
                                    Judul training
                                </flux:label>

                                <flux:input
                                    wire:model="title"
                                    placeholder="Masukkan judul training"
                                    size="sm"
                                    class="text-sm"
                                    :disabled="in_array($mode, ['add', 'append-standalone', 'edit'], true)"
                                />

                                <flux:error name="title" />

                                @if (in_array($mode, ['add', 'append-standalone', 'edit'], true))
                                    <flux:text class="mt-1 text-xs">
                                        Judul mengikuti training group dan tidak dapat diubah dari form sesi.
                                    </flux:text>
                                @endif
                            </flux:field>

                            @if (in_array($mode, ['create', 'edit-standalone', 'edit-single-group'], true))
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <flux:field>
                                            <flux:label class="text-sm font-medium">
                                                Penyelenggara
                                            </flux:label>

                                            <flux:input
                                                wire:model="held_by"
                                                size="sm"
                                                class="text-sm"
                                                placeholder="Nama penyelenggara training"
                                            />

                                            <flux:error name="held_by" />
                                        </flux:field>
                                    </div>

                                    <flux:field>
                                        <flux:label class="text-sm font-medium">
                                            Activity
                                        </flux:label>

                                        <flux:select
                                            wire:model="activity_name"
                                            size="sm"
                                            placeholder="Pilih activity"
                                            class="text-sm"
                                        >
                                            <flux:select.option value="">
                                                Pilih activity
                                            </flux:select.option>

                                            <flux:select.option value="Internal">
                                                Internal
                                            </flux:select.option>

                                            <flux:select.option value="External">
                                                External
                                            </flux:select.option>
                                        </flux:select>

                                        <flux:error name="activity_name" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-sm font-medium">
                                            Skill type
                                        </flux:label>

                                        <flux:select
                                            wire:model="skill_name"
                                            size="sm"
                                            placeholder="Pilih skill"
                                            class="text-sm"
                                        >
                                            <flux:select.option value="">
                                                Pilih skill
                                            </flux:select.option>

                                            <flux:select.option value="Hard Skill">
                                                Hard skill
                                            </flux:select.option>

                                            <flux:select.option value="Soft Skill">
                                                Soft skill
                                            </flux:select.option>
                                        </flux:select>

                                        <flux:error name="skill_name" />
                                    </flux:field>
                                </div>
                            @endif
                        </flux:card>

                        <flux:card size="sm" class="space-y-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <flux:heading size="sm" class="font-semibold">
                                        {{ $usesSimpleSchedule ? 'Jadwal training' : 'Sesi training' }}
                                    </flux:heading>

                                    <flux:text class="mt-1 text-xs">
                                        @if ($usesSimpleSchedule)
                                            Atur tanggal dan waktu pelaksanaan training.
                                        @elseif ($mode === 'edit')
                                            Perbarui jadwal sesi yang dipilih.
                                        @else
                                            Tambahkan satu atau beberapa sesi sekaligus.
                                        @endif
                                    </flux:text>
                                </div>

                                @if (in_array($mode, ['create', 'add', 'append-standalone'], true))
                                    <flux:button
                                        type="button"
                                        variant="filled"
                                        size="sm"
                                        wire:click="addBatch"
                                        class="shrink-0"
                                    >
                                        Tambah sesi
                                    </flux:button>
                                @endif
                            </div>

                            <div class="space-y-4">
                                @foreach ($batches as $index => $batch)
                                    <div
                                        wire:key="batch-form-row-{{ $index }}"
                                        class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-900/40"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $usesSimpleSchedule
                                                        ? 'Jadwal'
                                                        : 'Sesi ' . ($batch_number_start + $index) }}
                                                </flux:badge>

                                                <span class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                                    {{ $usesSimpleSchedule
                                                        ? $title
                                                        : ($batch['batch_name']
                                                            ?: 'Sesi ' . ($batch_number_start + $index)) }}
                                                </span>
                                            </div>

                                            @if (in_array($mode, ['create', 'add', 'append-standalone'], true) && count($batches) > 1)
                                                <flux:button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    wire:click="removeBatch({{ $index }})"
                                                    class="shrink-0 text-zinc-400 hover:text-rose-600"
                                                    title="Hapus sesi"
                                                />
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            @if (! $usesSimpleSchedule)
                                                <div class="md:col-span-2 xl:col-span-4">
                                                    <flux:field>
                                                        <flux:label class="text-sm font-medium">
                                                            Nama sesi
                                                        </flux:label>

                                                        <flux:input
                                                            wire:model="batches.{{ $index }}.batch_name"
                                                            placeholder="Contoh: Sesi Finance"
                                                            size="sm"
                                                            class="text-sm"
                                                        />

                                                        <flux:error name="batches.{{ $index }}.batch_name" />
                                                    </flux:field>
                                                </div>
                                            @endif

                                            <div class="md:col-span-2">
                                                <flux:field>
                                                    <flux:label class="text-sm font-medium">
                                                        Tanggal training
                                                    </flux:label>

                                                    <flux:input
                                                        wire:model="batches.{{ $index }}.training_date"
                                                        type="date"
                                                        size="sm"
                                                        class="text-sm"
                                                    />

                                                    <flux:error name="batches.{{ $index }}.training_date" />
                                                </flux:field>
                                            </div>

                                            <flux:field>
                                                <flux:label class="text-sm font-medium">
                                                    Jam mulai
                                                </flux:label>

                                                <flux:input
                                                    wire:model="batches.{{ $index }}.start_time"
                                                    type="time"
                                                    size="sm"
                                                    class="text-sm"
                                                />

                                                <flux:error name="batches.{{ $index }}.start_time" />
                                            </flux:field>

                                            <flux:field>
                                                <flux:label class="text-sm font-medium">
                                                    Jam selesai
                                                </flux:label>

                                                <flux:input
                                                    wire:model="batches.{{ $index }}.finish_time"
                                                    type="time"
                                                    size="sm"
                                                    class="text-sm"
                                                />

                                                <flux:error name="batches.{{ $index }}.finish_time" />
                                            </flux:field>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <flux:error name="batches" />
                        </flux:card>
                    </div>

                    <div class="min-w-0 space-y-5 xl:sticky xl:top-0">
                        <flux:card size="sm" class="space-y-5">
                            <div>
                                <flux:heading size="sm" class="font-semibold">
                                    Certificate
                                </flux:heading>

                                <flux:text class="mt-1 text-xs">
                                    @if (in_array($mode, ['add', 'append-standalone'], true))
                                        Konfigurasi mengikuti training yang sudah ada.
                                    @elseif (in_array($mode, ['edit-standalone', 'edit-single-group'], true))
                                        Berlaku untuk training ini.
                                    @else
                                        Berlaku untuk seluruh sesi dalam training group.
                                    @endif
                                </flux:text>
                            </div>

                            @if (!in_array($mode, ['add', 'append-standalone'], true))
                                <flux:field>
                                    <flux:label class="text-sm font-medium">
                                        Certification
                                    </flux:label>

                                    <flux:select
                                        wire:model.live="is_certified"
                                        size="sm"
                                        class="text-sm"
                                    >
                                        <flux:select.option value="No">
                                            Tidak
                                        </flux:select.option>

                                        <flux:select.option value="Yes">
                                            Ya
                                        </flux:select.option>
                                    </flux:select>

                                    <flux:error name="is_certified" />
                                </flux:field>

                                @if ($is_certified === 'Yes')
                                    <flux:field>
                                        <flux:label class="text-sm font-medium">
                                            Certificate template
                                        </flux:label>

                                        <flux:select
                                            wire:model.live="certificate_template_id"
                                            size="sm"
                                            placeholder="Pilih certificate template"
                                            class="text-sm"
                                        >
                                            <flux:select.option value="">
                                                Pilih template
                                            </flux:select.option>

                                            @foreach ($certificate_templates as $template)
                                                <flux:select.option value="{{ $template->id }}">
                                                    {{ $template->name }}
                                                    — {{ ucfirst($template->kind) }}

                                                    @if ($template->is_default)
                                                        — Default
                                                    @endif
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:error name="certificate_template_id" />
                                    </flux:field>

                                    @if (
                                        $certificate_template_id &&
                                        (
                                            !$selected_certificate_template ||
                                            $selected_certificate_template->archived_at ||
                                            $selected_certificate_template->trashed()
                                        )
                                    )
                                        <flux:callout
                                            icon="exclamation-triangle"
                                            color="amber"
                                        >
                                            <flux:callout.heading>
                                                Template tidak tersedia
                                            </flux:callout.heading>

                                            <flux:callout.text class="text-xs">
                                                Template sebelumnya diarsipkan atau dihapus. Pilih template aktif.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @elseif (
                                        $certificate_template_id === null &&
                                        $certificate_templates->isEmpty()
                                    )
                                        <flux:callout
                                            icon="exclamation-triangle"
                                            color="amber"
                                        >
                                            <flux:callout.heading>
                                                Template belum tersedia
                                            </flux:callout.heading>

                                            <flux:callout.text class="text-xs">
                                                Buat certificate template aktif terlebih dahulu.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @elseif (
                                        $certificate_template_id === null &&
                                        $certificate_templates->isNotEmpty()
                                    )
                                        <flux:callout color="blue">
                                            <flux:callout.heading>
                                                Pilih template
                                            </flux:callout.heading>

                                            <flux:callout.text class="text-xs">
                                                Pilih salah satu template yang tersedia.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @endif
                                @endif
                            @else
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:badge
                                            size="sm"
                                            :color="$is_certified === 'Yes' ? 'emerald' : 'zinc'"
                                        >
                                            {{ $is_certified === 'Yes'
                                                ? 'Certified'
                                                : 'No certificate' }}
                                        </flux:badge>

                                        @if (
                                            $is_certified === 'Yes' &&
                                            $selected_certificate_template &&
                                            !$selected_certificate_template->archived_at &&
                                            !$selected_certificate_template->trashed()
                                        )
                                            <flux:badge size="sm" color="blue">
                                                {{ $selected_certificate_template->name }}
                                            </flux:badge>
                                        @endif

                                        @if (
                                            $is_certified === 'Yes' &&
                                            (
                                                !$selected_certificate_template ||
                                                $selected_certificate_template->archived_at ||
                                                $selected_certificate_template->trashed()
                                            )
                                        )
                                            <flux:badge size="sm" color="amber">
                                                Template tidak tersedia
                                            </flux:badge>
                                        @endif
                                    </div>

                                    @if (
                                        $is_certified === 'Yes' &&
                                        (
                                            !$selected_certificate_template ||
                                            $selected_certificate_template->archived_at ||
                                            $selected_certificate_template->trashed()
                                        )
                                    )
                                        <flux:text class="mt-2 text-xs text-amber-600">
                                            Edit sesi yang ada dan pilih template aktif terlebih dahulu.
                                        </flux:text>
                                    @endif
                                </div>

                                <flux:error name="certificate_template_id" />
                            @endif
                        </flux:card>

                        <flux:card size="sm" class="space-y-5">
                            <div>
                                <flux:heading size="sm" class="font-semibold">
                                    Trainer dan biaya
                                </flux:heading>

                                <flux:text class="mt-1 text-xs">
                                    Tentukan trainer dan biaya pelaksanaan.
                                </flux:text>
                            </div>

                            <flux:radio.group
                                wire:model.live="trainer_type"
                                variant="segmented"
                            >
                                <flux:radio value="internal" label="Internal" />
                                <flux:radio value="external" label="External" />
                                <flux:radio value="none" label="Tanpa trainer" />
                            </flux:radio.group>

                            @if ($trainer_type === 'internal')
                                <flux:field>
                                    <flux:label class="text-sm font-medium">
                                        Internal trainer
                                    </flux:label>

                                    @if ($show_modal)
                                        <x-ui.searchable-multi-select
                                            wire:model="trainer_employee_ids"
                                            :options="$trainer_employees"
                                            placeholder="Pilih trainer internal"
                                            search-placeholder="Cari nama atau NIK..."
                                            empty-text="Trainer tidak ditemukan."
                                            clear-label="Bersihkan"
                                            :single="true"
                                            :max="1"
                                            value-key="id"
                                            label-key="name"
                                            description-key="nik"
                                            size="sm"
                                            :id="'internal-trainer-'.$mode.'-'.($training_id ?? 'new')"
                                        />
                                    @endif

                                    <flux:text class="mt-1 text-xs text-zinc-500">
                                        Maksimal satu internal trainer.
                                    </flux:text>

                                    <flux:error name="trainer_employee_id" />
                                </flux:field>
                            @elseif ($trainer_type === 'external')
                                <flux:field>
                                    <flux:label class="text-sm font-medium">
                                        External trainer
                                    </flux:label>

                                    <flux:input
                                        wire:model="trainer_external_name"
                                        placeholder="Nama trainer external"
                                        size="sm"
                                        class="text-sm"
                                    />

                                    <flux:error name="trainer_external_name" />
                                </flux:field>
                            @else
                                <flux:callout color="zinc">
                                    <flux:callout.text class="text-xs">
                                        Training akan disimpan tanpa trainer.
                                    </flux:callout.text>
                                </flux:callout>
                            @endif

                            <flux:field>
                                <flux:label class="text-sm font-medium">
                                    Fee atau biaya
                                </flux:label>

                                <flux:input
                                    wire:model="fee"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="0"
                                    size="sm"
                                    class="text-sm"
                                />

                                <flux:error name="fee" />
                            </flux:field>
                        </flux:card>
                    </div>
                </div>
            </div>

            <div class="shrink-0 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        Pastikan jadwal, trainer, dan konfigurasi certificate sudah benar sebelum menyimpan.
                    </flux:text>

                    <flux:spacer />

                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeBatchModal"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        <span wire:loading.remove wire:target="save">
                            @if ($mode === 'create')
                                Buat training
                            @elseif (in_array($mode, ['add', 'append-standalone'], true))
                                Simpan sesi
                            @else
                                Simpan perubahan
                            @endif
                        </span>

                        <span wire:loading wire:target="save">
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
