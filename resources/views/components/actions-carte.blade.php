@props(['carte', 'detail' => true])

<div {{ $attributes->class(['actions-carte d-flex flex-wrap gap-2']) }}>
    @if ($detail)
        @can('view', $carte)
            <a href="{{ route('agent.cartes.show', $carte) }}" class="btn btn-primary flex-grow-1">
                Voir le détail
            </a>
        @endcan
    @endif

    @can('declarerPerte', $carte)
        <form method="POST" action="{{ route('agent.cartes.perte', $carte) }}" class="flex-grow-1"
              data-motif="Motif de la déclaration de perte"
              data-titre="Déclarer la carte {{ $carte->numeroFormate() }} perdue ?"
              data-confirmer="La carte sera révoquée définitivement. Le titulaire devra recevoir une nouvelle carte."
              data-bouton-confirmer="Déclarer perdue" data-danger="1">
            @csrf
            <input type="hidden" name="motif" value="">
            <button type="submit" class="btn btn-outline-danger w-100">Déclarer perdue</button>
        </form>
    @endcan
</div>
