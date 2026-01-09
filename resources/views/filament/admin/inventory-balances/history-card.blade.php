@php
    $entries = $entries ?? [];
@endphp

<div class="space-y-4">
    @forelse ($entries as $entry)
        <x-filament::card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-semibold text-gray-900">
                    {{ $entry['action'] }}
                    <span class="font-normal text-gray-600">oleh {{ $entry['user'] }}</span>
                </div>
                <div class="text-xs font-medium text-gray-500">{{ $entry['timestamp'] }}</div>
            </div>

            <p class="mt-2 text-sm text-gray-700">{{ $entry['description'] }}</p>

            @if (! empty($entry['changes']))
                <dl class="mt-3 grid gap-x-6 gap-y-1 text-xs text-gray-600 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($entry['changes'] as $label => $value)
                        <div class="flex items-center justify-between gap-2">
                            <dt class="font-medium text-gray-700">{{ $label }}</dt>
                            <dd class="text-right">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-filament::card>
    @empty
        <x-filament::section icon="heroicon-o-information-circle">
            <x-slot name="description">Belum ada riwayat perubahan untuk saldo ini.</x-slot>
        </x-filament::section>
    @endforelse
</div>
