<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class AuthorizedRoute
{
    public static function urlFor(User $user): string
    {
        foreach (
            self::permissionRouteMap()
            as $permission => $routeName
        ) {
            if (
                $user->can($permission)
                && Route::has($routeName)
            ) {
                return route($routeName);
            }
        }

        if (Route::has('profile.edit')) {
            return route('profile.edit');
        }

        return url('/');
    }

    /**
     * Urutan array menentukan prioritas landing page.
     *
     * @return array<string, string>
     */
    public static function permissionRouteMap(): array
    {
        return [
            Permissions::VIEW_DASHBOARD =>
            'dashboard',

            Permissions::VIEW_TRAINING =>
            'trainingdata',

            Permissions::VIEW_CERTIFICATE =>
            'certificates.index',

            Permissions::VIEW_CERTIFICATE_TEMPLATE =>
            'certificate-templates.index',

            Permissions::VIEW_TRAINING_REPORT =>
            'trainingdetail',

            Permissions::VIEW_EMPLOYEE =>
            'employee',

            Permissions::VIEW_MANAGEMENT_DATA =>
            'managementdata',

            Permissions::VIEW_TRAINER_CONTRIBUTION =>
            'trnc',

            Permissions::VIEW_AVERAGE_TRAINING =>
            'avg',

            Permissions::VIEW_TRAINING_PENETRATION =>
            'trnp',

            Permissions::MANAGE_USERS =>
            'user-management',

            Permissions::MANAGE_ROLES =>
            'user-management',
        ];
    }
}
