<?php

namespace App\Support;

/**
 * Libellés français des codes d'action et des types d'élément du journal
 * d'audit (affichage et filtres). Un code inconnu s'affiche tel quel.
 */
final class LibellesAudit
{
    /**
     * @var array<string, array<string, string>> groupe => [code => libellé]
     */
    public const ACTIONS = [
        'Connexions' => [
            'connexion.reussie' => 'Connexion réussie',
            'connexion.echec' => 'Connexion échouée',
            'connexion.refusee' => 'Connexion refusée (compte bloqué)',
            'deconnexion' => 'Déconnexion',
        ],
        'Comptes' => [
            'utilisateur.cree' => 'Compte créé',
            'utilisateur.modifie' => 'Compte modifié',
            'utilisateur.pin_reinitialise' => 'PIN réinitialisé',
            'compte.verrouille' => 'Compte verrouillé',
            'compte.deverrouille' => 'Compte déverrouillé',
            'utilisateur.supprime' => 'Compte supprimé',
            'utilisateur.restaure' => 'Compte restauré',
        ],
        'Rôles et permissions' => [
            'role.attribue' => 'Rôle attribué',
            'role.retire' => 'Rôle retiré',
            'role.cree' => 'Rôle créé',
            'role.renomme' => 'Rôle renommé',
            'role.supprime' => 'Rôle supprimé',
            'permission.attribuee' => 'Permission accordée',
            'permission.retiree' => 'Permission retirée',
        ],
        'Cartes et titulaires' => [
            'carte.activee' => 'Carte activée',
            'carte.modifiee' => 'Carte modifiée',
            'carte.statut_modifie' => 'Statut de carte modifié',
            'carte.supprimee' => 'Carte supprimée',
            'carte.consultee' => 'Carte consultée',
            'cartes.recherchees' => 'Recherche de cartes',
            'titulaire.cree' => 'Titulaire créé',
            'titulaire.modifie' => 'Titulaire modifié',
            'titulaire.recherche' => 'Recherche de titulaire',
        ],
        'Partenaires et transactions' => [
            'partenaire.cree' => 'Partenaire créé',
            'partenaire.modifie' => 'Partenaire modifié',
            'partenaire.supprime' => 'Partenaire supprimé',
            'partenaire.restaure' => 'Partenaire restauré',
            'partenaire.choisi' => 'Partenaire choisi (back-office)',
            'carte.verifiee' => 'Carte vérifiée en caisse',
            'otp.demande' => 'Code SMS envoyé',
            'otp.echec' => 'Code SMS erroné',
            'otp.valide' => 'Code SMS validé',
            'transaction.creee' => 'Transaction validée',
        ],
        'Exploitation' => [
            'sms.test' => 'SMS de test (configuration)',
            'donnees.purgees' => 'Purge des données techniques',
            'export.genere' => "Export d'une liste",
        ],
    ];

    /**
     * @var array<string, string>
     */
    public const ENTITES = [
        'Carte' => 'Carte',
        'Titulaire' => 'Titulaire',
        'Partenaire' => 'Partenaire',
        'Transaction' => 'Transaction',
        'DemandeOtp' => 'Code SMS',
        'User' => 'Compte',
        'RoleUtilisateur' => 'Rôle',
        'Role' => 'Rôle',
    ];

    public static function action(string $code): string
    {
        foreach (self::ACTIONS as $actions) {
            if (isset($actions[$code])) {
                return $actions[$code];
            }
        }

        return $code;
    }

    public static function entite(?string $type): ?string
    {
        return $type === null ? null : (self::ENTITES[$type] ?? $type);
    }

    /**
     * @return list<string>
     */
    public static function codesActions(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::ACTIONS)));
    }

    /**
     * Libellés des champs affichés dans le détail d'une entrée.
     *
     * @var array<string, string>
     */
    public const CHAMPS = [
        'nom' => 'Nom', 'prenom' => 'Prénoms', 'telephone' => 'Téléphone', 'email' => 'Email',
        'nom_utilisateur' => 'Identifiant', 'statut' => 'Statut', 'motif' => 'Motif', 'motif_statut' => 'Motif',
        'numero_carte' => 'Carte', 'titulaire' => 'Titulaire', 'active_le' => 'Activée le', 'expire_le' => 'Expire le',
        'partenaire' => 'Partenaire', 'taux_applique' => 'Remise', 'taux_reduction' => 'Remise', 'validee_par' => 'Validée par',
        'secteur' => 'Secteur', 'localisation' => 'Localisation', 'contact' => 'Contact', 'responsable' => 'Responsable',
        'navigateur' => 'Navigateur', 'valide' => 'Carte valide', 'tentatives' => 'Tentatives', 'bloque' => 'Code bloqué',
        'roles' => 'Rôles', 'permissions' => 'Permissions', 'libelle' => 'Libellé', 'name' => 'Code', 'espace' => 'Espace',
        'pilote' => 'Pilote SMS', 'liste' => 'Liste', 'format' => 'Format', 'lignes' => 'Lignes', 'filtres' => 'Filtres',
        'demandes_otp' => 'Codes supprimés', 'messages_sms' => 'SMS supprimés', 'transaction_id' => 'Transaction',
    ];

    /**
     * Détail d'une entrée en lignes lisibles : [libellé, valeur] pour une
     * donnée simple, [libellé, avant, après] pour une modification.
     *
     * @param  array<string, mixed>|null  $donnees
     * @return list<array{0: string, 1: string, 2?: string}>
     */
    public static function details(?array $donnees): array
    {
        if (empty($donnees)) {
            return [];
        }

        $lignes = [];
        $avant = is_array($donnees['avant'] ?? null) ? $donnees['avant'] : [];
        $apres = is_array($donnees['apres'] ?? null) ? $donnees['apres'] : [];

        foreach (array_diff_key($donnees, ['avant' => 1, 'apres' => 1]) as $cle => $valeur) {
            $lignes[] = [self::champ($cle), self::valeur($cle, $valeur)];
        }

        foreach (array_unique([...array_keys($avant), ...array_keys($apres)]) as $cle) {
            $lignes[] = array_key_exists($cle, $avant) && array_key_exists($cle, $apres)
                ? [self::champ($cle), self::valeur($cle, $avant[$cle]), self::valeur($cle, $apres[$cle])]
                : [self::champ($cle), self::valeur($cle, $apres[$cle] ?? $avant[$cle])];
        }

        return $lignes;
    }

    /**
     * Détail en texte sur une ligne (exports).
     *
     * @param  array<string, mixed>|null  $donnees
     */
    public static function detailsTexte(?array $donnees): ?string
    {
        $lignes = array_map(
            fn (array $l) => count($l) === 3 ? "{$l[0]} : {$l[1]} → {$l[2]}" : "{$l[0]} : {$l[1]}",
            self::details($donnees),
        );

        return $lignes === [] ? null : implode(' · ', $lignes);
    }

    private static function champ(string|int $cle): string
    {
        return self::CHAMPS[$cle] ?? ucfirst(str_replace('_', ' ', (string) $cle));
    }

    private static function valeur(string|int $cle, mixed $valeur): string
    {
        return match (true) {
            $valeur === null || $valeur === '' => '—',
            is_bool($valeur) => $valeur ? 'Oui' : 'Non',
            is_array($valeur) => array_is_list($valeur)
                ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $valeur))
                : implode(', ', array_map(fn ($k, $v) => self::champ($k).' '.(is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)), array_keys($valeur), $valeur)),
            in_array($cle, ['statut'], true) => ucfirst(str_replace('_', ' ', (string) $valeur)),
            default => (string) $valeur,
        };
    }
}
