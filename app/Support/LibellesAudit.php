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
}
