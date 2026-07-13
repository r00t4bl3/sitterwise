<?php

use App\Models\PricingRule;
use App\Models\User;
use Database\Seeders\PricingRulesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingSimulatorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingRulesTableSeeder::class);
    }

    public function test_guest_cannot_access_simulator_page()
    {
        $response = $this->get('/pricing-rules/simulator');

        $response->assertRedirect('/login');
    }

    public function test_simulator_page_renders_with_rules_and_service_types()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/pricing-rules/simulator');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/pricing-rules/simulator')
            ->has('pricingRules')
            ->has('serviceTypes')
            ->has('maxChildren')
        );
    }

    public function test_simulate_returns_correct_breakdown_for_exact_match()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'number_of_children' => 2,
            'is_for_pets' => false,
            'hours' => 5,
        ]);

        $response->assertOk();
        $response->assertJson([
            'is_fallback' => false,
            'hourly' => [
                'charge_to_client' => 35.00,
                'paid_to_caregiver' => 23.00,
                'sitterwise_cut' => 12.00,
            ],
            'totals' => [
                'charge_to_client' => 175.00,
                'paid_to_caregiver' => 115.00,
                'sitterwise_cut' => 60.00,
            ],
        ]);
    }

    public function test_simulate_returns_fallback_when_no_exact_match()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'petsitter',
            'is_for_pets' => false,
            'hours' => 5,
        ]);

        $response->assertOk();
        $response->assertJson([
            'is_fallback' => true,
        ]);
        $response->assertJsonStructure([
            'matched_rule',
            'is_fallback',
            'hourly' => ['charge_to_client', 'paid_to_caregiver', 'sitterwise_cut'],
            'totals' => ['charge_to_client', 'paid_to_caregiver', 'sitterwise_cut'],
        ]);
    }

    public function test_simulate_handles_petsitter_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'petsitter',
            'is_for_pets' => true,
            'hours' => 3,
        ]);

        $response->assertOk();
        $response->assertJson([
            'is_fallback' => false,
            'hourly' => [
                'charge_to_client' => 30.00,
                'paid_to_caregiver' => 23.00,
                'sitterwise_cut' => 7.00,
            ],
            'totals' => [
                'charge_to_client' => 90.00,
                'paid_to_caregiver' => 69.00,
                'sitterwise_cut' => 21.00,
            ],
        ]);
    }

    public function test_simulate_validates_required_fields()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => '',
            'hours' => 5,
        ]);

        $response->assertInvalid(['service_type' => 'required']);
    }

    public function test_simulate_validates_hours_min()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'hours' => -1,
        ]);

        $response->assertInvalid(['hours' => 'The hours field must be at least 0.']);
    }

    public function test_simulate_validates_service_type_must_be_valid()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'invalid_type',
            'hours' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_access_calculate_endpoint()
    {
        $response = $this->post('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'hours' => 5,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_simulate_validates_hours_required()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
        ]);

        $response->assertInvalid(['hours' => 'required']);
    }

    public function test_simulate_validates_hours_is_zero()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'number_of_children' => 1,
            'is_for_pets' => false,
            'hours' => 0,
        ]);

        $response->assertOk();
        $response->assertJson([
            'totals' => [
                'charge_to_client' => 0,
                'paid_to_caregiver' => 0,
                'sitterwise_cut' => 0,
            ],
        ]);
    }

    public function test_simulate_validates_negative_number_of_children()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'number_of_children' => -1,
            'hours' => 5,
        ]);

        $response->assertInvalid(['number_of_children' => 'The number of children field must be at least 0.']);
    }

    public function test_simulate_validates_non_numeric_hours()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'hours' => 'abc',
        ]);

        $response->assertStatus(422);
    }

    public function test_simulate_returns_null_when_no_rules_exist_for_service_type()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Use a service type that has no pricing rules
        PricingRule::where('service_type', 'babysitter')->delete();

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'number_of_children' => 1,
            'is_for_pets' => false,
            'hours' => 5,
        ]);

        $response->assertOk();
        $response->assertJson([
            'matched_rule' => null,
            'is_fallback' => true,
            'hourly' => null,
            'totals' => null,
        ]);
    }

    public function test_simulate_validates_number_of_children_zero()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/pricing-rules/simulator/calculate', [
            'service_type' => 'babysitter',
            'number_of_children' => 0,
            'is_for_pets' => false,
            'hours' => 5,
        ]);

        $response->assertOk();
    }

    public function test_gap_analysis_includes_all_service_types()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/pricing-rules/simulator');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/pricing-rules/simulator')
            ->has('serviceTypes', 6)
            ->has('pricingRules')
            ->has('maxChildren')
        );
    }
}
