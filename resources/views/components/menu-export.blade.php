{{--
    Menu « Exporter » d'une liste (CSV, Excel, PDF), si l'utilisateur détient
    la permission d'export. Le lien reprend les filtres du formulaire et la
    recherche du tableau (resources/js/app.js, [data-export]) : l'export
    contient ce qui est affiché.
--}}
@props(['liste', 'formulaire' => null, 'tableau' => null])

@can('exporter-donnees')
    <div {{ $attributes->merge(['class' => 'dropdown']) }}>
        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-download me-1" aria-hidden="true"></i>Exporter
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            @foreach (App\Enums\FormatExport::cases() as $format)
                <li>
                    <a class="dropdown-item" href="{{ route('gestion.exports', [$liste, $format]) }}" data-export
                       @if ($formulaire) data-formulaire="{{ $formulaire }}" @endif
                       @if ($tableau) data-tableau="{{ $tableau }}" @endif>
                        <i class="bi {{ $format->icone() }} me-2" aria-hidden="true"></i>{{ $format->libelle() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endcan
