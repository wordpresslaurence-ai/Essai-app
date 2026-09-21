<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les vues utilisent la directive @vite, qui exige un manifeste compilé
        // (public/build/manifest.json). En test, le frontend n'est pas compilé,
        // donc on remplace Vite par un stub pour éviter ViteManifestNotFoundException.
        $this->withoutVite();
    }
}
