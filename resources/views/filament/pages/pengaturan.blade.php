<x-filament-panels::page>
    <form wire:submit="save" style="max-width: 56rem;">
        {{ $this->form }}

        <div style="margin-top: 1.25rem;">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
