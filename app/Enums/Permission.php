<?php

namespace App\Enums;

/**
 * Permissions de la plateforme (Spatie laravel-permission).
 */
enum Permission: string
{
    // Accès aux espaces (exigé en plus du rôle sur chaque groupe de routes)
    case AccederEspaceAdmin = 'acceder-espace-admin';
    case AccederEspaceAgent = 'acceder-espace-agent';
    case AccederEspacePartenaire = 'acceder-espace-partenaire';

    // Administration
    case GererUtilisateurs = 'gerer-utilisateurs';
    case GererCartes = 'gerer-cartes';
    case GererPartenaires = 'gerer-partenaires';
    case GererTaux = 'gerer-taux';
    case GererActivations = 'gerer-activations';
    case VoirTransactions = 'voir-transactions';
    case VoirStatistiques = 'voir-statistiques';
    case VoirJournalAudit = 'voir-journal-audit';
    case GererParametres = 'gerer-parametres';
    case GererRoles = 'gerer-roles';

    // Agent
    case ActiverCarte = 'activer-carte';
    case RechercherCarte = 'rechercher-carte';
    case VoirSesActivations = 'voir-ses-activations';
    case SignalerCartePerdue = 'signaler-carte-perdue';

    // Partenaire
    case VerifierCarte = 'verifier-carte';
    case ConfirmerOtp = 'confirmer-otp';
    case VoirSesTransactions = 'voir-ses-transactions';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
