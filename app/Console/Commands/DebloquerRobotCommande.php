<?php

namespace App\Console\Commands;

use App\Services\Robots;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('robots:debloquer {ip : Adresse IP bloquée par la protection anti-robots}')]
#[Description('Lève le blocage temporaire d\'une adresse IP (protection anti-robots)')]
class DebloquerRobotCommande extends Command
{
    public function handle(): int
    {
        $ip = (string) $this->argument('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->components->error("Adresse IP invalide : {$ip}");

            return self::FAILURE;
        }

        Robots::debloquer($ip)
            ? $this->components->info("Adresse {$ip} débloquée.")
            : $this->components->warn("L'adresse {$ip} n'était pas bloquée (compteur de sondes remis à zéro).");

        return self::SUCCESS;
    }
}
