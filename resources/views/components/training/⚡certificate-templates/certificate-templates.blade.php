<div id="certificate-template-list" class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">
                Certificate Templates
            </flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Kelola desain sertifikat completion dan participation.
            </flux:subheading>
        </div>

        @can(\App\Support\Auth\Permissions::CREATE_CERTIFICATE_TEMPLATE)
            <flux:button href="{{ route('certificate-templates.create') }}" wire:navigate variant="primary" icon="plus"
                size="sm">
                Create Template
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:checkbox wire:model.live="show_archived" label="Show archived" />

        <div class="w-full sm:w-80">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search template"
                clearable size="sm" class="text-xs" />
        </div>
    </div>

    @if ($visible_template_count > 0)
        <div class="space-y-8">
            @foreach ($kind_labels as $kind => $kindLabel)
                @php
                    $kindTemplates = $templates_by_kind->get($kind, collect());
                @endphp

                @if ($kindTemplates->isNotEmpty())
                    <section class="space-y-3">
                        <flux:heading size="sm" class="font-black uppercase tracking-wide">
                            {{ $kindLabel }}
                        </flux:heading>

                        <flux:card class="divide-y divide-zinc-200 !p-0 dark:divide-zinc-800">
                            @foreach ($kindTemplates as $template)
                                <div wire:key="certificate-template-{{ $template->id }}"
                                    class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading size="sm" class="truncate font-bold">
                                                {{ $template->name }}
                                            </flux:heading>

                                            @if ($template->is_default)
                                                <flux:badge size="sm" color="emerald">
                                                    Default
                                                </flux:badge>
                                            @endif

                                            @if ($template->isArchived())
                                                <flux:badge size="sm" color="zinc">
                                                    Archived
                                                </flux:badge>
                                            @endif
                                        </div>

                                        <flux:text class="mt-1 text-xs">
                                            Updated
                                            {{ $template->updated_at->format('d M Y') }}
                                            ·
                                            {{ str($template->design)->replace('_', ' ')->title() }}
                                        </flux:text>
                                    </div>

                                    <div class="flex items-center gap-1 self-end sm:self-auto">
                                        @can(\App\Support\Auth\Permissions::UPDATE_CERTIFICATE_TEMPLATE)
                                            <flux:button href="{{ route('certificate-templates.edit', $template->id) }}"
                                                wire:navigate variant="ghost" size="sm" class="font-semibold text-xs">
                                                Edit
                                            </flux:button>
                                        @endcan

                                        @canany([\App\Support\Auth\Permissions::UPDATE_CERTIFICATE_TEMPLATE,
                                            \App\Support\Auth\Permissions::ARCHIVE_CERTIFICATE_TEMPLATE])
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="chevron-down"
                                                    inset="top bottom" title="Template actions" />

                                                <flux:menu>
                                                    @if ($template->isArchived())
                                                        @can(\App\Support\Auth\Permissions::ARCHIVE_CERTIFICATE_TEMPLATE)
                                                            <flux:menu.item icon="arrow-uturn-left"
                                                                wire:click="restore({{ $template->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="restore({{ $template->id }})">
                                                                Restore
                                                            </flux:menu.item>
                                                        @endcan
                                                    @else
                                                        @can(\App\Support\Auth\Permissions::UPDATE_CERTIFICATE_TEMPLATE)
                                                            <flux:menu.item icon="check-circle"
                                                                wire:click="setDefault({{ $template->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="setDefault({{ $template->id }})"
                                                                :disabled="$template->is_default">
                                                                Set as default
                                                            </flux:menu.item>
                                                        @endcan

                                                        @can(\App\Support\Auth\Permissions::ARCHIVE_CERTIFICATE_TEMPLATE)
                                                            @can(\App\Support\Auth\Permissions::UPDATE_CERTIFICATE_TEMPLATE)
                                                                <flux:menu.separator />
                                                            @endcan

                                                            <flux:menu.item icon="archive-box" variant="danger"
                                                                wire:click="archive({{ $template->id }})"
                                                                wire:confirm="Archive this certificate template?"
                                                                wire:loading.attr="disabled"
                                                                wire:target="archive({{ $template->id }})"
                                                                :disabled="$template->is_default">
                                                                Archive
                                                            </flux:menu.item>
                                                        @endcan
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endcanany
                                    </div>
                                </div>
                            @endforeach
                        </flux:card>
                    </section>
                @endif
            @endforeach
        </div>
    @else
        <flux:card class="py-16 text-center">
            <flux:icon.document-text class="mx-auto h-10 w-10 text-zinc-300 dark:text-zinc-700" />

            <flux:heading size="sm" class="mt-4">
                No Certificate Templates
            </flux:heading>

            <flux:text class="mt-1 text-xs">
                @if ($show_archived)
                    Belum ada template yang diarsipkan.
                @elseif ($search !== '')
                    Template yang dicari tidak ditemukan.
                @else
                    Buat template sertifikat pertama Anda.
                @endif
            </flux:text>
        </flux:card>
    @endif
</div>
