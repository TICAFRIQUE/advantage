<?php

namespace App\Enums;

/**
 * Rôles système. Leurs métadonnées (libellé, espace, verrouillage) et les
 * permissions par défaut sont dans config/permissions.php.
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Agent = 'agent';
    case Partenaire = 'partenaire';

    public function libelle(): string
    {
        return (string) config("permissions.roles.{$this->value}.libelle", $this->value);
    }

    /**
     * gestion (back-office) ou partenaire.
     */
    public function espace(): string
    {
        return (string) config("permissions.roles.{$this->value}.espace");
    }

    /**
     * Rôle dont les permissions ne sont jamais modifiables (superadmin).
     */
    public function estVerrouille(): bool
    {
        return (bool) config("permissions.roles.{$this->value}.verrouille", false);
    }

    /**
     * Permissions attribuées par défaut (première synchronisation uniquement).
     *
     * @return list<string>
     */
    public function permissionsParDefaut(): array
    {
        return array_keys(array_filter(
            Permission::definitions(),
            fn (array $permission) => in_array($this->value, $permission['roles'], true),
        ));
    }

    /**
     * Nom de la route d'accueil de l'espace du rôle.
     */
    public function routeAccueil(): string
    {
        return $this->espace() === 'partenaire' ? 'partenaire.tableau-de-bord' : 'gestion.tableau-de-bord';
    }

    /**
     * Rôles du back-office (admin, agent, superadmin).
     *
     * @return list<self>
     */
    public static function roleGestion(): array
    {
        return [self::Superadmin, self::Admin, self::Agent];
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
