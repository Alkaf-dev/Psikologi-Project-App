<div style="display: grid; gap: .75rem;">
    <div>
        <div style="font-size: .8rem; color: rgb(var(--gray-500));">Nilai</div>
        <x-filament::badge color="success">{{ filled($grade) ? $grade : '-' }}</x-filament::badge>
    </div>
    <div>
        <div style="font-size: .8rem; color: rgb(var(--gray-500));">Catatan dosen</div>
        <div style="white-space: pre-line; line-height: 1.7;">{{ $feedback }}</div>
    </div>
</div>
