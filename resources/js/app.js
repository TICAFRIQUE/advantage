import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.bootstrap = bootstrap;
window.Swal = Swal;

const couleurs = { nuit: '#0b1257', danger: '#b3261e', gris: '#5b6178' };
const echapper = (texte) => String(texte).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Désactive le bouton d'envoi d'un formulaire et affiche un indicateur.
 */
function marquerEnvoi(formulaire) {
    formulaire.querySelectorAll('button[type="submit"]').forEach((bouton) => {
        bouton.disabled = true;
        bouton.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
    });
}

/**
 * Réduction / extension de la barre latérale (desktop). La préférence est
 * mémorisée dans un cookie lu côté serveur : pas de clignotement au chargement.
 */
document.addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('[data-basculer-barre]');

    if (!bouton) {
        return;
    }

    const reduite = document.body.classList.toggle('barre-reduite');
    document.cookie = `barre_reduite=${reduite ? 1 : 0}; path=/; max-age=31536000; SameSite=Lax`;
    document.querySelectorAll('[data-basculer-barre]').forEach((element) => element.setAttribute('aria-expanded', String(!reduite)));
});

/**
 * Formulaires sensibles : confirmation SweetAlert avant envoi.
 * - data-confirmer="message"              → simple confirmation
 * - data-motif="Libellé du motif"         → confirmation avec saisie obligatoire d'un motif
 */
document.addEventListener('submit', async (evenement) => {
    const formulaire = evenement.target;

    if (!(formulaire instanceof HTMLFormElement) || formulaire.dataset.confirme === '1') {
        if (formulaire instanceof HTMLFormElement) marquerEnvoi(formulaire);
        return;
    }

    if (!formulaire.dataset.confirmer && !formulaire.dataset.motif) {
        return;
    }

    evenement.preventDefault();

    const options = {
        title: formulaire.dataset.titre ?? 'Confirmer',
        text: formulaire.dataset.confirmer ?? '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: formulaire.dataset.boutonConfirmer ?? 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: formulaire.dataset.danger ? couleurs.danger : couleurs.nuit,
        cancelButtonColor: couleurs.gris,
        reverseButtons: true,
        focusCancel: true,
    };

    if (formulaire.dataset.motif) {
        Object.assign(options, {
            input: 'textarea',
            inputLabel: formulaire.dataset.motif,
            inputAttributes: { maxlength: 200, 'aria-label': formulaire.dataset.motif },
            inputValidator: (valeur) => (valeur.trim().length < 3 ? 'Merci de préciser le motif (3 caractères minimum).' : undefined),
        });
    }

    const resultat = await Swal.fire(options);

    if (!resultat.isConfirmed) {
        return;
    }

    if (formulaire.dataset.motif) {
        formulaire.querySelector('input[name="motif"]').value = resultat.value.trim();
    }

    formulaire.dataset.confirme = '1';
    formulaire.requestSubmit();
});

/**
 * Formulaire d'activation de carte (espace agent).
 */
