<x-layouts.app>
    <x-slot:header>Battery Health — {{ $vehicle->name }}</x-slot:header>
    <livewire:battery-health :vehicle="$vehicle" />
</x-layouts.app>
