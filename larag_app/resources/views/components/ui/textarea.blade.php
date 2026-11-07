@props(['error' => false, 'rows' => 4])
<textarea rows="{{ $rows }}" {{ $attributes->merge([
    'class' => 'block w-full rounded-lg bg-surface text-app-text placeholder:text-muted text-sm px-3 py-2 border '
        .($error
            ? 'border-danger focus:border-danger focus:ring-danger/40'
            : 'border-line focus:border-primary focus:ring-brand'),
]) }}>{{ $slot }}</textarea>
