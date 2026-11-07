@props(['error' => false])
<input {{ $attributes->merge([
    'class' => 'block w-full rounded-lg bg-surface text-app-text placeholder:text-muted text-sm h-10 px-3 border '
        .($error
            ? 'border-danger focus:border-danger focus:ring-danger/40'
            : 'border-line focus:border-primary focus:ring-brand'),
]) }}>
