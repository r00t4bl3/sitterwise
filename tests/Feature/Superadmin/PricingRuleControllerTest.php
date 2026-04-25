<?php

use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Pricing Rules - Superadmin', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'admin']);
    });

    test('admin can view pricing rules index', function () {
        PricingRule::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/pricing-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/pricing-rules/index')
            ->has('pricingRules', 3)
            ->has('serviceTypes')
        );
    });

    test('guest cannot access pricing rules', function () {
        $response = $this->get('/pricing-rules');

        $response->assertRedirect('/login');
    });

    test('admin can create pricing rule', function () {
        $serviceType = fake()->randomElement(array_column(ServiceType::cases(), 'value'));

        $response = $this->actingAs($this->user)->post('/pricing-rules', [
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
    });

    test('admin can update pricing rule', function () {
        $pricingRule = PricingRule::factory()->create([
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 1,
        ]);

        $newServiceType = fake()->randomElement(array_column(ServiceType::cases(), 'value'));

        $response = $this->actingAs($this->user)->put("/pricing-rules/{$pricingRule->id}", [
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
    });

    test('admin can delete pricing rule', function () {
        $pricingRule = PricingRule::factory()->create();

        $response = $this->actingAs($this->user)->delete("/pricing-rules/{$pricingRule->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Pricing Rule deleted successfully');

        $this->assertDatabaseMissing('pricing_rules', [
            'id' => $pricingRule->id,
        ]);
    });

    test('validation requires service_type', function () {
        $response = $this->actingAs($this->user)->post('/pricing-rules', [
            'service_type' => '',
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['service_type']);
    });

    test('validation requires charge_to_client', function () {
        $response = $this->actingAs($this->user)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => '',
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['charge_to_client']);
    });

    test('validation requires paid_to_caregiver', function () {
        $response = $this->actingAs($this->user)->post('/pricing-rules', [
            'service_type' => 'Babysitter',
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => '',
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['paid_to_caregiver']);
    });

    test('validation requires payment_form', function () {
        $response = $this->actingAs($this->user)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => '',
            'sitterwise_cut' => 5.00,
        ]);

        $response->assertSessionHasErrors(['payment_form']);
    });

    test('validation requires sitterwise_cut', function () {
        $response = $this->actingAs($this->user)->post('/pricing-rules', [
            'service_type' => fake()->randomElement(array_column(ServiceType::cases(), 'value')),
            'number_of_children' => 2,
            'is_for_pets' => false,
            'charge_to_client' => 25.00,
            'paid_to_caregiver' => 20.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => '',
        ]);

        $response->assertSessionHasErrors(['sitterwise_cut']);
    });
});