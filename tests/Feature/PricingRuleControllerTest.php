<?php

use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pricing_rules_index()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        PricingRule::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/pricing-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/pricing-rules/index')
            ->has('pricingRules', 3)
            ->has('serviceTypes')
        );
    }

    public function test_guest_cannot_access_pricing_rules()
    {
        $response = $this->get('/pricing-rules');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_pricing_rule()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceType = fake()->randomElement(array_column(ServiceType::cases(), 'value'));
        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => $serviceType,
            'is_for_pets' => true,
            'charge_to_client' => 25.00,
            'charge_to_client_notes' => 'Standard rate',
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Pricing Rule created successfully');

        $this->assertDatabaseHas('pricing_rules', [
            'service_type' => $serviceType,
            'number_of_children' => null,
            'is_for_pets' => true,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);
    }

    public function test_admin_can_update_pricing_rule()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pricingRule = PricingRule::factory()->create([
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 1,
        ]);

        $newServiceType = fake()->randomElement(array_column(ServiceType::cases(), 'value'));
        $response = $this->actingAs($admin)->put("/pricing-rules/{$pricingRule->id}", [
            'service_type' => $newServiceType,
            'number_of_children' => null,
            'is_for_pets' => true,
            'charge_to_client' => 30.00,
            'charge_to_client_notes' => 'Pet sitting rate',
            'paid_to_caregiver' => 25.00,
            'payment_form' => 'OnPay (Payroll)',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Pricing Rule updated successfully');

        $this->assertDatabaseHas('pricing_rules', [
            'id' => $pricingRule->id,
            'service_type' => $newServiceType,
            'number_of_children' => null,
            'is_for_pets' => true,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 25.00,
            'payment_form' => 'OnPay (Payroll)',
            'sitterwise_cut' => 5.00,
        ]);
    }

    public function test_admin_can_delete_pricing_rule()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pricingRule = PricingRule::factory()->create();

        $response = $this->actingAs($admin)->delete("/pricing-rules/{$pricingRule->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Pricing Rule deleted successfully');

        $this->assertDatabaseMissing('pricing_rules', [
            'id' => $pricingRule->id,
        ]);
    }

    public function test_validation_requires_service_type()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => '',
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['service_type']);
    }

    public function test_validation_requires_charge_to_client()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => '',
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['charge_to_client']);
    }

    public function test_validation_requires_paid_to_caregiver()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => 'Babysitter',
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => '',
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['paid_to_caregiver']);
    }

    public function test_validation_requires_payment_form()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => '',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['payment_form']);
    }

    public function test_validation_requires_sitterwise_cut()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => '',
        ]);

        $response->assertSessionHasErrors(['sitterwise_cut']);
    }
}
