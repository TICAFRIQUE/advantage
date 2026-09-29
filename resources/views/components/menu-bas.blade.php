{{-- Menu du bas (moins de 992 px) : voir App\View\Components\MenuBas. --}}
<nav class="menu-bas d-lg-none" aria-label="Menu rapide">
    @foreach ($entrees as $entree)
        @if ($entree['menu'])
            <button type="button" class="menu-bas__lien" data-bs-toggle="offcanvas" data-bs-target="#barre-laterale"
                    aria-controls="barre-laterale" aria-label="Ouvrir le menu complet">
                <i class="bi {{ $entree['icone'] }}" aria-hidden="true"></i>
                <span>{{ $entree['libelle'] }}</span>
            </button>
        @elseif ($entree['principal'])
            <a href="{{ $entree['url'] }}" @class(['menu-bas__lien menu-bas__principal', 'actif' => $entree['actif']])
               @if ($entree['actif']) aria-current="page" @endif>
                <span class="menu-bas__bulle"><i class="bi {{ $entree['icone'] }}" aria-hidden="true"></i></span>
                <span>{{ $entree['libelle'] }}</span>
            </a>
        @else
            <a href="{{ $entree['url'] }}" @class(['menu-bas__lien', 'actif' => $entree['actif']])
               @if ($entree['actif']) aria-current="page" @endif>
                <i class="bi {{ $entree['icone'] }}" aria-hidden="true"></i>
                <span>{{ $entree['libelle'] }}</span>
            </a>
        @endif
    @endforeach
</nav>
