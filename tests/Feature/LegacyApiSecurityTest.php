<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_siparis_api_rejects_mutating_actions_over_get(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=clearAllOrders')
            ->assertStatus(405)
            ->assertJson([
                'success' => false,
                'message' => 'Bu işlem yalnızca POST isteğiyle yapılabilir.',
            ]);
    }

    public function test_siparis_api_rejects_cross_origin_posts(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->withHeader('Origin', 'https://example.invalid')
            ->postJson('/SiparisApi.ashx?action=clearAllOrders', [])
            ->assertStatus(419)
            ->assertJson([
                'success' => false,
                'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.',
            ]);
    }

    private function makeAdminUser(): User
    {
        $user = new User();
        $user->forceFill([
            'id' => 1,
            'name' => 'Admin',
            'surname' => 'User',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'department_id' => null,
            'personnel_no' => 1,
        ]);

        return $user;
    }
}
