<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\UsesLegacyInMemoryDatabase;

class LegacyApiSecurityTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_siparis_api_requires_authentication(): void
    {
        $this->get('/SiparisApi.ashx?action=getOrders')
            ->assertRedirect('/login');
    }

    public function test_toplu_is_emri_api_requires_authentication(): void
    {
        $this->post('/TopluIsEmriApi.ashx?action=createWorkOrders', [])
            ->assertRedirect('/login');
    }
}
