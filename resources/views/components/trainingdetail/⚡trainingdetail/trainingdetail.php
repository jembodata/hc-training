<?php

use App\Models\TrainingParticipant;
use App\Queries\TrainingDetailReportQuery;
use App\Services\TrainingReportExportService;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    private const ROWS_PER_PAGE = 10;

    public string $search = '';

    public ?string $date_from = null;

    public ?string $date_to = null;

    /** @var list<string> */
    public array $title_filter = [];

    /** @var list<string> */
    public array $trainer_filter = [];

    /** @var array<int, int|null|string> */
    public array $scores = [];

    /** @var array<int, int|null> */
    public array $last_valid_scores = [];

    public function mount(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_DETAIL
        );
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTitleFilter(): void
    {
        $this->normalizeSingleSelect(
            $this->title_filter
        );

        $this->resetPage();
    }

    public function updatedTrainerFilter(): void
    {
        $this->normalizeSingleSelect(
            $this->trainer_filter
        );

        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->validateDateFilters();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->validateDateFilters();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'date_from',
            'date_to',
            'title_filter',
            'trainer_filter',
        ]);

        $this->resetValidation();
        $this->resetPage();

        Flux::toast(
            heading: 'Filter direset',
            text: 'Semua filter laporan telah dibersihkan.',
            variant: 'success',
            duration: 2000,
        );
    }

    public function updatedScores(
        mixed $value,
        string|int $key
    ): void {
        $participantId = (int) $key;
        $field = "scores.{$participantId}";

        try {
            $this->validateScore($value);
            $this->resetValidation($field);
        } catch (ValidationException $exception) {
            $this->addError(
                $field,
                $this->scoreValidationMessage(
                    $exception
                )
            );
        }
    }

    public function saveScore(
        int $participantId
    ): void {
        Gate::authorize(
            Permissions::UPDATE_TRAINING_DETAIL_NILAI
        );

        $field = "scores.{$participantId}";
        $value = $this->scores[$participantId]
            ?? null;

        try {
            $this->validateScore($value);

            $score = (
                $value === ''
                || $value === null
            )
                ? null
                : (int) $value;

            DB::transaction(
                function () use (
                    $participantId,
                    $score
                ): void {
                    $participant =
                        TrainingParticipant::query()
                        ->whereKey($participantId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $participant->score = $score;
                    $participant->save();
                }
            );

            $this->scores[$participantId] = $score;
            $this->last_valid_scores[$participantId] = $score;

            $this->resetValidation($field);

            Flux::toast(
                heading: 'Score diperbarui',
                text: 'Perubahan score berhasil disimpan.',
                variant: 'success',
                duration: 2000,
            );
        } catch (ValidationException $exception) {
            $message = $this->scoreValidationMessage(
                $exception
            );

            $this->addError($field, $message);
            $this->scores[$participantId] =
                $this->last_valid_scores[$participantId] ?? null;

            Flux::toast(
                heading: 'Score tidak valid',
                text: $message,
                variant: 'danger',
                duration: 3000,
            );
        }
    }

    public function exportExcel(
        TrainingReportExportService $exporter
    ): mixed {
        Gate::authorize(
            Permissions::EXPORT_TRAINING_DETAIL
        );

        $this->validateDateFilters();

        return $exporter->detail(
            $this->filters()
        );
    }

    public function exportRekap(
        TrainingReportExportService $exporter
    ): mixed {
        Gate::authorize(
            Permissions::EXPORT_TRAINING_DETAIL
        );

        $this->validateDateFilters();

        return $exporter->rekap(
            $this->filters()
        );
    }

    public function render(): View
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_DETAIL
        );

        $report = app(
            TrainingDetailReportQuery::class
        );

        $rows = $report
            ->rows($this->filters())
            ->paginate(self::ROWS_PER_PAGE);

        $stats = $report->stats(
            $this->filters()
        );

        $this->syncScoreInputs($rows);

        $totalMinutes = (float) (
            $stats->total_minutes ?? 0
        );

        return view(
            'components.trainingdetail.⚡trainingdetail.trainingdetail',
            [
                'rows' => $rows,
                'total_attendances' => (int) (
                    $stats->total_attendances ?? 0
                ),
                'total_unique_trainings' => (int) (
                    $stats->total_unique_trainings ?? 0
                ),
                'total_hours' => number_format(
                    $totalMinutes / 60,
                    1,
                    ',',
                    '.'
                ),
                'allTitles' => $report->titles(),
                'trainerList' => $report->trainers(),
            ]
        );
    }

    private function filters(): array
    {
        return [
            'search' => trim($this->search),
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'title_filter' => $this->singleSelectValue(
                $this->title_filter
            ),
            'trainer_filter' => $this->singleSelectValue(
                $this->trainer_filter
            ),
        ];
    }

    private function validateDateFilters(): void
    {
        $this->validate(
            [
                'date_from' => [
                    'nullable',
                    'date',
                ],
                'date_to' => [
                    'nullable',
                    'date',
                    'after_or_equal:date_from',
                ],
            ],
            [
                'date_from.date' =>
                'Tanggal mulai tidak valid.',
                'date_to.date' =>
                'Tanggal sampai tidak valid.',
                'date_to.after_or_equal' =>
                'Tanggal sampai tidak boleh lebih kecil dari tanggal mulai.',
            ]
        );
    }

    private function validateScore(
        mixed $value
    ): void {
        Validator::make(
            [
                'score' => $value,
            ],
            [
                'score' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:100',
                ],
            ],
            [
                'score.integer' =>
                'Score harus berupa angka bulat.',
                'score.min' =>
                'Score tidak boleh kurang dari 0.',
                'score.max' =>
                'Score tidak boleh lebih dari 100.',
            ]
        )->validate();
    }

    private function scoreValidationMessage(
        ValidationException $exception
    ): string {
        return $exception->validator
            ->errors()
            ->first('score');
    }

    private function syncScoreInputs(
        LengthAwarePaginator $rows
    ): void {
        $visibleParticipantIds = [];

        foreach ($rows as $row) {
            $participantId =
                (int) $row->participant_id;

            $visibleParticipantIds[] =
                $participantId;

            if (
                array_key_exists(
                    $participantId,
                    $this->scores
                )
            ) {
                continue;
            }

            $score = $row->score === null
                ? null
                : (int) $row->score;

            $this->scores[$participantId] =
                $score;

            $this->last_valid_scores[$participantId] = $score;
        }

        $visibleKeys = array_flip(
            $visibleParticipantIds
        );

        $this->scores = array_intersect_key(
            $this->scores,
            $visibleKeys
        );

        $this->last_valid_scores =
            array_intersect_key(
                $this->last_valid_scores,
                $visibleKeys
            );
    }

    private function normalizeSingleSelect(
        array &$values
    ): void {
        $values = collect($values)
            ->map(
                static fn(mixed $value): string =>
                trim((string) $value)
            )
            ->filter(
                static fn(string $value): bool =>
                $value !== ''
            )
            ->unique()
            ->take(1)
            ->values()
            ->all();
    }

    private function singleSelectValue(
        array $values
    ): string {
        return trim(
            (string) ($values[0] ?? '')
        );
    }
};
