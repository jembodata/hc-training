@php
    use App\Enums\IssuedCertificateStatus;
    use App\Support\Auth\Permissions;

    $canIssueCertificate = auth()->user()->can(Permissions::ISSUE_CERTIFICATE);

    $canDownloadCertificate = auth()->user()->can(Permissions::DOWNLOAD_CERTIFICATE);

    $canReissueCertificate = auth()->user()->can(Permissions::REISSUE_CERTIFICATE);

    $canRevokeCertificate = auth()->user()->can(Permissions::REVOKE_CERTIFICATE);

    $hasCertificateActions =
        $canDownloadCertificate || $canIssueCertificate || $canReissueCertificate || $canRevokeCertificate;
@endphp

<div class="relative w-full" @if (
    !$show_preview_modal &&
        ($tracked_certificate_ids !== [] ||
            $certificates->contains(fn($certificate) => in_array(
                    $certificate->status,
                    [IssuedCertificateStatus::PENDING, IssuedCertificateStatus::PROCESSING],
                    true)))) wire:poll.5s="checkTrackedCertificateStatuses" @endif>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                Issued Certificates
            </flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Kelola penerbitan, monitoring, download, reissue, dan revoke certificate.
            </flux:subheading>
        </div>

        @if ($canIssueCertificate)
            <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
                <flux:button type="button" wire:click="openIssue" wire:loading.attr="disabled" wire:target="openIssue"
                    variant="primary" icon="document-plus" size="sm" class="font-bold text-xs uppercase">
                    Issue Certificate
                </flux:button>
            </div>
        @endif
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="mt-6 space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="w-full sm:w-[220px]">
                    <flux:select wire:model.live="status_filter" size="sm"
                        placeholder="Pilih Status Certificate..." class="text-xs">
                        <flux:select.option value="">
                            Semua Status
                        </flux:select.option>

                        @foreach ($status_options as $status)
                            <flux:select.option value="{{ $status->value }}">
                                {{ $status->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($status_filter !== '' || $search !== '')
                    <flux:button type="button" variant="subtle" size="sm" wire:click="clearFilters"
                        wire:loading.attr="disabled" wire:target="clearFilters" class="font-black text-xs uppercase">
                        Reset
                    </flux:button>
                @endif
            </div>

            <div class="w-full lg:w-[320px]">
                <flux:input wire:model.live.debounce.300ms="search"
                    placeholder="Cari certificate, employee, atau training" icon="magnifying-glass" clearable
                    size="sm" class="text-xs" />
            </div>
        </div>

        <flux:table :paginate="$certificates">
            <flux:table.columns>
                <flux:table.column class="text-xs font-black uppercase" align="center">
                    No.
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Certificate
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Participant
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Training
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Status
                </flux:table.column>

                @if ($hasCertificateActions)
                    <flux:table.column class="text-xs font-black uppercase" align="center">
                        Aksi
                    </flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($certificates as $certificate)
                    @php
                        $badgeColor = match ($certificate->status) {
                            IssuedCertificateStatus::ISSUED => 'emerald',
                            IssuedCertificateStatus::FAILED => 'rose',
                            IssuedCertificateStatus::REVOKED => 'zinc',
                            IssuedCertificateStatus::PROCESSING => 'blue',
                            default => 'amber',
                        };
                    @endphp

                    <flux:table.row :key="$certificate->id">
                        <flux:table.cell class="text-center font-semibold text-xs tabular-nums">
                            {{ $certificates->firstItem() + $loop->index }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold text-xs">
                                {{ $certificate->certificate_number }}
                            </div>

                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Issued:
                                {{ $certificate->issued_on?->format('d M Y') ?? '-' }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold uppercase text-xs">
                                {{ $certificate->employee?->name ?? data_get($certificate->participant_snapshot, 'employee.name', '-') }}
                            </div>

                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                NIK:
                                {{ $certificate->employee?->nik ?? data_get($certificate->participant_snapshot, 'employee.nik', '-') }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold uppercase text-xs">
                                {{ $certificate->training?->title ?? data_get($certificate->participant_snapshot, 'training.title', '-') }}
                            </div>

                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Batch
                                {{ data_get($certificate->participant_snapshot, 'training.batch_number') ?: '-' }}
                                {{ data_get($certificate->participant_snapshot, 'training.batch_name') ?: '' }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$badgeColor">
                                {{ $certificate->status->label() }}
                            </flux:badge>

                            @if ($certificate->status === IssuedCertificateStatus::FAILED)
                                <div class="mt-2 max-w-64 text-xs text-rose-600 dark:text-rose-400">
                                    {{ $certificate->failure_message }}
                                </div>
                            @endif
                        </flux:table.cell>

                        @if ($hasCertificateActions)
                            <flux:table.cell>
                                <div class="flex items-center justify-center gap-1">
                                    @if ($canDownloadCertificate && $certificate->status === IssuedCertificateStatus::ISSUED)
                                        <flux:button type="button" variant="ghost" size="sm" icon="eye"
                                            wire:click="openPreview({{ $certificate->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openPreview({{ $certificate->id }})" inset="top bottom"
                                            class="text-slate-500 hover:text-blue-600" title="Preview Certificate" />

                                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray"
                                            href="{{ route('certificates.download', $certificate) }}"
                                            inset="top bottom" class="text-slate-500 hover:text-emerald-600"
                                            title="Download Certificate" />
                                    @endif

                                    @if ($canIssueCertificate && $certificate->status === IssuedCertificateStatus::FAILED)
                                        <flux:button type="button" variant="ghost" size="sm" icon="arrow-path"
                                            wire:click="retry({{ $certificate->id }})" wire:loading.attr="disabled"
                                            wire:target="retry({{ $certificate->id }})" inset="top bottom"
                                            class="text-slate-500 hover:text-amber-600" title="Retry Certificate" />
                                    @endif

                                    @if (
                                        $canReissueCertificate &&
                                            in_array($certificate->status, [IssuedCertificateStatus::ISSUED, IssuedCertificateStatus::REVOKED], true) &&
                                            $certificate->supersededBy === null)
                                        <flux:button type="button" variant="ghost" size="sm"
                                            icon="document-duplicate"
                                            wire:click="prepareReissue({{ $certificate->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="prepareReissue({{ $certificate->id }})" inset="top bottom"
                                            class="text-slate-500 hover:text-indigo-600" title="Reissue Certificate" />
                                    @endif

                                    @if ($canRevokeCertificate && $certificate->status === IssuedCertificateStatus::ISSUED)
                                        <flux:button type="button" variant="ghost" size="sm" icon="x-circle"
                                            wire:click="openRevoke({{ $certificate->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openRevoke({{ $certificate->id }})" inset="top bottom"
                                            class="text-slate-500 hover:text-rose-600" title="Revoke Certificate" />
                                    @endif
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $hasCertificateActions ? 6 : 5 }}"
                            class="py-16 text-center font-black uppercase opacity-40">
                            Belum Ada Data Issued Certificate
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($canDownloadCertificate)
        <flux:modal wire:model.self="show_preview_modal" class="w-[96vw] max-w-[96vw] md:w-[72rem]"
            :dismissible="false">
            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="flex items-center gap-2 font-black">
                            <flux:icon.document-text class="h-5 w-5 text-blue-600" />

                            Preview Certificate
                        </flux:heading>

                        <flux:text
                            class="mt-1 truncate font-bold uppercase text-slate-400 text-xs dark:text-slate-500">
                            {{ $preview_certificate_number ?: 'Certificate PDF' }}
                        </flux:text>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-900">
                    @if ($show_preview_modal && $preview_certificate_id !== null)
                        <iframe wire:key="certificate-preview-{{ $preview_certificate_id }}"
                            src="{{ route('certificates.preview', ['issuedCertificate' => $preview_certificate_id]) }}#toolbar=1&navpanes=0&view=FitH"
                            title="Preview {{ $preview_certificate_number }}"
                            class="h-[68vh] min-h-[360px] w-full bg-white sm:min-h-[480px]" loading="eager"></iframe>
                    @else
                        <div class="flex h-[68vh] min-h-[360px] items-center justify-center sm:min-h-[480px]">
                            <flux:text class="text-xs text-slate-500">
                                Menyiapkan preview PDF...
                            </flux:text>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <flux:button type="button" variant="ghost" wire:click="closePreview"
                        class="font-black uppercase text-xs">
                        Tutup
                    </flux:button>

                    @if ($preview_certificate_id !== null)
                        <flux:button variant="primary" icon="arrow-down-tray"
                            href="{{ route('certificates.download', ['issuedCertificate' => $preview_certificate_id]) }}"
                            class="font-black uppercase text-xs">
                            Download PDF
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @endif

    @if ($canIssueCertificate)
        <flux:modal wire:model.self="show_issue_modal" wire:close="closeIssue" body="scroll" class=" -translate-y-20"
            :dismissible="false">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.document-plus class="h-5 w-5 text-blue-600" />

                        Issue Certificate Baru
                    </flux:heading>

                    <flux:text class="mt-1 leading-relaxed">
                        Pilih training dan participant.
                    </flux:text>
                </div>

                <form wire:submit.prevent="issue" class="space-y-6"
                    x-on:submit="$flux.toast({
                        heading: 'Membuat certificate',
                        text: 'Permintaan sedang diperiksa dan dijadwalkan.',
                        variant: 'warning',
                    })">
                    <flux:field>
                        <flux:label class="font-black uppercase text-xs">
                            Training
                        </flux:label>

                        <flux:select wire:model.live="selected_training_id" size="sm"
                            placeholder="Pilih Training..." class="font-bold uppercase text-xs">
                            <flux:select.option value="">
                                Pilih Training
                            </flux:select.option>

                            @foreach ($trainings as $training)
                                <flux:select.option value="{{ $training->id }}">
                                    {{ $training->title }}
                                    @if ($training->batch_number)
                                        — Batch {{ $training->batch_number }}
                                    @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:error name="selected_training_id" />
                        <flux:error name="training" />
                    </flux:field>

                    @if ($selected_training_id !== null)
                        @php
                            $eligibleParticipantOptions = $participants
                                ->where('eligible', true)
                                ->map(
                                    fn(array $participant): array => [
                                        'id' => (int) $participant['id'],
                                        'name' => (string) $participant['name'],
                                        'nik' => (string) ($participant['nik'] ?: '-'),
                                    ],
                                )
                                ->values();
                        @endphp

                        <div class="space-y-3" wire:key="certificate-participant-select-{{ $selected_training_id }}">
                            <div>
                                <flux:label class="font-black uppercase text-xs">
                                    Participant
                                </flux:label>

                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $participant_summary['eligible'] }} / 
                                    {{ $participant_summary['blocked'] }} sudah memiliki
                                    proses atau certificate. 
                                    Maksimal {{ $max_bulk_issue }} participant per proses.
                                </div>
                            </div>

                            <x-ui.searchable-multi-select id="certificate-participants"
                                wire:model="selected_employee_ids" :options="$eligibleParticipantOptions" value-key="id" label-key="name"
                                description-key="nik" placeholder="Pilih participant"
                                search-placeholder="Cari nama atau NIK" empty-text="Participant tidak ditemukan."
                                select-all-label="Pilih Semua" clear-label="Bersihkan"
                                selected-suffix="participant dipilih" :max="$max_bulk_issue" />

                            <flux:error name="selected_employee_ids" />
                            <flux:error name="selected_employee_ids.*" />
                            <flux:error name="employee" />
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:card size="sm">
                            <div>
                                <flux:heading size="sm">Issued On</flux:heading>
                                <flux:text class="mt-2">{{ $automatic_issued_on->format('d M Y') }}</flux:text>
                            </div>
                        </flux:card>
                        <flux:card size="sm">
                            <div>
                                <flux:heading size="sm">Expires At</flux:heading>
                                <flux:text class="mt-2">{{ $automatic_expires_at->format('d M Y') }}</flux:text>
                            </div>
                        </flux:card>
                    </div>

                    <flux:text class="text-xs text-slate-500 dark:text-slate-400">
                        Tanggal penerbitan diisi otomatis. Certificate berlaku selama satu tahun.
                    </flux:text>

                    @if ($bulk_issue_errors !== [] && $show_issue_modal)
                        <div
                            class="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900 dark:bg-rose-950/30">
                            <div class="font-black uppercase text-xs text-rose-800 dark:text-rose-200">
                                Certificate Tidak Dapat Dijadwalkan
                            </div>

                            <ul
                                class="mt-2 max-h-32 space-y-1 overflow-y-auto text-xs text-rose-700 dark:text-rose-300">
                                @foreach ($bulk_issue_errors as $error)
                                    <li>• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <flux:spacer />

                        <flux:button type="button" variant="ghost" wire:click="closeIssue"
                            class="font-black uppercase text-xs">
                            Batal
                        </flux:button>

                        <flux:button type="submit" variant="primary" :disabled="count($selected_employee_ids) === 0"
                            wire:loading.attr="disabled" wire:target="issue" class="font-black uppercase text-xs">
                            <span wire:loading.remove wire:target="issue">
                                Queue {{ count($selected_employee_ids) }} Certificate
                            </span>

                            <span wire:loading wire:target="issue">
                                Memproses...
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif

    @if ($canReissueCertificate)
        <flux:modal wire:model.self="show_reissue_modal" class="md:w-[32rem] -translate-y-28" :dismissible="false">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg"
                        class="flex items-center gap-2 font-black uppercase text-indigo-600 dark:text-indigo-400">
                        <flux:icon.document-duplicate class="h-5 w-5 text-indigo-500" variant="outline" />

                        Reissue Certificate?
                    </flux:heading>

                    <flux:text class="mt-3 leading-relaxed text-slate-500 text-xs dark:text-slate-400">
                        Certificate

                        <span class="font-black text-slate-800 dark:text-slate-200">
                            “{{ $reissue_certificate_number ?: '-' }}”
                        </span>

                        akan diterbitkan ulang dengan nomor baru. Data participant
                        tetap menggunakan snapshot lama, sedangkan desain PDF
                        menggunakan template terbaru yang dipilih pada training.
                    </flux:text>
                </div>

                <div
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-300">
                    Certificate lama tetap disimpan sebagai riwayat audit dan
                    certificate baru akan diproses melalui queue.
                </div>

                <flux:error name="reissue_certificate_id" />
                <flux:error name="certificate" />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <flux:button type="button" variant="ghost" wire:click="closeReissue"
                        wire:loading.attr="disabled" wire:target="confirmReissue"
                        class="font-black uppercase text-xs">
                        Batal
                    </flux:button>

                    <flux:button type="button" variant="primary" wire:click="confirmReissue"
                        wire:loading.attr="disabled" wire:target="confirmReissue"
                        class="font-black uppercase text-xs">
                        <span wire:loading.remove wire:target="confirmReissue">
                            Ya, Reissue Certificate
                        </span>

                        <span wire:loading wire:target="confirmReissue">
                            Memproses...
                        </span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @if ($canRevokeCertificate)
        <flux:modal wire:model.self="show_revoke_modal" class="md:w-[32rem] -translate-y-28" :dismissible="false">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg"
                        class="flex items-center gap-2 font-black uppercase text-rose-600 dark:text-rose-400">
                        <flux:icon.x-circle class="h-5 w-5 text-rose-500" variant="outline" />

                        Revoke Certificate?
                    </flux:heading>

                    <flux:text class="mt-3 leading-relaxed text-slate-500 text-xs dark:text-slate-400">
                        Record dan file tetap disimpan, tetapi certificate tidak dapat diunduh kembali.
                    </flux:text>
                </div>

                <form wire:submit.prevent="revoke" class="space-y-6">
                    <flux:field>
                        <flux:label class="font-black uppercase text-xs">
                            Alasan Revoke
                        </flux:label>

                        <flux:textarea wire:model="revocation_reason" rows="4"
                            placeholder="Jelaskan alasan certificate dicabut..." class="font-semibold text-xs" />

                        <flux:error name="revocation_reason" />
                    </flux:field>

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <flux:button type="button" variant="ghost" wire:click="$set('show_revoke_modal', false)"
                            class="font-black uppercase text-xs">
                            Batal
                        </flux:button>

                        <flux:button type="submit" variant="danger" wire:loading.attr="disabled"
                            wire:target="revoke" class="font-black uppercase text-xs">
                            <span wire:loading.remove wire:target="revoke">
                                Ya, Revoke Certificate
                            </span>

                            <span wire:loading wire:target="revoke">
                                Memproses...
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif
</div>