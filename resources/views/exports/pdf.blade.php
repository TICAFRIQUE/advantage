<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $liste->titre() }}</title>
    <style>
        @page { margin: 28px 28px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1d2233; }
        .entete { width: 100%; border-bottom: 2px solid #c9973a; margin-bottom: 10px; padding-bottom: 6px; }
        .entete td { vertical-align: middle; }
        .titre { font-size: 15px; font-weight: bold; color: #0b1257; margin: 0; }
        .sous-titre { color: #5b6178; margin: 2px 0 0; }
        .filtres { margin: 0 0 8px; color: #5b6178; }
        .filtres strong { color: #1d2233; }
        table.donnees { width: 100%; border-collapse: collapse; }
        table.donnees th { background: #0b1257; color: #fff; text-align: left; padding: 4px 5px; font-size: 8px; }
        table.donnees td { padding: 3px 5px; border-bottom: 1px solid #e3e5ee; vertical-align: top; word-wrap: break-word; }
        table.donnees tr:nth-child(even) td { background: #f5f6fa; }
        .vide { color: #5b6178; padding: 12px 0; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            @if ($logo)
                <td style="width: 44px"><img src="{{ $logo }}" alt="" width="38" height="38"></td>
            @endif
            <td>
                <p class="titre">{{ $liste->titre() }}</p>
                <p class="sous-titre">
                    ADVANTAGE — fontaine GROUP · {{ $nombre }} ligne(s) · généré le {{ now()->format('d/m/Y à H:i') }}
                    par {{ $auteur->libelleActeur() }}
                </p>
            </td>
        </tr>
    </table>

    @if ($liste->descriptionFiltres() !== [])
        <p class="filtres">
            Filtres :
            @foreach ($liste->descriptionFiltres() as $libelle => $valeur)
                <strong>{{ $libelle }}</strong> {{ $valeur }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    @endif

    <table class="donnees">
        <thead>
            <tr>
                @foreach ($liste->colonnes() as $colonne)
                    <th>{{ $colonne }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($lignes as $ligne)
                <tr>
                    @foreach ($ligne as $valeur)
                        <td>{{ $valeur ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($liste->colonnes()) }}" class="vide">Aucune donnée pour ces filtres.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
