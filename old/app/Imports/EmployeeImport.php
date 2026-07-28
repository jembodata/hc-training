<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Organization;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class EmployeeImport implements ToModel, WithHeadingRow, WithCustomCsvSettings
{
    // Tambahkan ini karena fungsi export kamu pakai ";" (Semicolon)
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';'
        ];
    }

    public function model(array $row)
    {
        // 1. Ambil NIK dari kolom 'NIK'
        $nik = $row['nik'] ?? null;
        
        if (!$nik) return null;

        // 2. Mapping Organisasi (Berdasarkan kolom 'Organization')
        $orgName = strtoupper(trim($row['organization'] ?? 'DEFAULT ORG'));
        $organization = Organization::firstOrCreate(['org_name' => $orgName]);

        // 3. Mapping Posisi (Berdasarkan kolom 'Position')
        $posName = strtoupper(trim($row['position'] ?? 'DEFAULT POS'));
        $position = Position::firstOrCreate(['position_name' => $posName]);

        // 4. Simpan ke Database
        return Employee::updateOrCreate(
            ['nik' => (string)$nik], 
            [
                'name'            => trim($row['nama_karyawan'] ?? 'No Name'), 
                'org_id'          => $organization->id,
                'position_id'     => $position->id,
                'status_employee' => trim($row['status_employee'] ?? 'Permanent'),
                'status'          => trim($row['system_status'] ?? 'Active'), 
            ]
        );
    }
}