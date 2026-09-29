/**
 * Tableaux Yajra DataTables (traitement côté serveur).
 * Chargé uniquement sur les pages qui en ont besoin (jQuery hors du bundle principal).
 *
 * Usage : <table data-source="URL" data-filtres="#formulaire"> avec des
 * <th data-colonne="nom" data-triable="false">.
 */
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

window.$ = window.jQuery = $;

const francais = {
    emptyTable: 'Aucune donnée disponible',
    info: '_START_ à _END_ sur _TOTAL_',
    infoEmpty: '0 résultat',
    infoFiltered: '(filtré sur _MAX_)',
    lengthMenu: '_MENU_ par page',
    loadingRecords: 'Chargement…',
    processing: 'Chargement…',
    search: 'Rechercher :',
    zeroRecords: 'Aucun résultat',
    paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
    aria: { orderable: 'Trier par cette colonne' },
};

document.querySelectorAll('table[data-source]').forEach((tableau) => {
    const filtres = tableau.dataset.filtres ? document.querySelector(tableau.dataset.filtres) : null;

    // data-lien="colonne_url" : la cellule devient un lien. Les valeurs sont
    // déjà échappées côté serveur par Yajra (pas de double échappement).
    const colonnes = [...tableau.querySelectorAll('thead th')].map((th) => ({
        data: th.dataset.colonne,
        name: th.dataset.colonne,
        orderable: th.dataset.triable !== 'false',
        searchable: false,
        render: th.dataset.lien
            ? (valeur, type, ligne) => (type === 'display' && ligne[th.dataset.lien] ? `<a href="${ligne[th.dataset.lien]}">${valeur}</a>` : valeur)
            : undefined,
    }));

    let table;
    const exports = boutonsExport(tableau, filtres, () => table);

    table = new DataTable(tableau, {
        serverSide: true,
        processing: true,
        responsive: true,
        pageLength: 25,
        // Boutons d'export à droite du nombre de lignes par page.
        layout: { topStart: exports ? ['pageLength', exports] : 'pageLength' },
        // Tri initial sur la première colonne triable (côté serveur).
        order: [[Math.max(0, colonnes.findIndex((colonne) => colonne.orderable)), tableau.dataset.ordre === 'asc' ? 'asc' : 'desc']],
        columns: colonnes,
        language: francais,
        ajax: {
            url: tableau.dataset.source,
            data: (donnees) => {
                if (filtres) {
                    new FormData(filtres).forEach((valeur, cle) => {
                        donnees[cle] = valeur;
                    });
                }
            },
        },
    });

    // data-soumission="page" sur le formulaire : soumission normale (les
    // indicateurs rendus côté serveur suivent les filtres).
    filtres?.addEventListener('submit', (evenement) => {
        if (filtres.dataset.soumission === 'page') {
            return;
        }

        evenement.preventDefault();
        table.ajax.reload();
        synchroniserUrl(filtres);
    });
});

/**
 * Filtrage sans rechargement : l'URL reprend les filtres (lien partageable,
 * retour arrière) et le bouton « réinitialiser » s'affiche s'il y en a.
 */
function synchroniserUrl(filtres) {
    const url = new URL(window.location.href);
    url.search = '';

    new FormData(filtres).forEach((valeur, cle) => {
        if (valeur !== '') {
            url.searchParams.append(cle, valeur);
        }
    });

    window.history.replaceState(null, '', url);
    filtres.querySelector('[data-reinitialiser]')?.classList.toggle('d-none', url.search === '');
}

/**
 * Petits boutons PDF / Excel / CSV (data-exports : URL par format, présent
 * seulement si l'utilisateur peut exporter). L'export reprend les filtres du
 * formulaire et la recherche du tableau : il contient ce qui est affiché.
 */
function boutonsExport(tableau, filtres, instance) {
    const urls = tableau.dataset.exports ? JSON.parse(tableau.dataset.exports) : null;

    if (!urls) {
        return null;
    }

    const groupe = document.createElement('div');
    groupe.className = 'btn-group btn-group-sm ms-2 exports-tableau';
    groupe.setAttribute('role', 'group');
    groupe.setAttribute('aria-label', 'Exporter la liste');

    [['pdf', 'PDF', 'bi-file-earmark-pdf'], ['xlsx', 'Excel', 'bi-file-earmark-excel'], ['csv', 'CSV', 'bi-filetype-csv']]
        .filter(([format]) => urls[format])
        .forEach(([format, libelle, icone]) => {
            const lien = document.createElement('a');
            lien.className = 'btn btn-outline-secondary';
            lien.href = urls[format];
            lien.title = `Exporter en ${libelle}`;

            const pictogramme = document.createElement('i');
            pictogramme.className = `bi ${icone} me-1`;
            pictogramme.setAttribute('aria-hidden', 'true');
            lien.append(pictogramme, libelle);

            lien.addEventListener('click', (evenement) => {
                evenement.preventDefault();
                const url = new URL(lien.href, window.location.origin);

                if (filtres) {
                    new FormData(filtres).forEach((valeur, cle) => {
                        if (valeur !== '') {
                            url.searchParams.append(cle, valeur);
                        }
                    });
                }

                const recherche = instance()?.search().trim();

                if (recherche) {
                    url.searchParams.set('recherche_tableau', recherche);
                }

                window.location.href = url.toString();
            });

            groupe.append(lien);
        });

    return groupe;
}
