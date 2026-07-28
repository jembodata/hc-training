<?php

use App\Support\Auth\AuthorizedRoute;
use App\Support\Auth\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->to(
        AuthorizedRoute::urlFor(Auth::user())
    );
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'dashboard')
        ->middleware(
            'permission:' . Permissions::VIEW_DASHBOARD
        )
        ->name('dashboard');

    Route::livewire('/employee', 'employee.create')
        ->middleware(
            'permission:' . Permissions::VIEW_EMPLOYEE
        )
        ->name('employee');

    Route::livewire('/training', 'training')
        ->middleware(
            'permission:' . Permissions::VIEW_TRAINING
        )
        ->name('trainingdata');

    /*
    |--------------------------------------------------------------------------
    | Certificate Templates
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/certificate-templates',
        'training.certificate-templates'
    )
        ->middleware(
            'permission:'
                . Permissions::VIEW_CERTIFICATE_TEMPLATE
        )
        ->name('certificate-templates.index');

    Route::livewire(
        '/certificate-templates/create',
        'training.certificate-template-form'
    )
        ->middleware(
            'permission:'
                . Permissions::CREATE_CERTIFICATE_TEMPLATE
        )
        ->name('certificate-templates.create');

    Route::livewire(
        '/certificate-templates/{template}/edit',
        'training.certificate-template-form'
    )
        ->middleware(
            'permission:'
                . Permissions::UPDATE_CERTIFICATE_TEMPLATE
        )
        ->name('certificate-templates.edit');

    Route::livewire('/user-management', 'user-management')
        ->middleware(
            'permission:'
                . Permissions::VIEW_USER
                . '|'
                . Permissions::VIEW_ROLE
        )
        ->name('user-management');

    Route::livewire('/managementdata', 'managementdata')
        ->middleware(
            'permission:'
                . Permissions::VIEW_DEPARTMENT_POSITION_DATA
        )
        ->name('managementdata');

    Route::livewire('/trainingdetail', 'trainingdetail')
        ->middleware(
            'permission:'
                . Permissions::VIEW_TRAINING_DETAIL
        )
        ->name('trainingdetail');

    Route::livewire('/trnc', 'trainer-contribution')
        ->middleware(
            'permission:'
                . Permissions::VIEW_TRAINING_CONTRIBUTION
        )
        ->name('trnc');

    Route::get('/prf', function () {
        return redirect()->route('profile.edit');
    })->name('prf');

    Route::livewire('/avg', 'average-training')
        ->middleware(
            'permission:'
                . Permissions::VIEW_AVERAGE_TRAINING
        )
        ->name('avg');

    Route::livewire('/trnp', 'training-penetration')
        ->middleware(
            'permission:'
                . Permissions::VIEW_TRAINING_PENETRATION
        )
        ->name('trnp');

    /*
     * Modul History/Recycle Bin sengaja tidak didaftarkan.
     * Data soft-deleted tetap tersimpan di database.
     */
});

require __DIR__ . '/certificates.php';
require __DIR__ . '/settings.php';
