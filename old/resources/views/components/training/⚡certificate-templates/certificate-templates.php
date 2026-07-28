<?php

use App\Models\CertificateTemplate;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'archived', except: false)]
    public bool $show_archived = false;

    public function mount(): void
    {
        Gate::authorize(
            Permissions::VIEW_CERTIFICATE_TEMPLATE
        );
    }

    public function setDefault(int $templateId): void
    {
        Gate::authorize(
            Permissions::UPDATE_CERTIFICATE_TEMPLATE
        );

        $result = DB::transaction(
            function () use ($templateId): string {
                $target = CertificateTemplate::query()
                    ->findOrFail($templateId);

                $templates = CertificateTemplate::query()
                    ->where('kind', $target->kind)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $template = $templates->firstWhere(
                    'id',
                    $target->id
                );

                if (! $template) {
                    return 'missing';
                }

                if ($template->isArchived()) {
                    return 'archived';
                }

                if ($template->is_default) {
                    return 'unchanged';
                }

                CertificateTemplate::query()
                    ->where('kind', $template->kind)
                    ->whereKeyNot($template->id)
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);

                $template->update([
                    'is_default' => true,
                    'updated_by' => auth()->id(),
                ]);

                return 'changed';
            },
            attempts: 3
        );

        if ($result === 'archived') {
            $this->warningToast(
                'Template yang diarsipkan tidak dapat dijadikan default.'
            );

            return;
        }

        if ($result !== 'changed') {
            return;
        }

        $this->successToast(
            'Template berhasil dijadikan default.'
        );
    }

    public function archive(int $templateId): void
    {
        Gate::authorize(
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE
        );

        $result = DB::transaction(
            function () use ($templateId): string {
                $template = CertificateTemplate::query()
                    ->lockForUpdate()
                    ->findOrFail($templateId);

                if ($template->is_default) {
                    return 'default';
                }

                if ($template->isArchived()) {
                    return 'unchanged';
                }

                $template->update([
                    'archived_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

                return 'changed';
            },
            attempts: 3
        );

        if ($result === 'default') {
            $this->warningToast(
                'Pilih template default lain sebelum mengarsipkan template ini.'
            );

            return;
        }

        if ($result !== 'changed') {
            return;
        }

        $this->successToast(
            'Template berhasil diarsipkan.'
        );
    }

    public function restore(int $templateId): void
    {
        Gate::authorize(
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE
        );

        $changed = DB::transaction(
            function () use ($templateId): bool {
                $template = CertificateTemplate::query()
                    ->lockForUpdate()
                    ->findOrFail($templateId);

                if (! $template->isArchived()) {
                    return false;
                }

                $template->update([
                    'archived_at' => null,
                    'updated_by' => auth()->id(),
                ]);

                return true;
            },
            attempts: 3
        );

        if (! $changed) {
            return;
        }

        $this->successToast(
            'Template berhasil dipulihkan.'
        );
    }

    public function with(): array
    {
        Gate::authorize(
            Permissions::VIEW_CERTIFICATE_TEMPLATE
        );

        $search = trim($this->search);

        $templates = CertificateTemplate::query()
            ->when(
                $this->show_archived,
                fn ($query) => $query
                    ->whereNotNull('archived_at'),
                fn ($query) => $query
                    ->whereNull('archived_at'),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    'name',
                    'like',
                    "%{$search}%"
                )
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return [
            'kind_labels' => $this->kindLabels(),
            'templates_by_kind' => $templates->groupBy('kind'),
            'visible_template_count' => $templates->count(),
        ];
    }

    private function kindLabels(): Collection
    {
        return collect([
            CertificateTemplate::KIND_COMPLETION =>
                'Completion',

            CertificateTemplate::KIND_PARTICIPATION =>
                'Participation',
        ]);
    }

    private function successToast(string $text): void
    {
        Flux::toast(
            heading: 'Success',
            text: $text,
            variant: 'success',
            duration: 3000,
        );
    }

    private function warningToast(string $text): void
    {
        Flux::toast(
            heading: 'Warning',
            text: $text,
            variant: 'warning',
            duration: 3500,
        );
    }
};