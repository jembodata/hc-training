<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class TrainingPenetrationSummaryExport implements
    FromArray,
    ShouldAutoSize,
    WithEvents,
    WithStyles,
    WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly int $sumTotal,
        private readonly int $sumTrained,
        private readonly float $totalPercentage,
        private readonly string $departmentLabel,
        private readonly string $periodLabel,
        private readonly string $trainingLabel,
    ) {
    }

    public function array(): array
    {
        $data = [
            ['TRAINING PENETRATION REPORT'],
            ['Department', $this->departmentLabel],
            ['Periode', $this->periodLabel],
            ['Judul Training', $this->trainingLabel],
            [],
            [
                'Department',
                'Total Employee',
                'Sudah Training',
                'Belum Training',
                'Penetration',
            ],
        ];

        foreach ($this->rows as $row) {
            $data[] = [
                (string) $row->org_name,
                (int) $row->total_emp,
                (int) $row->trained,
                max(
                    0,
                    (int) $row->total_emp - (int) $row->trained
                ),
                (float) $row->percentage / 100,
            ];
        }

        $data[] = [
            'TOTAL',
            $this->sumTotal,
            $this->sumTrained,
            max(0, $this->sumTotal - $this->sumTrained),
            $this->totalPercentage / 100,
        ];

        return $data;
    }

    public function title(): string
    {
        return 'Summary Department';
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRow = 7 + $this->rows->count();

        $sheet->getStyle('A1:E1')->getFont()
            ->setBold(true)
            ->setSize(14);

        $sheet->getStyle('A2:A4')->getFont()
            ->setBold(true);

        $sheet->getStyle('A6:E6')->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');

        $sheet->getStyle('A6:E6')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF2563EB');

        $sheet->getStyle("A{$totalRow}:E{$totalRow}")
            ->getFont()
            ->setBold(true);

        $sheet->getStyle("A{$totalRow}:E{$totalRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFF4F4F5');

        $sheet->getStyle("B7:D{$totalRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        $sheet->getStyle("E7:E{$totalRow}")
            ->getNumberFormat()
            ->setFormatCode('0.00%');

        $sheet->getStyle("B6:E{$totalRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A1:E{$totalRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (
                AfterSheet $event
            ): void {
                $totalRow = 7 + $this->rows->count();
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:E1');
                $sheet->freezePane('A7');
                $sheet->setAutoFilter("A6:E{$totalRow}");
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(6)->setRowHeight(22);
            },
        ];
    }
}