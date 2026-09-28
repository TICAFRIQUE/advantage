<?php

namespace App\Enums;

/**
 * Rôles système de la plateforme. Ils ne sont jamais supprimables.
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Agent = 'agent';
    case Partenaire = 'partenaire';

    public function libelle(): string
    {
        return match ($this) {
            self::Superadmin => 'Super administrateur',
            self::Admin => 'Administrateur',
            self::Agent => 'Agent',
            self::Partenaire => 'Partenaire',
        };
    }

    /**
     * Permissions accordées au rôle. Le superadmin n'en reçoit aucune :
     * il est autorisé partout via Gate::before().
     *
     * L'admin cumule ses permissions avec celles de l'agent et du partenaire
     * (il peut tout faire sauf gérer les rôles et les paramètres) ; chaque
     * action reste tracée à son nom et à son rôle.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Superadmin => [],
            self::Admin => [
                Permission::AccederEspaceAdmin,
                Permission::GererUtilisateurs,
                Permission::GererCartes,
                Permission::GererPartenaires,
                Permission::GererTaux,
                Permission::GererActivations,
                Permission::VoirTransactions,
                Permission::VoirStatistiques,
                Permission::VoirJournalAudit,
                Permission::VoirSmsSimules,
                ...self::Agent->permissions(),
                ...self::Partenaire->permissions(),
            ],
            self::Agent => [
                Permission::AccederEspaceAgent,
                Permission::ActiverCarte,
                Permission::RechercherCarte,
                Permission::VoirSesActivations,
                Permission::SignalerCartePerdue,
            ],
            self::Partenaire => [
                Permission::AccederEspacePartenaire,
                Permission::VerifierCarte,
                Permission::ConfirmerOtp,
                Permission::VoirSesTransactions,
            ],
        };
    }

    /**
     * Nom de la route d'accueil de l'espace du rôle.
     */
    public function routeAccueil(): string
    {
        return match ($this) {
            self::Superadmin, self::Admin => 'admin.tableau-de-bord',
            self::Agent => 'agent.tableau-de-bord',
            self::Partenaire => 'partenaire.tableau-de-bord',
        };
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
