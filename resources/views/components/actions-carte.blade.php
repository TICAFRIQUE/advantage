@props(['carte', 'detail' => true])

<div {{ $attributes->class(['actions-carte d-flex flex-wrap gap-2']) }}>
    @if ($detail)
        @can('view', $carte)
            <a href="{{ route('gestion.cartes.show', $carte) }}" class="btn btn-primary flex-grow-1">
                Voir le détail
            </a>
        @endcan
    @endif

    {{ $slot }}
</div>
