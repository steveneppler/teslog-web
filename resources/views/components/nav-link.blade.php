@props(['active' => false])

<a {{ $attributes->merge([
    'class' => 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ' .
        ($active ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:bg-surface-alt hover:text-text-primary')
]) }}>
    @if(isset($icon))
        <span class="{{ $active ? 'text-red-500' : 'text-text-subtle' }}">{{ $icon }}</span>
    @endif
    {{ $slot }}
</a>
