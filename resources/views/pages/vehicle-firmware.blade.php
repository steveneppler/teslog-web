<x-layouts.app>
    <x-slot:header>Firmware History — {{ $vehicle->name }}</x-slot:header>
    <livewire:firmware-history :vehicle="$vehicle" />
</x-layouts.app>