Alpine.data('activationCarte', (urlRecherche, anciennesValeurs = {}, listePays = {}, paysDefaut = 'CI') => ({
    telephone: anciennesValeurs.telephone ?? '',
    pays: anciennesValeurs.pays_telephone ?? paysDefaut,
    nom: anciennesValeurs.nom ?? '',
    prenom: anciennesValeurs.prenom ?? '',
    numero: anciennesValeurs.numero_carte ?? '',
    confirmation: '',
    titulaire: null,
    recherche: false,
    confirme: false,

    get configPays() {
        return listePays[this.pays] ?? listePays[paysDefaut];
    },

    /**
     * Même logique que App\Services\Telephone::normaliser (le serveur reste l'arbitre).
     * Retourne { code, national } ou null.
     */
    get telephoneNormalise() {
        const saisie = this.telephone.trim();
        let chiffres = saisie.replace(/\D/g, '');

        if (chiffres === '') {
            return null;
        }

        let code = listePays[this.pays] ? this.pays : paysDefaut;

        if (saisie.startsWith('+') || chiffres.startsWith('00')) {
            chiffres = chiffres.replace(/^00/, '');
            code = Object.keys(listePays)
                .sort((a, b) => listePays[b].indicatif.length - listePays[a].indicatif.length)
                .find((c) => chiffres.startsWith(listePays[c].indicatif));

            if (!code) {
                return null;
            }

            chiffres = chiffres.slice(listePays[code].indicatif.length);
        } else {
            const { indicatif, longueur } = listePays[code];

            if (chiffres.length === indicatif.length + longueur && chiffres.startsWith(indicatif)) {
                chiffres = chiffres.slice(indicatif.length);
            }
        }

        const { longueur, prefixe_national: prefixe } = listePays[code];

        if (prefixe && chiffres.length === longueur + prefixe.length && chiffres.startsWith(prefixe)) {
            chiffres = chiffres.slice(prefixe.length);
        }

        return chiffres.length === longueur ? { code, national: chiffres } : null;
    },
    get telephoneValide() {
        return this.telephoneNormalise !== null;
    },
    get telephoneFormate() {
        const normalise = this.telephoneNormalise;

        if (!normalise) {
            return '';
        }

        const { indicatif, groupes } = listePays[normalise.code];
        let position = 0;
        const morceaux = groupes.map((taille) => {
            const morceau = normalise.national.slice(position, position + taille);
            position += taille;
            return morceau;
        });

        return `+${indicatif} ${morceaux.join(' ')}`;
    },
    get exempleTelephone() {
        const { longueur, groupes } = this.configPays;
        const exemple = '0712345678901234'.slice(0, longueur);
        let position = 0;

        return groupes.map((taille) => {
            const morceau = exemple.slice(position, position + taille);
            position += taille;
            return morceau;
        }).join(' ');
    },
    get messageTelephone() {
        return `Le numéro doit comporter ${this.configPays.longueur} chiffres (${this.configPays.nom}).`;
    },
    get numeroValide() {
        return /^\d{7}$/.test(this.numero);
    },
    get numerosIdentiques() {
        return this.confirmation !== '' && this.numero === this.confirmation;
    },
    get numeroFormate() {
        return this.numero.replace(/^(\d{3})(\d{3})(\d)$/, '$1 $2 $3');
    },

    filtrerChiffres(champ, longueur) {
        this[champ] = this[champ].replace(/\D/g, '').slice(0, longueur);
    },

    async rechercherTitulaire() {
        this.titulaire = null;

        if (!this.telephoneValide) {
            return;
        }

        this.recherche = true;

        try {
            const reponse = await fetch(urlRecherche, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                body: JSON.stringify({ telephone: this.telephone, pays_telephone: this.pays }),
            });

            if (reponse.ok) {
                const donnees = await reponse.json();
                this.titulaire = donnees.existe ? donnees : null;

                if (this.titulaire) {
                    this.nom = this.titulaire.titulaire.nom;
                    this.prenom = this.titulaire.titulaire.prenom;
                }
            }
        } finally {
            this.recherche = false;
        }
    },

    async soumettre(evenement) {
        const formulaire = evenement.target;

        if (formulaire.dataset.confirme === '1') {
            return; // second envoi après confirmation : on laisse partir le formulaire
        }

        evenement.preventDefault();
        formulaire.classList.add('was-validated');

        if (!formulaire.checkValidity() || !this.numerosIdentiques || !this.telephoneValide) {
            return;
        }

        const resultat = await Swal.fire({
            title: 'Activer cette carte ?',
            html: `<p class="mb-1">Carte <strong>${this.numeroFormate}</strong></p>
                   <p class="mb-1">${echapper(this.nom)} ${echapper(this.prenom)}</p>
                   <p class="mb-0">${echapper(this.telephoneFormate)}</p>
                   <p class="small text-secondary mt-2 mb-0">Le numéro de carte ne pourra plus jamais être réutilisé.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Activer',
            cancelButtonText: 'Corriger',
            confirmButtonColor: couleurs.nuit,
            cancelButtonColor: couleurs.gris,
            reverseButtons: true,
        });

        if (resultat.isConfirmed) {
            formulaire.dataset.confirme = '1';
            formulaire.requestSubmit();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

/**
 * Documentation des commandes (page « Mise en production ») : bouton
 * « Copier » sur chaque bloc de code.
 */
document.querySelectorAll('.doc-commandes pre').forEach((bloc) => {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'btn btn-sm btn-light doc-commandes__copier';
    bouton.textContent = 'Copier';
    bouton.setAttribute('aria-label', 'Copier les commandes');
    bouton.addEventListener('click', () => {
        navigator.clipboard?.writeText(bloc.querySelector('code')?.innerText ?? bloc.innerText).then(() => {
            bouton.textContent = 'Copié ✓';
            setTimeout(() => (bouton.textContent = 'Copier'), 2500);
        });
    });
    bloc.append(bouton);
});

/**
 * Menu « Exporter » : le lien reprend les filtres du formulaire de la liste
 * et le texte de la zone « Rechercher » du tableau, pour exporter exactement
 * ce qui est affiché. Sans JavaScript, le lien exporte la liste non filtrée.
 */
document.addEventListener('click', (evenement) => {
    const lien = evenement.target.closest('a[data-export]');

    if (!lien) {
        return;
    }

    const url = new URL(lien.href, window.location.origin);
    const formulaire = lien.dataset.formulaire ? document.querySelector(lien.dataset.formulaire) : null;

    if (formulaire) {
        new FormData(formulaire).forEach((valeur, cle) => {
            if (valeur !== '') {
                url.searchParams.append(cle, valeur);
            }
        });
    }

    const recherche = lien.dataset.tableau
        ? document.querySelector(`${lien.dataset.tableau}_wrapper input[type="search"]`)?.value.trim()
        : '';

    if (recherche) {
        url.searchParams.set('recherche_tableau', recherche);
    }

    evenement.preventDefault();
    window.location.href = url.toString();
});
