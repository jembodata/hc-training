<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')

    @php
        $user = auth()->user();

        $homeUrl = route('profile.edit');

        if ($user) {
            $homeUrl = \App\Support\Auth\AuthorizedRoute::urlFor($user);
        }
    @endphp
</head>

<body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
    <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <div wire:ignore>
                <flux:sidebar.brand href="/" logo="{{ asset('favicon.svg') }}" name="TRMS" />
            </div>

            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            @can(\App\Support\Auth\Permissions::VIEW_DASHBOARD)
                <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            @endcan

            @canany([\App\Support\Auth\Permissions::VIEW_TRAINING,
                \App\Support\Auth\Permissions::VIEW_CERTIFICATE_TEMPLATE, \App\Support\Auth\Permissions::VIEW_CERTIFICATE])
                <flux:sidebar.group heading="Course" icon="academic-cap" expandable class="grid">
                    @can(\App\Support\Auth\Permissions::VIEW_TRAINING)
                        <flux:sidebar.item icon="book-open" :href="route('trainingdata')"
                            :current="request()->routeIs('trainingdata')" wire:navigate>
                            {{ __('Training') }}
                        </flux:sidebar.item>
                    @endcan

                    @can(\App\Support\Auth\Permissions::VIEW_CERTIFICATE_TEMPLATE)
                        <flux:sidebar.item icon="document-text" :href="route('certificate-templates.index')"
                            :current="request()->routeIs('certificate-templates.*')" wire:navigate>
                            {{ __('Cert Template') }}
                        </flux:sidebar.item>
                    @endcan

                    @can(\App\Support\Auth\Permissions::VIEW_CERTIFICATE)
                        <flux:sidebar.item icon="check-badge" :href="route('certificates.index')"
                            :current="request()->routeIs('certificates.*')" wire:navigate>
                            {{ __('Issued Cert') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany([\App\Support\Auth\Permissions::VIEW_TRAINING_DETAIL,
                \App\Support\Auth\Permissions::VIEW_AVERAGE_TRAINING,
                \App\Support\Auth\Permissions::VIEW_TRAINING_PENETRATION,
                \App\Support\Auth\Permissions::VIEW_TRAINING_CONTRIBUTION])
                <flux:sidebar.group heading="Report" icon="chart-bar-square" expandable class="grid">
                    @can(\App\Support\Auth\Permissions::VIEW_AVERAGE_TRAINING)
                        <flux:sidebar.item icon="clock" :href="route('avg')" :current="request()->routeIs('avg')"
                            wire:navigate>
                            {{ __('Average Training') }}
                        </flux:sidebar.item>
                    @endcan

                    @can(\App\Support\Auth\Permissions::VIEW_TRAINING_DETAIL)
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('trainingdetail')"
                            :current="request()->routeIs('trainingdetail')" wire:navigate>
                            {{ __('Training Detail') }}
                        </flux:sidebar.item>
                    @endcan

                    @can(\App\Support\Auth\Permissions::VIEW_TRAINING_PENETRATION)
                        <flux:sidebar.item icon="chart-pie" :href="route('trnp')" :current="request()->routeIs('trnp')"
                            wire:navigate>
                            {{ __('Training Penetration') }}
                        </flux:sidebar.item>
                    @endcan

                    @can(\App\Support\Auth\Permissions::VIEW_TRAINING_CONTRIBUTION)
                        <flux:sidebar.item icon="presentation-chart-line" :href="route('trnc')"
                            :current="request()->routeIs('trnc')" wire:navigate>
                            {{ __('Training Contribution') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany([\App\Support\Auth\Permissions::VIEW_EMPLOYEE, \App\Support\Auth\Permissions::VIEW_USER,
                \App\Support\Auth\Permissions::VIEW_ROLE, \App\Support\Auth\Permissions::VIEW_DEPARTMENT_POSITION_DATA])
                <flux:sidebar.group heading="Data" icon="circle-stack" expandable class="grid">
                    @can(\App\Support\Auth\Permissions::VIEW_EMPLOYEE)
                        <flux:sidebar.item icon="users" :href="route('employee')" :current="request()->routeIs('employee')"
                            wire:navigate>
                            {{ __('Employee') }}
                        </flux:sidebar.item>
                    @endcan

                    @canany([\App\Support\Auth\Permissions::VIEW_USER, \App\Support\Auth\Permissions::VIEW_ROLE])
                        <flux:sidebar.item icon="key" :href="route('user-management')"
                            :current="request()->routeIs('user-management')" wire:navigate>
                            {{ __('User Role') }}
                        </flux:sidebar.item>
                    @endcanany

                    @can(\App\Support\Auth\Permissions::VIEW_DEPARTMENT_POSITION_DATA)
                        <flux:sidebar.item icon="building-office-2" :href="route('managementdata')"
                            :current="request()->routeIs('managementdata')" wire:navigate>
                            {{ __('Department / Position') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile :name="auth()->user()->name" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">
                                    {{ auth()->user()->name }}
                                </flux:heading>

                                <flux:text class="truncate">
                                    {{ auth()->user()->email }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf

                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">
                                    {{ auth()->user()->name }}
                                </flux:heading>

                                <flux:text class="truncate">
                                    {{ auth()->user()->email }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf

                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
    @livewireScripts

    @persist('toast')
        <flux:toast.group position="top end" class="pt-2 pr-6">
            <flux:toast />
        </flux:toast.group>
    @endpersist
</body>

</html>
