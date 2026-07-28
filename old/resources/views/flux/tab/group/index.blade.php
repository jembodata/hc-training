@blaze(fold: true)

@props([
    'default' => null,
])

<div
    x-data="{
        activeTab: @js($default),
        wireModel: null,

        registerTabsModel(model) {
            this.wireModel = model

            if (model && this.$wire && typeof this.$wire.get === 'function') {
                const value = this.$wire.get(model)

                if (value) {
                    this.activeTab = value
                }
            }
        },

        initTab(name, selected = false) {
            if (selected || this.activeTab === null || this.activeTab === '') {
                this.activeTab = name
            }
        },

        selectTab(name) {
            this.activeTab = name

            if (this.wireModel && this.$wire && typeof this.$wire.set === 'function') {
                this.$wire.set(this.wireModel, name)
            }
        },

        isTabActive(name) {
            return String(this.activeTab) === String(name)
        },
    }"
    {{ $attributes->class('space-y-4') }}
    data-flux-tab-group
>
    {{ $slot }}
</div>