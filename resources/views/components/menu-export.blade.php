{{--
    Petits boutons d'export PDF / Excel / CSV d'une liste hors tableau Yajra
    (les tableaux Yajra les affichent dans leur barre, voir tableaux.js),
    si l'utilisateur détient la permission d'export. Le lien reprend les
    filtres du formulaire (resources/js/app.js, [data-export]).
--}}
@props(['liste', 'formulaire' => null, 'tableau' => null])

@can('exporter-donnees')
    <div {{ $attributes->merge(['class' => 'btn-group btn-group-sm exports-tableau']) }} role="group" aria-label="Exporter la liste">
        @foreach ([App\Enums\FormatExport::Pdf, App\Enums\FormatExport::Excel, App\Enums\FormatExport::Csv] as $format)
            <a class="btn btn-outline-secondary" href="{{ route('gestion.exports', [$liste, $format]) }}" data-export
               title="Exporter en {{ $format->libelle() }}"
               @if ($formulaire) data-formulaire="{{ $formulaire }}" @endif
               @if ($tableau) data-tableau="{{ $tableau }}" @endif>
                <i class="bi {{ $format->icone() }} me-1" aria-hidden="true"></i>{{ $format->libelle() }}
            </a>
        @endforeach
    </div>
@endcan
