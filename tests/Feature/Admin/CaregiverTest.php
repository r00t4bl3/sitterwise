<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(AttributeDefinitionSeeder::class);
});

describe('Caregiver - Admin', function () {
    // Guest tests - all should redirect to login
    test('guests are redirected to login when accessing caregivers index', function () {
        $response = $this->get(route('caregivers.index'));
        $response->assertRedirect(route('login'));
    });

    test('guests are redirected to login when accessing caregiver show page', function () {
        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.show', $caregiver));
        $response->assertRedirect(route('login'));
    });

    test('guests are redirected to login when accessing caregiver create page', function () {
        $response = $this->get(route('caregivers.create'));
        $response->assertRedirect(route('login'));
    });

    test('guests are redirected to login when accessing caregiver edit page', function () {
        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.edit', $caregiver));
        $response->assertRedirect(route('login'));
    });

    test('guests cannot store a new caregiver', function () {
        $response = $this->post(route('caregivers.store'), [
            'first_name' => 'Test',
            'last_name' => 'Caregiver',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => CaregiverStatus::Active->value,
        ]);

        $response->assertRedirect(route('login'));
    });

    test('guests cannot update a caregiver', function () {
        $caregiver = Caregiver::factory()->create();

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => 'Updated',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('guests cannot access search suggestions', function () {
        $response = $this->get(route('caregivers.searchSuggestions', ['q' => 'John']));
        $response->assertRedirect(route('login'));
    });

    // Regular authenticated user (non-admin) tests - nothing should work
    test('regular users cannot view caregivers index', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.index'));
        $response->assertForbidden();
    });

    test('regular users cannot view caregiver show page', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.show', $caregiver));
        $response->assertForbidden();
    });

    test('regular users cannot view caregiver create page - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.create'));
        $response->assertForbidden();
    });

    test('regular users cannot view caregiver edit page - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.edit', $caregiver));
        $response->assertForbidden();
    });

    test('regular users cannot store a new caregiver - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $response = $this->post(route('caregivers.store'), [
            'first_name' => 'Test',
            'last_name' => 'Caregiver',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => CaregiverStatus::Active->value,
        ]);

        $response->assertForbidden();
    });

    test('regular users cannot update a caregiver - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => 'Updated',
        ]);

        $response->assertForbidden();
    });

    test('regular users cannot access search suggestions - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.searchSuggestions', ['q' => 'John']));
        $response->assertForbidden();
    });

    // Admin user tests - all routes should work
    test('admin users can view caregivers index', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.index'));
        $response->assertSuccessful();
    });

    test('the index renders a caregiver whose user record is missing', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Reproduce the orphan: a caregiver referencing a user that no longer exists.
        $caregiver = Caregiver::factory()->create();
        $caregiver->user()->delete();

        $this->get(route('caregivers.index', ['sort' => 'id', 'direction' => 'desc']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('caregivers.data.0.id', $caregiver->id)
                ->where('caregivers.data.0.user', null)
            );
    });

    test('the show page renders a caregiver whose user record is missing', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $caregiver = Caregiver::factory()->create();
        $caregiver->user()->delete();

        $this->get(route('caregivers.show', $caregiver))->assertSuccessful();
    });

    test('admin users can view caregiver show page', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.show', $caregiver));
        $response->assertSuccessful();
    });

    test('admin users can view caregiver create page', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.create'));
        $response->assertSuccessful();
    });

    test('admin users can view caregiver edit page', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $response = $this->get(route('caregivers.edit', $caregiver));
        $response->assertSuccessful();
    });

    test('admin users can create a caregiver', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('caregivers.store'), [
            'first_name' => 'Test',
            'last_name' => 'Caregiver',
            'email' => 'testcaregiver@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => CaregiverStatus::Active->value,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'testcaregiver@example.com',
        ]);
    });

    test('admin users can update a caregiver', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => 'UpdatedFirstName',
            'last_name' => $caregiver->last_name,
            'phone' => $caregiver->phone,
            'address_line1' => $caregiver->address_line1,
            'address_line2' => $caregiver->address_line2,
            'address_city' => $caregiver->address_city,
            'address_state' => $caregiver->address_state,
            'address_zip' => $caregiver->address_zip,
            'status' => $caregiver->status->value,
        ]);

        $response->assertRedirect();

        $caregiver->refresh();
        expect($caregiver->first_name)->toBe('UpdatedFirstName');
    });

    test('admin can set the languages a caregiver speaks', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'languages' => ['spanish', 'mandarin_chinese'],
        ])->assertRedirect();

        expect($caregiver->fresh()->languages)->toBe(['spanish', 'mandarin_chinese']);
    });

    test('admin can clear the languages a caregiver speaks', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create(['languages' => ['spanish']]);

        $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'languages' => [],
        ])->assertRedirect();

        expect($caregiver->fresh()->languages)->toBe([]);
    });

    test('an invalid language value is rejected', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create(['languages' => ['spanish']]);

        $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'languages' => ['klingon'],
        ])->assertSessionHasErrors('languages.0');

        expect($caregiver->fresh()->languages)->toBe(['spanish']);
    });

    test('admin users can update caregiver educations', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'educations' => [
                [
                    'education_type' => 'high_school',
                    'school_name' => 'San Diego High School',
                    'graduation_year' => 2020,
                    'degree' => null,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('caregiver_educations', [
            'caregiver_id' => $caregiver->id,
            'education_type' => 'high_school',
            'school_name' => 'San Diego High School',
            'graduation_year' => 2020,
        ]);
    });

    test('admin clearing a saved certification file nulls the pivot and deletes the stored file', function () {
        Storage::fake('documents');
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $certType = CertificationType::query()->firstOrFail();

        $path = 'certifications/existing-cert.pdf';
        Storage::disk('documents')->put($path, 'dummy-contents');
        $caregiver->certifications()->detach();
        $caregiver->certifications()->attach($certType->id, [
            'file_path' => $path,
            'expiration_date' => null,
            'verified_at' => null,
            'notes' => null,
        ]);
        Storage::disk('documents')->assertExists($path);

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'certifications' => [
                [
                    'certification_type_id' => $certType->id,
                    'expiration_date' => null,
                    'verified_at' => null,
                    'file_path' => null, // cleared via the X control
                    'notes' => null,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('caregiver_certifications', [
            'caregiver_id' => $caregiver->id,
            'certification_type_id' => $certType->id,
            'file_path' => null,
        ]);
        Storage::disk('documents')->assertMissing($path);
    });

    test('updating a caregiver without clearing keeps the saved certification file', function () {
        Storage::fake('documents');
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $certType = CertificationType::query()->firstOrFail();

        $path = 'certifications/keep-cert.pdf';
        Storage::disk('documents')->put($path, 'dummy-contents');
        $caregiver->certifications()->detach();
        $caregiver->certifications()->attach($certType->id, [
            'file_path' => $path,
            'expiration_date' => null,
            'verified_at' => null,
            'notes' => null,
        ]);

        $response = $this->patch(route('caregivers.update', $caregiver), [
            'first_name' => $caregiver->first_name,
            'last_name' => $caregiver->last_name,
            'status' => $caregiver->status->value,
            'certifications' => [
                [
                    'certification_type_id' => $certType->id,
                    'expiration_date' => null,
                    'verified_at' => null,
                    'file_path' => $path, // unchanged
                    'notes' => null,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('caregiver_certifications', [
            'caregiver_id' => $caregiver->id,
            'certification_type_id' => $certType->id,
            'file_path' => $path,
        ]);
        Storage::disk('documents')->assertExists($path);
    });

    test('admin users can search caregivers', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create([
            'first_name' => 'SearchableName',
            'last_name' => 'Test',
        ]);

        $response = $this->get(route('caregivers.index', ['search' => 'Searchable']));
        $response->assertSuccessful();
    });

    test('admin users can access search suggestions endpoint', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->get(route('caregivers.searchSuggestions', ['q' => 'John']));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
    });

    test('caregiver index shows caregiver list with pagination', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Caregiver::factory()->count(5)->create();

        $response = $this->get(route('caregivers.index'));
        $response->assertSuccessful();
    });

    test('caregiver show page displays all caregiver information', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->get(route('caregivers.show', $caregiver));
        $response->assertSuccessful();

        $response->assertSee($caregiver->first_name);
        $response->assertSee($caregiver->last_name);
    });

    test('guests cannot reset caregiver password', function () {
        $caregiver = Caregiver::factory()->create();

        $response = $this->post(route('caregivers.resetPassword', $caregiver), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('regular users cannot reset caregiver password - forbidden', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->post(route('caregivers.resetPassword', $caregiver), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertForbidden();
    });

    test('admin users can reset caregiver password', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();

        $response = $this->post(route('caregivers.resetPassword', $caregiver), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();

        $caregiver->refresh();
        expect(Hash::check('newpassword123', $caregiver->user->password))->toBeTrue();
    });

    test('admin users cannot reset caregiver password with mismatched confirmation', function () {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $caregiver = Caregiver::factory()->create();
        $oldPassword = $caregiver->user->password;

        $response = $this->post(route('caregivers.resetPassword', $caregiver), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'differentpassword123',
        ]);

        $response->assertSessionHasErrors();

        $caregiver->refresh();
        expect($caregiver->user->password)->toBe($oldPassword);
    });
});
