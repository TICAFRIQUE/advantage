@props(['carte'])

@php
    $actions = [
        App\Enums\StatutCarte::Suspendue->value => [
            'libelle' => 'Suspendre', 'icone' => 'bi-pause-circle', 'classe' => 'btn-outline-secondary',
            'titre' => 'Suspendre la carte '.$carte->numeroFormate().' ?',
            'texte' => 'La carte ne pourra plus être utilisée chez les partenaires jusqu\'à sa réactivation.',
            'danger' => false,
        ],
        App\Enums\StatutCarte::Active->value => [
            'libelle' => 'Réactiver', 'icone' => 'bi-play-circle', 'classe' => 'btn-outline-success',
            'titre' => 'Réactiver la carte '.$carte->numeroFormate().' ?',
            'texte' => 'La carte pourra de nouveau être utilisée chez les partenaires.',
            'danger' => false,
        ],
        App\Enums\StatutCarte::Revoquee->value => [
            'libelle' => 'Révoquer (perte, vol…)', 'icone' => 'bi-x-octagon', 'classe' => 'btn-outline-danger',
            'titre' => 'Révoquer définitivement la carte '.$carte->numeroFormate().' ?',
            'texte' => 'Action irréversible : la carte ne pourra plus jamais être utilisée. Le titulaire devra recevoir une nouvelle carte.',
            'danger' => true,
        ],
    ];
@endphp

@can('changerStatut', $carte)
    <div {{ $attributes->class(['d-flex flex-wrap gap-2']) }}>
        @foreach ($carte->transitionsPossibles() as $cible)
            @php($action = $actions[$cible->value])
            <form method="POST" action="{{ route('gestion.cartes.statut', $carte) }}" class="flex-grow-1"
                  data-motif="Motif (obligatoire)"
                  data-titre="{{ $action['titre'] }}"
                  data-confirmer="{{ $action['texte'] }}"
                  data-bouton-confirmer="{{ $action['libelle'] }}"
                  @if ($action['danger']) data-danger="1" @endif>
                @csrf
                <input type="hidden" name="statut" value="{{ $cible->value }}">
                <input type="hidden" name="motif" value="">
                <button type="submit" class="btn {{ $action['classe'] }} w-100">
                    <i class="bi {{ $action['icone'] }} me-1" aria-hidden="true"></i>{{ $action['libelle'] }}
                </button>
            </form>
        @endforeach
    </div>
@endcan
