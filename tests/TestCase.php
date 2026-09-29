<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les vues ne dépendent pas d'un build front (npm run build) en test.
        $this->withoutVite();

        // Aucun appel HTTP réel (fournisseur SMS…) : tout appel non simulé échoue.
        Http::preventStrayRequests();
    }
}
