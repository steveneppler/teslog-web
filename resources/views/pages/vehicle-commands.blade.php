<x-layouts.app>
    <x-slot:header>Commands — {{ $vehicle->name }}</x-slot:header>
    <livewire:vehicle-commands :vehicle="$vehicle" />
</x-layouts.app>
