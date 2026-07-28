<?php

namespace App\Support\Auth;

final class Permissions
{
    // Dashboard
    public const VIEW_DASHBOARD = 'view-dashboard';

    // Training
    public const VIEW_TRAINING = 'view-training';
    public const CREATE_TRAINING = 'create-training';
    public const UPDATE_TRAINING = 'update-training';
    public const DELETE_TRAINING = 'delete-training';

    // Training Report
    public const VIEW_TRAINING_REPORT = 'view-training-report';
    public const EXPORT_TRAINING_REPORT = 'export-training-report';
    public const UPDATE_TRAINING_SCORE = 'update-training-score';

    // Employee
    public const VIEW_EMPLOYEE = 'view-employee';
    public const CREATE_EMPLOYEE = 'create-employee';
    public const UPDATE_EMPLOYEE = 'update-employee';
    public const DELETE_EMPLOYEE = 'delete-employee';
    public const IMPORT_EMPLOYEE = 'import-employee';
    public const EXPORT_EMPLOYEE = 'export-employee';

    // Management Data
    public const VIEW_MANAGEMENT_DATA = 'view-management-data';

    // Analytics
    public const VIEW_TRAINER_CONTRIBUTION =
        'view-trainer-contribution';

    public const VIEW_AVERAGE_TRAINING =
        'view-average-training';

    public const VIEW_TRAINING_PENETRATION =
        'view-training-penetration';

    // Administration
    public const MANAGE_USERS = 'manage-users';
    public const MANAGE_ROLES = 'manage-roles';

    // Certificate Templates
    public const VIEW_CERTIFICATE_TEMPLATE =
        'view-certificate-template';

    public const CREATE_CERTIFICATE_TEMPLATE =
        'create-certificate-template';

    public const UPDATE_CERTIFICATE_TEMPLATE =
        'update-certificate-template';

    public const ARCHIVE_CERTIFICATE_TEMPLATE =
        'archive-certificate-template';

    // Issued Certificates
    public const VIEW_CERTIFICATE =
        'view-certificate';

    public const ISSUE_CERTIFICATE =
        'issue-certificate';

    public const DOWNLOAD_CERTIFICATE =
        'download-certificate';

    public const REISSUE_CERTIFICATE =
        'reissue-certificate';

    public const REVOKE_CERTIFICATE =
        'revoke-certificate';

    /*
     * Permission lama yang sudah tidak dipakai.
     * Dipertahankan sementara sebagai constant agar deployment bertahap aman.
     * Seeder akan menghapus record permission ini dari database.
     */
    public const MANAGE_ATTRIBUTE_MANAGEMENT =
        'manage-attribute-management';

    public const VIEW_HISTORY = 'view-history';

    /**
     * Permission aktif yang boleh tersedia pada role editor.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,

            self::VIEW_TRAINING,
            self::CREATE_TRAINING,
            self::UPDATE_TRAINING,
            self::DELETE_TRAINING,

            self::VIEW_TRAINING_REPORT,
            self::EXPORT_TRAINING_REPORT,
            self::UPDATE_TRAINING_SCORE,

            self::VIEW_EMPLOYEE,
            self::CREATE_EMPLOYEE,
            self::UPDATE_EMPLOYEE,
            self::DELETE_EMPLOYEE,
            self::IMPORT_EMPLOYEE,
            self::EXPORT_EMPLOYEE,

            self::VIEW_MANAGEMENT_DATA,

            self::VIEW_TRAINER_CONTRIBUTION,
            self::VIEW_AVERAGE_TRAINING,
            self::VIEW_TRAINING_PENETRATION,

            self::MANAGE_USERS,
            self::MANAGE_ROLES,

            self::VIEW_CERTIFICATE_TEMPLATE,
            self::CREATE_CERTIFICATE_TEMPLATE,
            self::UPDATE_CERTIFICATE_TEMPLATE,
            self::ARCHIVE_CERTIFICATE_TEMPLATE,

            self::VIEW_CERTIFICATE,
            self::ISSUE_CERTIFICATE,
            self::DOWNLOAD_CERTIFICATE,
            self::REISSUE_CERTIFICATE,
            self::REVOKE_CERTIFICATE,
        ];
    }

    /**
     * Permission yang harus dibersihkan dari database.
     *
     * @return list<string>
     */
    public static function obsolete(): array
    {
        return [
            self::MANAGE_ATTRIBUTE_MANAGEMENT,
            self::VIEW_HISTORY,
        ];
    }
}