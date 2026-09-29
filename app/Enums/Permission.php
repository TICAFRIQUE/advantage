<?php

namespace App\Enums;

/**
 * Références typées des permissions utilisées dans le code.
 *
 * Libellés, groupes, espace et rôles par défaut sont décrits dans
 * config/permissions.php (source de vérité) ; un test garantit que cet enum
 * et la configuration restent identiques.
 */
enum Permission: string
{
    // Général (back-office)
    case AccederGestion = 'acceder-gestion';
    case VoirTableauDeBord = 'voir-tableau-de-bord';

    // Cartes
    case ActiverCarte = 'activer-carte';
    case VoirCartes = 'voir-cartes';
    case GererStatutCarte = 'gerer-statut-carte';
    case ModifierTitulaire = 'modifier-titulaire';
    case ModifierTelephoneTitulaire = 'modifier-telephone-titulaire';
    case VoirRapportCartes = 'voir-rapport-cartes';

    // Partenaires
    case VoirPartenaires = 'voir-partenaires';
    case GererPartenaires = 'gerer-partenaires';
    case SupprimerPartenaires = 'supprimer-partenaires';
    case GererOperateursPartenaires = 'gerer-operateurs-partenaires';
    case EffectuerTransactionPartenaire = 'effectuer-transaction-partenaire';
    case VoirRapportTransactions = 'voir-rapport-transactions';

    // Paramètres
    case GererUtilisateurs = 'gerer-utilisateurs';
    case ReinitialiserPin = 'reinitialiser-pin';
    case SupprimerComptes = 'supprimer-comptes';
    case GererRoles = 'gerer-roles';
    case VoirJournalAudit = 'voir-journal-audit';
    case PurgerJournalAudit = 'purger-journal-audit';

    // Exports et outils
    case ExporterDonnees = 'exporter-donnees';
    case VoirSmsSimules = 'voir-sms-simules';

    // Superadmin uniquement (espace « superadmin », jamais attribuable)
    case RestaurerElements = 'restaurer-elements';
    case VoirCommandesProduction = 'voir-commandes-production';
    case TesterSms = 'tester-sms';

    // Espace partenaire
    case AccederEspacePartenaire = 'acceder-espace-partenaire';
    case EffectuerTransaction = 'effectuer-transaction';
    case VoirHistoriqueTransactions = 'voir-historique-transactions';

    public function libelle(): string
    {
        return (string) (self::definitions()[$this->value]['libelle'] ?? $this->value);
    }

    public function espace(): string
    {
        return (string) self::definitions()[$this->value]['espace'];
    }

    /**
     * Toutes les permissions déclarées dans la configuration, à plat.
     *
     * @return array<string, array{libelle: string, roles: list<string>, espace: string, groupe: string}>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (config('permissions.groupes') as $cleGroupe => $groupe) {
            foreach ($groupe['permissions'] as $nom => $permission) {
                $definitions[$nom] = $permission + ['espace' => $groupe['espace'], 'groupe' => $cleGroupe];
            }
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
