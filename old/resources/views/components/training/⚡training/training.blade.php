<div id="training-management-content" class="relative w-full">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">Training Data Management</flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Training Management System.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
            @can(\App\Support\Auth\Permissions::CREATE_TRAINING)
                <flux:button
                    wire:click="$dispatch(
                        'open-training-batch-form',
                        { createNew: true }
                    )"
                    variant="primary" icon="plus" size="sm" class="font-bold text-xs uppercase">
                    Buat Training
                </flux:button>

                <flux:button wire:click="$set('show_import_modal', true)" variant="filled" icon="arrow-up-tray"
                    size="sm" class="font-bold text-xs uppercase">
                    Import
                </flux:button>
            @endcan
        </div>
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="mt-6 space-y-6">

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="activity_filter" size="sm" placeholder="Semua Activity">
                        <flux:select.option value="">Semua Activity</flux:select.option>
                        <flux:select.option value="Internal">Internal</flux:select.option>
                        <flux:select.option value="External">External</flux:select.option>
                    </flux:select>
                </div>

                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="skill_filter" size="sm" placeholder="Semua Skill">
                        <flux:select.option value="">Semua Skill</flux:select.option>
                        <flux:select.option value="Hard Skill">Hard Skill</flux:select.option>
                        <flux:select.option value="Soft Skill">Soft Skill</flux:select.option>
                    </flux:select>
                </div>

                @if ($search !== '' || $activity_filter !== '' || $skill_filter !== '')
                    <flux:button type="button" variant="subtle" size="sm" wire:click="clearFilters"
                        class="font-black uppercase text-xs">
                        Reset
                    </flux:button>
                @endif
            </div>

            <div class="w-full lg:w-[340px]">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari judul, held by, trainer"
                    icon="magnifying-glass" clearable size="sm" class="text-xs" />
            </div>
        </div>

        <flux:table :paginate="$trainings" pagination:scroll-to="#training-management-content">
            <flux:table.columns>
                <flux:table.column class="text-xs font-black uppercase" align="center">
                    No.
                </flux:table.column>

                <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection"
                    wire:click="sort('title')" class="text-xs font-black uppercase">
                    Training
                </flux:table.column>

                <flux:table.column sortable :sorted="$sortBy === 'training_date'" :direction="$sortDirection"
                    wire:click="sort('training_date')" class="text-xs font-black uppercase">
                    Jadwal
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Trainer
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Kategori
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Peserta
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Status
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Aksi
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($trainings as $training)
                    @php
                        $isGrouped = $training->training_group_id !== null && $training->trainingGroup !== null;

                        $batchRows = $isGrouped ? $training->trainingGroup->trainings : collect([$training]);

                        $batchRows = $batchRows
                            ->sortBy([['batch_number', 'asc'], ['training_date', 'asc'], ['id', 'asc']])
                            ->values();

                        $totalParticipants = $isGrouped
                            ? (int) $batchRows->sum('participants_count')
                            : (int) $training->participants_count;

                        $datedBatches = $batchRows
                            ->filter(fn($batch) => $batch->training_date !== null)
                            ->sortBy('training_date')
                            ->values();

                        $firstDate = $datedBatches->first()?->training_date;
                        $lastDate = $datedBatches->last()?->training_date;

                        $isExpanded =
                            $isGrouped && in_array((int) $training->training_group_id, $expanded_training_groups, true);
                    @endphp

                    <flux:table.row :key="'training-summary-'.$training->id">
                        <flux:table.cell class="text-center font-semibold text-xs tabular-nums">
                            {{ $trainings->firstItem() + $loop->index }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold uppercase text-xs leading-snug">
                                {{ $isGrouped ? $training->trainingGroup->title : $training->title }}
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @if ($isGrouped)
                                    <flux:badge size="sm" color="amber">
                                        {{ $batchRows->count() }} Sesi
                                    </flux:badge>
                                @endif

                                <flux:badge size="sm" color="zinc">
                                    {{ $training->held_by ?: '-' }}
                                </flux:badge>

                                @if ($training->fee && (float) $training->fee > 0)
                                    <flux:badge size="sm" color="blue">
                                        Rp{{ number_format((float) $training->fee, 0, ',', '.') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($firstDate)
                                <div class="text-xs font-semibold uppercase">
                                    {{ \Carbon\Carbon::parse($firstDate)->format('d M Y') }}

                                    @if (
                                        $lastDate &&
                                            \Carbon\Carbon::parse($lastDate)->format('Y-m-d') !== \Carbon\Carbon::parse($firstDate)->format('Y-m-d'))
                                        -
                                        {{ \Carbon\Carbon::parse($lastDate)->format('d M Y') }}
                                    @endif
                                </div>

                                <flux:text class="mt-1 text-xs">
                                    {{ $isGrouped
                                        ? $batchRows->count() . ' jadwal pelaksanaan'
                                        : ($training->start_time ? \Carbon\Carbon::parse($training->start_time)->format('H:i') : '--:--') .
                                            ' - ' .
                                            ($training->finish_time ? \Carbon\Carbon::parse($training->finish_time)->format('H:i') : '--:--') }}
                                </flux:text>
                            @else
                                <span class="text-xs text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($isGrouped)
                                <div class="font-semibold uppercase text-xs">
                                    Trainer per sesi
                                </div>

                                <flux:text class="mt-1 text-xs">
                                    Buka detail sesi untuk melihat trainer.
                                </flux:text>
                            @elseif ($training->trainer_employee_id)
                                <div class="font-semibold uppercase text-xs">
                                    {{ $training->trainerInternal->name ?? 'Internal Trainer' }} -
                                    {{ $training->trainerInternal->nik ?? 'NIK' }}
                                </div>

                                <flux:badge size="sm" color="emerald" class="mt-2">
                                    Internal
                                </flux:badge>
                            @elseif ($training->trainer_external_name)
                                <div class="font-semibold uppercase text-xs">
                                    {{ $training->trainer_external_name }}
                                </div>

                                <flux:badge size="sm" color="sky" class="mt-2">
                                    External
                                </flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">
                                    No Trainer
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1.5">
                                @if ($training->activity_name)
                                    <flux:badge size="sm" color="blue">
                                        {{ $training->activity_name }}
                                    </flux:badge>
                                @endif

                                @if ($training->skill_name)
                                    <flux:badge size="sm" color="indigo">
                                        {{ $training->skill_name }}
                                    </flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <flux:badge size="sm" color="zinc">
                                {{ $totalParticipants }} peserta
                            </flux:badge>

                            @if (!$isGrouped)
                                @can(\App\Support\Auth\Permissions::UPDATE_TRAINING)
                                    <flux:button type="button" variant="ghost" size="sm" icon="users"
                                        wire:click="openParticipantsModal({{ $training->id }})" inset="top bottom"
                                        class="mt-1 text-slate-500 hover:text-emerald-600" title="Kelola Peserta" />
                                @endcan
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <div class="flex flex-col items-center gap-1.5">
                                <flux:badge size="sm"
                                    :color="$training->is_certified === 'Yes'
                                                                                                                            ? 'emerald'
                                                                                                                            : 'zinc'">
                                    {{ $training->is_certified === 'Yes' ? 'Certified' : 'No Cert' }}
                                </flux:badge>

                                @if ($training->is_certified === 'Yes')
                                    @if ($training->certificateTemplate)
                                        <flux:badge size="sm"
                                            :color="
                                                                                                                                                            $training
                                                                                                                                                                ->certificateTemplate
                                                                                                                                                                ->archived_at
                                                                                                                                                                || $training
                                                                                                                                                                    ->certificateTemplate
                                                                                                                                                                    ->trashed()
                                                                                                                                                                ? 'amber'
                                                                                                                                                                : 'blue'
                                                                                                                                                        ">
                                            {{ $training->certificateTemplate->name }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">
                                            Template belum dipilih
                                        </flux:badge>
                                    @endif
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center justify-center gap-1">
                                @if ($isGrouped)
                                    @can(\App\Support\Auth\Permissions::CREATE_TRAINING)
                                        <flux:button type="button" variant="ghost" size="sm" icon="plus"
                                            wire:click="$dispatch(
                                                'open-training-batch-form',
                                                {
                                                    trainingGroupId:
                                                        {{ (int) $training->training_group_id }}
                                                }
                                            )"
                                            inset="top bottom" class="text-slate-500 hover:text-emerald-600"
                                            title="Tambah Sesi" />
                                    @endcan

                                    <flux:button type="button" variant="ghost" size="sm"
                                        :icon="$isExpanded ? 'chevron-up' : 'chevron-down'"
                                        wire:click="toggleTrainingGroup({{ $training->training_group_id }})"
                                        inset="top bottom" class="text-slate-500 hover:text-blue-600"
                                        :title="$isExpanded ? 'Tutup Detail Sesi' : 'Lihat Detail Sesi'" />
                                @else
                                    @can(\App\Support\Auth\Permissions::UPDATE_TRAINING)
                                        <flux:button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            wire:click="$dispatch(
                                                'open-training-batch-form',
                                                {
                                                    trainingId:
                                                        {{ (int) $training->id }}
                                                }
                                            )"
                                            inset="top bottom"
                                            class="text-slate-500 hover:text-blue-600"
                                            title="Edit Training"
                                        />
                                    @endcan

                                    @can(\App\Support\Auth\Permissions::DELETE_TRAINING)
                                        <flux:modal.trigger :name="'delete-training-'.$training->id">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                inset="top bottom"
                                                class="text-slate-500 hover:text-rose-600"
                                                title="Hapus Training"
                                            />
                                        </flux:modal.trigger>
                                    @endcan
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>

                    @if ($isGrouped && $isExpanded)
                        @foreach ($batchRows as $batch)
                            <flux:table.row :key="'training-batch-'.$batch->id"
                                class="bg-zinc-50/60 dark:bg-zinc-900/40">
                                <flux:table.cell class="text-center">
                                    <span class="sr-only">Batch</span>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-start gap-2 pl-2">
                                        <flux:badge size="sm" color="amber" class="shrink-0">
                                            Sesi {{ $batch->batch_number ?: $loop->iteration }}
                                        </flux:badge>

                                        <div
                                            class="border-l-2 border-amber-300 pl-3
                                                   dark:border-amber-700">
                                            <div class="font-semibold uppercase text-xs">
                                                {{ $batch->batch_name ?: 'Sesi ' . $batch->batch_number }}
                                            </div>

                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="text-xs font-semibold uppercase">
                                        {{ $batch->training_date ? \Carbon\Carbon::parse($batch->training_date)->format('d M Y') : '-' }}
                                    </div>

                                    <flux:text class="mt-1 text-xs tabular-nums">
                                        {{ $batch->start_time ? \Carbon\Carbon::parse($batch->start_time)->format('H:i') : '--:--' }}
                                        -
                                        {{ $batch->finish_time ? \Carbon\Carbon::parse($batch->finish_time)->format('H:i') : '--:--' }}
                                    </flux:text>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($batch->trainer_employee_id)
                                        <div class="font-semibold uppercase text-xs">
                                            {{ $batch->trainerInternal->name ?? 'Internal Trainer' }} -
                                            {{ $batch->trainerInternal->nik ?? 'NIK' }}
                                        </div>

                                        <flux:badge size="sm" color="emerald" class="mt-2">
                                            Internal
                                        </flux:badge>
                                    @elseif ($batch->trainer_external_name)
                                        <div class="font-semibold uppercase text-xs">
                                            {{ $batch->trainer_external_name }}
                                        </div>

                                        <flux:badge size="sm" color="sky" class="mt-2">
                                            External
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">
                                            No Trainer
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:text class="text-xs">
                                        Mengikuti kategori utama
                                    </flux:text>
                                </flux:table.cell>

                                <flux:table.cell align="center">
                                    <div class="flex items-center justify-center gap-2">
                                        <flux:badge size="sm" color="zinc">
                                            {{ $batch->participants_count }} peserta
                                        </flux:badge>

                                        @can(\App\Support\Auth\Permissions::UPDATE_TRAINING)
                                            <flux:button type="button" variant="ghost" size="sm" icon="users"
                                                wire:click="openParticipantsModal({{ $batch->id }})"
                                                inset="top bottom" class="text-slate-500 hover:text-emerald-600"
                                                title="Kelola Peserta Sesi" />
                                        @endcan
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="center">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <flux:badge size="sm"
                                            :color="$batch->is_certified === 'Yes'
                                                            ? 'emerald'
                                                            : 'zinc'">
                                            {{ $batch->is_certified === 'Yes' ? 'Certified' : 'No Cert' }}
                                        </flux:badge>

                                        @if ($batch->is_certified === 'Yes')
                                            @if ($batch->certificateTemplate)
                                                <flux:badge size="sm"
                                                    :color="
                                                                            $batch
                                                                                ->certificateTemplate
                                                                                ->archived_at
                                                                                || $batch
                                                                                    ->certificateTemplate
                                                                                    ->trashed()
                                                                                ? 'amber'
                                                                                : 'blue'
                                                                        ">
                                                    {{ $batch->certificateTemplate->name }}
                                                </flux:badge>
                                            @else
                                                <flux:badge size="sm" color="amber">
                                                    Template belum dipilih
                                                </flux:badge>
                                            @endif
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center justify-center gap-1">
                                        @can(\App\Support\Auth\Permissions::UPDATE_TRAINING)
                                            <flux:button type="button" variant="ghost" size="sm"
                                                icon="pencil-square"
                                                wire:click="$dispatch(
                                                    'open-training-batch-form',
                                                    {
                                                        trainingId:
                                                            {{ (int) $batch->id }}
                                                    }
                                                )"
                                                inset="top bottom" class="text-slate-500 hover:text-blue-600"
                                                title="Edit Sesi" />
                                        @endcan

                                        @can(\App\Support\Auth\Permissions::DELETE_TRAINING)
                                            <flux:modal.trigger :name="'delete-training-'.$batch->id">
                                                <flux:button variant="ghost" size="sm" icon="trash"
                                                    inset="top bottom" class="text-slate-500 hover:text-rose-600"
                                                    title="Hapus Batch" />
                                            </flux:modal.trigger>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    @endif
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center py-16 font-black uppercase opacity-40">
                            Belum Ada Data Training
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @foreach ($trainings as $summaryTraining)
            @php
                $visibleDeleteRows =
                    $summaryTraining->training_group_id &&
                    $summaryTraining->trainingGroup &&
                    in_array((int) $summaryTraining->training_group_id, $expanded_training_groups, true)
                        ? $summaryTraining->trainingGroup->trainings
                        : ($summaryTraining->training_group_id
                            ? collect()
                            : collect([$summaryTraining]));
            @endphp

            @foreach ($visibleDeleteRows as $deleteTrainingRow)
                @can(\App\Support\Auth\Permissions::DELETE_TRAINING)
                    <flux:modal :name="'delete-training-'.$deleteTrainingRow->id" class="min-w-[22rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">
                                    Hapus Training?
                                </flux:heading>

                                <flux:text class="mt-2">
                                    Anda akan menghapus
                                    <span
                                        class="font-semibold text-zinc-900
                                                 dark:text-zinc-100">
                                        {{ $deleteTrainingRow->title }}
                                        @if ($deleteTrainingRow->batch_name)
                                            — {{ $deleteTrainingRow->batch_name }}
                                        @endif
                                    </span>.
                                    <br>
                                    Data peserta pada batch ini juga akan dilepas.
                                </flux:text>
                            </div>

                            <div class="flex gap-2">
                                <flux:spacer />

                                <flux:modal.close>
                                    <flux:button variant="ghost">
                                        Batal
                                    </flux:button>
                                </flux:modal.close>

                                <flux:button type="button" variant="danger"
                                    wire:click="deleteTraining({{ $deleteTrainingRow->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteTraining({{ $deleteTrainingRow->id }})">
                                    Hapus Training
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endcan
            @endforeach
        @endforeach
    </flux:card>

    @can(\App\Support\Auth\Permissions::UPDATE_TRAINING)
        <flux:modal
            wire:model.self="show_participant_modal"
            wire:close="closeParticipantsModal"
            class="w-[98vw] max-w-[98vw] xl:w-[90rem] !max-w-[90rem]"
            :dismissible="false"
        >
            <div class="flex h-[88vh] min-h-0 flex-col">
                <div class="shrink-0 border-b border-zinc-200 pb-4 dark:border-zinc-800">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <flux:heading
                                size="lg"
                                class="flex items-center gap-2 font-semibold"
                            >
                                <flux:icon.users class="h-5 w-5 shrink-0 text-emerald-600" />
                                Kelola peserta training
                            </flux:heading>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" color="blue">
                                    {{ $participant_training_title ?: 'Training belum dipilih' }}
                                </flux:badge>

                                <flux:badge size="sm" color="emerald">
                                    {{ count($selected_participants) }} peserta
                                </flux:badge>

                                @if ($this->participantHasChanges)
                                    <flux:badge size="sm" color="amber">
                                        Perubahan belum disimpan
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        @if ($this->participantHasChanges)
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($this->participantChangeSummary['added'] > 0)
                                    <flux:badge size="sm" color="emerald">
                                        +{{ $this->participantChangeSummary['added'] }} ditambahkan
                                    </flux:badge>
                                @endif

                                @if ($this->participantChangeSummary['removed'] > 0)
                                    <flux:badge size="sm" color="rose">
                                        -{{ $this->participantChangeSummary['removed'] }} dikeluarkan
                                    </flux:badge>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto py-5 xl:overflow-hidden">
                    <div class="grid min-h-0 grid-cols-1 gap-5 xl:h-full xl:grid-cols-2">
                        <section class="flex h-[34rem] min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950 xl:h-full">
                            <div class="shrink-0 space-y-4 border-b border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <flux:heading size="sm" class="font-semibold">
                                            Karyawan tersedia
                                        </flux:heading>

                                        <flux:text class="mt-1 text-xs">
                                            Pilih maksimal 20 karyawan pada setiap halaman.
                                        </flux:text>
                                    </div>

                                    <flux:badge size="sm" color="zinc">
                                        {{ $this->availableEmployees->count() }}
                                        dari
                                        {{ $this->availableEmployeeCount }}
                                    </flux:badge>
                                </div>

                                <flux:input
                                    wire:model.live.debounce.300ms="participant_search"
                                    placeholder="Cari nama atau NIK"
                                    icon="magnifying-glass"
                                    clearable
                                    size="sm"
                                    class="text-xs"
                                />

                                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <flux:field>
                                        <flux:label class="text-xs font-medium">
                                            Department
                                        </flux:label>

                                        <flux:select
                                            wire:model.live="participant_department_id"
                                            size="sm"
                                            placeholder="Semua department"
                                            class="text-xs"
                                        >
                                            <flux:select.option value="">
                                                Semua department
                                            </flux:select.option>

                                            @foreach ($departments as $department)
                                                <flux:select.option value="{{ $department->id }}">
                                                    {{ $department->org_name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs font-medium">
                                            Position
                                        </flux:label>

                                        <flux:select
                                            wire:model.live="participant_position_id"
                                            size="sm"
                                            placeholder="Semua position"
                                            class="text-xs"
                                        >
                                            <flux:select.option value="">
                                                Semua position
                                            </flux:select.option>

                                            @foreach ($positions as $position)
                                                <flux:select.option value="{{ $position->id }}">
                                                    {{ $position->position_name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>

                                    <div class="flex">
                                        @if (
                                            $participant_search !== ''
                                            || $participant_department_id !== ''
                                            || $participant_position_id !== ''
                                        )
                                            <flux:button
                                                type="button"
                                                wire:click="clearParticipantFilters"
                                                variant="subtle"
                                                size="sm"
                                                icon="x-mark"
                                            >
                                                Reset
                                            </flux:button>
                                        @else
                                            <div class="hidden h-8 w-[72px] sm:block"></div>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 rounded-xl bg-zinc-50 p-2.5 dark:bg-zinc-900/70 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <flux:button
                                            type="button"
                                            wire:click="selectVisibleAvailableEmployees"
                                            variant="ghost"
                                            size="sm"
                                            :disabled="$this->availableEmployees->isEmpty()"
                                        >
                                            Pilih semua
                                        </flux:button>

                                        @if ($available_employee_ids !== [])
                                            <flux:button
                                                type="button"
                                                wire:click="clearAvailableEmployeeSelection"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Clear
                                            </flux:button>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button
                                            type="button"
                                            wire:click="addCheckedParticipants"
                                            wire:loading.attr="disabled"
                                            wire:target="addCheckedParticipants"
                                            variant="primary"
                                            size="sm"
                                            icon="user-plus"
                                            :disabled="$available_employee_ids === []"
                                        >
                                            Tambah dipilih ({{ count($available_employee_ids) }})
                                        </flux:button>

                                        <flux:button
                                            type="button"
                                            wire:click="prepareAddAllFilteredParticipants"
                                            wire:loading.attr="disabled"
                                            wire:target="prepareAddAllFilteredParticipants"
                                            variant="filled"
                                            size="sm"
                                            icon="users"
                                            :disabled="$this->availableEmployeeCount === 0"
                                        >
                                            Tambah semua hasil
                                        </flux:button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="relative min-h-0 flex-1 overflow-y-auto overscroll-contain"
                                tabindex="0"
                                aria-label="Daftar karyawan tersedia"
                            >
                                @forelse ($this->availableEmployees as $employee)
                                    @php
                                        $availableChecked = in_array(
                                            (int) $employee->id,
                                            array_map('intval', $available_employee_ids),
                                            true
                                        );
                                    @endphp

                                    <div
                                        wire:key="available-employee-{{ $employee->id }}"
                                        @class([
                                            'group flex min-w-0 items-center gap-3 border-b border-zinc-100 px-4 py-3 transition last:border-b-0 dark:border-zinc-800',
                                            'bg-blue-50/70 dark:bg-blue-950/20' => $availableChecked,
                                            'hover:bg-zinc-50 dark:hover:bg-zinc-900/70' => !$availableChecked,
                                        ])
                                    >
                                        <input
                                            type="checkbox"
                                            wire:model.live="available_employee_ids"
                                            value="{{ $employee->id }}"
                                            aria-label="Pilih {{ $employee->name }}"
                                            class="size-4 shrink-0 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900"
                                        >

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $employee->name }}
                                            </div>

                                            <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                                                <flux:badge size="sm" color="blue">
                                                    {{ $employee->nik ?: '-' }}
                                                </flux:badge>

                                                <span class="min-w-0 truncate text-xs text-zinc-500">
                                                    {{ $employee->org_name ?? 'Tanpa department' }}
                                                </span>
                                            </div>

                                            <div class="mt-1 truncate text-xs text-zinc-400">
                                                {{ $employee->position_name ?? '-' }}
                                            </div>
                                        </div>

                                        <flux:button
                                            type="button"
                                            wire:click="addSelectedParticipant({{ $employee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="addSelectedParticipant({{ $employee->id }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-right"
                                            class="shrink-0"
                                            title="Tambahkan peserta"
                                        />
                                    </div>
                                @empty
                                    <div class="flex h-full min-h-[260px] flex-col items-center justify-center gap-3 px-6 text-center">
                                        <div class="flex size-11 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900">
                                            <flux:icon.users class="h-5 w-5 text-zinc-400" />
                                        </div>

                                        <div>
                                            <flux:heading size="sm">
                                                Tidak ada karyawan
                                            </flux:heading>

                                            <flux:text class="mt-1 max-w-sm text-xs">
                                                Ubah pencarian atau filter untuk menemukan karyawan lain.
                                            </flux:text>
                                        </div>
                                    </div>
                                @endforelse

                                <div
                                    wire:loading.flex
                                    wire:target="participant_search,participant_department_id,participant_position_id,previousAvailableParticipantPage,nextAvailableParticipantPage"
                                    class="absolute inset-0 items-center justify-center bg-white/75 dark:bg-zinc-950/75"
                                >
                                    <span class="size-6 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-800 dark:border-zinc-700 dark:border-t-white"></span>
                                </div>
                            </div>

                            <div class="shrink-0 border-t border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="flex items-center justify-between gap-3">
                                    <flux:text class="text-xs">
                                        Halaman {{ $available_participant_page }}
                                        dari {{ $this->availableEmployeeTotalPages }}
                                    </flux:text>

                                    <div class="flex items-center gap-1">
                                        <flux:button
                                            type="button"
                                            wire:click="previousAvailableParticipantPage"
                                            variant="ghost"
                                            size="sm"
                                            icon="chevron-left"
                                            :disabled="!$this->availableHasPrevious"
                                            title="Halaman sebelumnya"
                                        />

                                        <flux:button
                                            type="button"
                                            wire:click="nextAvailableParticipantPage"
                                            variant="ghost"
                                            size="sm"
                                            icon="chevron-right"
                                            :disabled="!$this->availableHasNext"
                                            title="Halaman berikutnya"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="flex h-[34rem] min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950 xl:h-full">
                            <div class="shrink-0 space-y-4 border-b border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <flux:heading size="sm" class="font-semibold">
                                            Peserta terpilih
                                        </flux:heading>

                                        <flux:text class="mt-1 text-xs">
                                            Review maksimal 20 peserta pada setiap halaman.
                                        </flux:text>
                                    </div>

                                    <flux:badge size="sm" color="emerald">
                                        {{ $this->selectedParticipantCount }}
                                        dari {{ count($selected_participants) }}
                                    </flux:badge>
                                </div>

                                <flux:input
                                    wire:model.live.debounce.300ms="selected_participant_search"
                                    placeholder="Cari peserta terpilih"
                                    icon="magnifying-glass"
                                    clearable
                                    size="sm"
                                    class="text-xs"
                                />

                                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <flux:field>
                                        <flux:label class="text-xs font-medium">
                                            Department
                                        </flux:label>

                                        <flux:select
                                            wire:model.live="selected_participant_department_id"
                                            size="sm"
                                            placeholder="Semua department"
                                            class="text-xs"
                                        >
                                            <flux:select.option value="">
                                                Semua department
                                            </flux:select.option>

                                            @foreach ($departments as $department)
                                                <flux:select.option value="{{ $department->id }}">
                                                    {{ $department->org_name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs font-medium">
                                            Position
                                        </flux:label>

                                        <flux:select
                                            wire:model.live="selected_participant_position_id"
                                            size="sm"
                                            placeholder="Semua position"
                                            class="text-xs"
                                        >
                                            <flux:select.option value="">
                                                Semua position
                                            </flux:select.option>

                                            @foreach ($positions as $position)
                                                <flux:select.option value="{{ $position->id }}">
                                                    {{ $position->position_name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>

                                    <div class="flex">
                                        @if (
                                            $selected_participant_search !== ''
                                            || $selected_participant_department_id !== ''
                                            || $selected_participant_position_id !== ''
                                        )
                                            <flux:button
                                                type="button"
                                                wire:click="clearSelectedParticipantFilters"
                                                variant="subtle"
                                                size="sm"
                                                icon="x-mark"
                                            >
                                                Reset
                                            </flux:button>
                                        @else
                                            <div class="hidden h-8 w-[72px] sm:block"></div>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 rounded-xl bg-zinc-50 p-2.5 dark:bg-zinc-900/70 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <flux:button
                                            type="button"
                                            wire:click="selectVisibleSelectedParticipants"
                                            variant="ghost"
                                            size="sm"
                                            :disabled="$this->selectedParticipantsPage->isEmpty()"
                                        >
                                            Pilih semua
                                        </flux:button>

                                        @if ($selected_employee_ids_for_removal !== [])
                                            <flux:button
                                                type="button"
                                                wire:click="clearSelectedParticipantSelection"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Clear
                                            </flux:button>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button
                                            type="button"
                                            wire:click="removeCheckedParticipants"
                                            variant="danger"
                                            size="sm"
                                            icon="user-minus"
                                            :disabled="$selected_employee_ids_for_removal === []"
                                        >
                                            Keluarkan ({{ count($selected_employee_ids_for_removal) }})
                                        </flux:button>

                                        <flux:button
                                            type="button"
                                            wire:click="prepareClearSelectedParticipants"
                                            variant="subtle"
                                            size="sm"
                                            icon="trash"
                                            :disabled="$selected_participants === []"
                                        >
                                            Kosongkan daftar
                                        </flux:button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                                tabindex="0"
                                aria-label="Daftar peserta terpilih"
                            >
                                @forelse ($this->selectedParticipantsPage as $participant)
                                    @php
                                        $selectedChecked = in_array(
                                            (int) $participant['id'],
                                            array_map('intval', $selected_employee_ids_for_removal),
                                            true
                                        );
                                    @endphp

                                    <div
                                        wire:key="selected-participant-{{ $participant['id'] }}"
                                        @class([
                                            'group flex min-w-0 items-center gap-3 border-b border-zinc-100 px-4 py-3 transition last:border-b-0 dark:border-zinc-800',
                                            'bg-rose-50/70 dark:bg-rose-950/20' => $selectedChecked,
                                            'hover:bg-zinc-50 dark:hover:bg-zinc-900/70' => !$selectedChecked,
                                        ])
                                    >
                                        <input
                                            type="checkbox"
                                            wire:model.live="selected_employee_ids_for_removal"
                                            value="{{ $participant['id'] }}"
                                            aria-label="Pilih {{ $participant['name'] }} untuk dikeluarkan"
                                            class="size-4 shrink-0 rounded border-zinc-300 text-rose-600 focus:ring-rose-500 dark:border-zinc-700 dark:bg-zinc-900"
                                        >

                                        <flux:button
                                            type="button"
                                            wire:click="removeParticipant({{ $participant['id'] }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-left"
                                            class="shrink-0"
                                            title="Keluarkan peserta"
                                        />

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $participant['name'] }}
                                            </div>

                                            <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                                                <flux:badge size="sm" color="emerald">
                                                    {{ $participant['nik'] ?: '-' }}
                                                </flux:badge>

                                                <span class="min-w-0 truncate text-xs text-zinc-500">
                                                    {{ $participant['org_name'] ?? 'Tanpa department' }}
                                                </span>
                                            </div>

                                            <div class="mt-1 truncate text-xs text-zinc-400">
                                                {{ $participant['position_name'] ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex h-full min-h-[260px] flex-col items-center justify-center gap-3 px-6 text-center">
                                        <div class="flex size-11 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900">
                                            <flux:icon.users class="h-5 w-5 text-zinc-400" />
                                        </div>

                                        <div>
                                            <flux:heading size="sm">
                                                Belum ada peserta
                                            </flux:heading>

                                            <flux:text class="mt-1 max-w-sm text-xs">
                                                Tambahkan karyawan dari panel di sebelah kiri.
                                            </flux:text>
                                        </div>
                                    </div>
                                @endforelse
                            </div>

                            <div class="shrink-0 border-t border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="flex items-center justify-between gap-3">
                                    <flux:text class="text-xs">
                                        Halaman {{ $selected_participant_page }}
                                        dari {{ $this->selectedParticipantTotalPages }}
                                    </flux:text>

                                    <div class="flex items-center gap-1">
                                        <flux:button
                                            type="button"
                                            wire:click="previousSelectedParticipantPage"
                                            variant="ghost"
                                            size="sm"
                                            icon="chevron-left"
                                            :disabled="!$this->selectedHasPrevious"
                                            title="Halaman sebelumnya"
                                        />

                                        <flux:button
                                            type="button"
                                            wire:click="nextSelectedParticipantPage"
                                            variant="ghost"
                                            size="sm"
                                            icon="chevron-right"
                                            :disabled="!$this->selectedHasNext"
                                            title="Halaman berikutnya"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <flux:error name="selected_participants" />
                </div>

                <div class="shrink-0 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                        <div class="min-w-0">
                            @if ($this->participantHasChanges)
                                <div class="flex items-center gap-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                                    <span class="size-2 shrink-0 rounded-full bg-amber-500"></span>
                                    Ada perubahan yang belum disimpan.
                                </div>
                            @else
                                <flux:text class="text-xs">
                                    Belum ada perubahan peserta.
                                </flux:text>
                            @endif
                        </div>

                        <flux:spacer />

                        <flux:button
                            type="button"
                            variant="ghost"
                            wire:click="closeParticipantsModal"
                            wire:loading.attr="disabled"
                            wire:target="saveParticipants"
                        >
                            Batal
                        </flux:button>

                        <flux:button
                            type="button"
                            variant="primary"
                            icon="check"
                            wire:click="saveParticipants"
                            wire:loading.attr="disabled"
                            wire:target="saveParticipants"
                            :disabled="!$this->participantHasChanges"
                        >
                            <span wire:loading.remove wire:target="saveParticipants">
                                Simpan {{ count($selected_participants) }} peserta
                            </span>

                            <span wire:loading wire:target="saveParticipants">
                                Menyimpan...
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            wire:model.self="show_participant_bulk_add_modal"
            class="md:w-[30rem]"
            :dismissible="false"
        >
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        Tambah semua hasil filter?
                    </flux:heading>

                    <flux:text class="mt-2 leading-relaxed">
                        Terdapat {{ number_format($pending_bulk_add_count) }}
                        karyawan tersedia. Sistem akan menambahkan
                        {{ number_format($pending_bulk_add_limit) }}
                        karyawan pada proses ini.
                    </flux:text>
                </div>

                @if ($pending_bulk_add_count > $pending_bulk_add_limit)
                    <flux:callout icon="exclamation-triangle" color="amber">
                        <flux:callout.text class="text-xs">
                            Maksimal 500 karyawan per proses. Gunakan filter yang lebih spesifik untuk memproses sisanya.
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="$set('show_participant_bulk_add_modal', false)"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="primary"
                        wire:click="confirmAddAllFilteredParticipants"
                        wire:loading.attr="disabled"
                        wire:target="confirmAddAllFilteredParticipants"
                    >
                        Tambahkan {{ number_format($pending_bulk_add_limit) }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            wire:model.self="show_participant_clear_modal"
            class="md:w-[30rem]"
            :dismissible="false"
        >
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">
                        Kosongkan daftar peserta?
                    </flux:heading>

                    <flux:text class="mt-2 leading-relaxed">
                        {{ number_format(count($selected_participants)) }}
                        peserta akan dikeluarkan dari daftar sementara.
                        Perubahan baru diterapkan setelah tombol Simpan ditekan.
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="$set('show_participant_clear_modal', false)"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="confirmClearSelectedParticipants"
                    >
                        Kosongkan daftar
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            wire:model.self="show_participant_discard_modal"
            class="md:w-[30rem]"
            :dismissible="false"
        >
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        Buang perubahan?
                    </flux:heading>

                    <flux:text class="mt-2 leading-relaxed">
                        Perubahan peserta belum disimpan.
                        Jika modal ditutup sekarang, semua perubahan akan hilang.
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="cancelDiscardParticipantChanges"
                    >
                        Kembali mengedit
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="discardParticipantChanges"
                    >
                        Buang perubahan
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan

    @can(\App\Support\Auth\Permissions::CREATE_TRAINING)
        <flux:modal
            wire:model.self="show_import_modal"
            class="md:w-[36rem]"
            :dismissible="false"
        >
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="font-semibold">
                        Import data training
                    </flux:heading>

                    <flux:text class="mt-1 text-sm">
                        Gunakan template resmi agar struktur kolom sesuai dan proses import lebih aman.
                    </flux:text>
                </div>

                <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                Template import training
                            </div>

                            <div class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                                Isi data pada file ini tanpa mengubah nama atau urutan kolom.
                            </div>
                        </div>

                        <flux:button
                            href="{{ asset('Template_Import_Training.xlsx') }}"
                            download="Template_Import_Training.xlsx"
                            variant="subtle"
                            size="sm"
                            icon="arrow-down-tray"
                            class="shrink-0"
                        >
                            Unduh template
                        </flux:button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                            1. Unduh
                        </div>

                        <div class="mt-1 text-xs leading-relaxed text-zinc-500">
                            Gunakan template yang tersedia.
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                            2. Lengkapi
                        </div>

                        <div class="mt-1 text-xs leading-relaxed text-zinc-500">
                            Isi data tanpa mengubah header.
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                            3. Import
                        </div>

                        <div class="mt-1 text-xs leading-relaxed text-zinc-500">
                            Pilih file lalu mulai proses.
                        </div>
                    </div>
                </div>

                <form wire:submit.prevent="importExcel" class="space-y-5">
                    <flux:field>
                        <flux:label class="text-sm font-medium">
                            File training
                        </flux:label>

                        <flux:input
                            wire:model="excel_file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="text-sm"
                        />

                        <flux:text class="mt-1 text-xs">
                            Format yang didukung: XLSX, XLS, atau CSV.
                        </flux:text>

                        <flux:error name="excel_file" />
                    </flux:field>

                    <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800 sm:flex-row sm:justify-end">
                        <flux:button
                            type="button"
                            variant="ghost"
                            wire:click="$set('show_import_modal', false)"
                            wire:loading.attr="disabled"
                            wire:target="importExcel"
                        >
                            Batal
                        </flux:button>

                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="importExcel"
                        >
                            <span wire:loading.remove wire:target="importExcel">
                                Import data
                            </span>

                            <span wire:loading wire:target="importExcel">
                                Memproses...
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endcan

    @canany([
        \App\Support\Auth\Permissions::CREATE_TRAINING,
        \App\Support\Auth\Permissions::UPDATE_TRAINING,
    ])
        <livewire:training.batch-form />
    @endcanany
</div>

