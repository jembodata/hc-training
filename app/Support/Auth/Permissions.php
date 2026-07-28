<?php

namespace App\Support\Auth;

final class Permissions
{
    // Dashboard
    public const VIEW_DASHBOARD = 'view-dashboard';

    // Training
    public const VIEW_TRAINING = 'view-training';
    public const UPDATE_TRAINING = 'update-training';
    public const CREATE_TRAINING = 'create-training';
    public const DELETE_TRAINING = 'delete-training';
    public const IMPORT_TRAINING = 'import-training';

    // Certificate Templates
    public const VIEW_CERTIFICATE_TEMPLATE = 'view-certificate-template';
    public const UPDATE_CERTIFICATE_TEMPLATE = 'update-certificate-template';
    public const CREATE_CERTIFICATE_TEMPLATE = 'create-certificate-template';
    public const ARCHIVE_CERTIFICATE_TEMPLATE = 'archive-certificate-template';

    // Issued Certificates
    public const VIEW_CERTIFICATE = 'view-certificate';
    public const DOWNLOAD_CERTIFICATE = 'download-certificate';
    public const REVOKE_CERTIFICATE = 'revoke-certificate';
    public const ISSUE_CERTIFICATE = 'issue-certificate';
    public const REISSUE_CERTIFICATE = 'reissue-certificate';

    // Average Training
    public const VIEW_AVERAGE_TRAINING = 'view-average-training';
    public const EXPORT_AVERAGE_TRAINING = 'export-average-training';

    // Training Detail
    public const VIEW_TRAINING_DETAIL = 'view-training-detail';
    public const UPDATE_TRAINING_DETAIL_NILAI =
        'update-training-detail-nilai';
    public const EXPORT_TRAINING_DETAIL = 'export-training-detail';

    // Training Penetration
    public const VIEW_TRAINING_PENETRATION =
        'view-training-penetration';
    public const EXPORT_TRAINING_PENETRATION =
        'export-training-penetration';

    // Training Contribution
    public const VIEW_TRAINING_CONTRIBUTION =
        'view-training-contribution';
    public const EXPORT_TRAINING_CONTRIBUTION =
        'export-training-contribution';

    // Employee
    public const VIEW_EMPLOYEE = 'view-employee';
    public const UPDATE_EMPLOYEE = 'update-employee';
    public const CREATE_EMPLOYEE = 'create-employee';
    public const DELETE_EMPLOYEE = 'delete-employee';
    public const IMPORT_EMPLOYEE = 'import-employee';
    public const EXPORT_EMPLOYEE = 'export-employee';

    // User
    public const CREATE_USER = 'create-user';
    public const UPDATE_USER = 'update-user';
    public const VIEW_USER = 'view-user';
    public const DELETE_USER = 'delete-user';

    // Role
    public const CREATE_ROLE = 'create-role';
    public const UPDATE_ROLE = 'update-role';
    public const VIEW_ROLE = 'view-role';
    public const DELETE_ROLE = 'delete-role';

    // Department / Position
    public const VIEW_DEPARTMENT_POSITION_DATA =
        'view-department-position-data';
    public const UPDATE_DEPARTMENT_POSITION_DATA =
        'update-department-position-data';
    public const CREATE_DEPARTMENT_POSITION_DATA =
        'create-department-position-data';
    public const DELETE_DEPARTMENT_POSITION_DATA =
        'delete-department-position-data';

    /**
     * Permission aktif yang tersedia pada role editor.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,

            self::VIEW_TRAINING,
            self::UPDATE_TRAINING,
            self::CREATE_TRAINING,
            self::DELETE_TRAINING,
            self::IMPORT_TRAINING,

            self::VIEW_CERTIFICATE_TEMPLATE,
            self::UPDATE_CERTIFICATE_TEMPLATE,
            self::CREATE_CERTIFICATE_TEMPLATE,
            self::ARCHIVE_CERTIFICATE_TEMPLATE,

            self::VIEW_CERTIFICATE,
            self::DOWNLOAD_CERTIFICATE,
            self::REVOKE_CERTIFICATE,
            self::ISSUE_CERTIFICATE,
            self::REISSUE_CERTIFICATE,

            self::VIEW_AVERAGE_TRAINING,
            self::EXPORT_AVERAGE_TRAINING,

            self::VIEW_TRAINING_DETAIL,
            self::UPDATE_TRAINING_DETAIL_NILAI,
            self::EXPORT_TRAINING_DETAIL,

            self::VIEW_TRAINING_PENETRATION,
            self::EXPORT_TRAINING_PENETRATION,

            self::VIEW_TRAINING_CONTRIBUTION,
            self::EXPORT_TRAINING_CONTRIBUTION,

            self::VIEW_EMPLOYEE,
            self::UPDATE_EMPLOYEE,
            self::CREATE_EMPLOYEE,
            self::DELETE_EMPLOYEE,
            self::IMPORT_EMPLOYEE,
            self::EXPORT_EMPLOYEE,

            self::CREATE_USER,
            self::UPDATE_USER,
            self::VIEW_USER,
            self::DELETE_USER,

            self::CREATE_ROLE,
            self::UPDATE_ROLE,
            self::VIEW_ROLE,
            self::DELETE_ROLE,

            self::VIEW_DEPARTMENT_POSITION_DATA,
            self::UPDATE_DEPARTMENT_POSITION_DATA,
            self::CREATE_DEPARTMENT_POSITION_DATA,
            self::DELETE_DEPARTMENT_POSITION_DATA,
        ];
    }

}
