<?php

use App\Enums\CaregiverStatus;
use App\Mail\ReferenceRequestMail;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function applicationManagementCreateApplication(array $overrides = []): array
{
    $user = User::factory()->create(['role' => 'caregiver', 'email' => $overrides['applicant_email'] ?? 'applicant@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Applicant->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-'.Str::random(4),
        'phone' => '555-1234',
        'date_of_birth' => '1990-01-01',
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => array_merge([
            'personal' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '555-1234',
                'dob' => '1990-01-01',
                'address_line1' => '123 Main St',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
            ],
            'sponsor' => [
                'first_name' => 'Sponsor',
                'last_name' => 'Person',
                'email' => 'sponsor@example.com',
                'relationship' => 'Friend',
            ],
        ], $overrides),
        'submitted_at' => now(),
    ]);

    // Create some reference requests
    ReferenceRequest::create([
        'token' => Str::random(32),
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'Ref One',
        'reference_email' => 'ref1@example.com',
        'relationship' => 'Former Employer',
        'years_known' => '3-5',
    ]);

    ReferenceRequest::create([
        'token' => Str::random(32),
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'Ref Two',
        'reference_email' => 'ref2@example.com',
        'relationship' => 'Friend',
        'years_known' => '5-10',
    ]);

    return [
        'application' => $application,
        'caregiver' => $caregiver,
        'user' => $user,
    ];
}

describe('Application Management - Index', function () {
    it('redirects unauthenticated users', function () {
        $response = $this->get('/applications');
        $response->assertRedirect('/login');
    });

    it('redirects non-admin users', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($user);

        $response = $this->get('/applications');
        $response->assertStatus(403);
    });

    it('lists applications for admin users', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        applicationManagementCreateApplication();

        $response = $this->get('/applications');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/applications/index')
            ->has('applications.data', 1)
        );
    });

    it('excludes an application whose caregiver has no user record and still renders', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $valid = applicationManagementCreateApplication(['applicant_email' => 'valid@example.com']);

        // Orphan: a caregiver referencing a user that no longer exists.
        $orphan = applicationManagementCreateApplication(['applicant_email' => 'orphan@example.com']);
        $orphan['caregiver']->user()->delete();

        $response = $this->get('/applications');
        $response->assertStatus(200);
        // Only the valid application is listed; the orphaned one is filtered out.
        $response->assertInertia(fn ($page) => $page
            ->component('admin/applications/index')
            ->has('applications.data', 1)
            ->where('applications.data.0.caregiver_id', $valid['caregiver']->id)
        );
    });

    it('lists applications for super admin users', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        applicationManagementCreateApplication();

        $response = $this->get('/applications');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/applications/index')
        );
    });

    it('excludes applications whose caregiver user has been deleted', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $data['user']->delete();

        $response = $this->get('/applications');
        $response->assertInertia(fn ($page) => $page
            ->has('applications.data', 0)
        );
    });

    it('shows reference progress in the list', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();

        // Complete one reference
        $ref = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->first();
        $ref->update(['rating' => 5, 'feedback' => 'Great!', 'submitted_at' => now()]);

        $response = $this->get('/applications');
        $response->assertInertia(fn ($page) => $page
            ->where('applications.data.0.completed_count', 1)
            ->where('applications.data.0.reference_count', 2)
        );
    });
});

describe('Application Management - Show', function () {
    it('shows application detail page', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();

        $response = $this->get("/applications/{$data['application']->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/applications/show')
            ->has('references', 2)
        );
    });

    it('includes sponsor info in application detail', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();

        $response = $this->get("/applications/{$data['application']->id}");
        $response->assertInertia(fn ($page) => $page
            ->where('application.data.sponsor.email', 'sponsor@example.com')
        );
    });

    it('handles deleted caregiver user gracefully', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $data['user']->delete();

        $response = $this->get("/applications/{$data['application']->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/applications/show')
            ->where('application.caregiver.email', null)
        );
    });

    it('marks completed and pending references correctly', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();

        $ref = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->first();
        $ref->update(['rating' => 4, 'feedback' => 'Good', 'submitted_at' => now()]);

        $response = $this->get("/applications/{$data['application']->id}");
        $response->assertInertia(fn ($page) => $page
            ->has('references', 2)
        );
    });
});

describe('Application Management - Resend Reference', function () {
    it('resends reference request email', function () {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $ref = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->first();
        $originalToken = $ref->token;

        $response = $this->post("/applications/{$data['application']->id}/references/{$ref->id}/resend");

        $response->assertSessionHas('success');

        $ref->refresh();
        expect($ref->token)->not->toBe($originalToken);

        Mail::assertQueued(ReferenceRequestMail::class, function ($mail) use ($ref) {
            return $mail->token === $ref->token;
        });
    });

    it('prevents resending completed reference', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $ref = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->first();
        $ref->update(['rating' => 5, 'feedback' => 'Done', 'submitted_at' => now()]);

        $response = $this->post("/applications/{$data['application']->id}/references/{$ref->id}/resend");

        $response->assertSessionHas('error');
    });

    it('returns 404 for reference not belonging to application caregiver', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data1 = applicationManagementCreateApplication();
        $data2 = applicationManagementCreateApplication(['applicant_email' => 'other@example.com']);
        $otherRef = ReferenceRequest::where('caregiver_id', $data2['caregiver']->id)->first();

        $response = $this->post("/applications/{$data1['application']->id}/references/{$otherRef->id}/resend");

        $response->assertStatus(404);
    });
});

describe('Application Management - Update Reference', function () {
    it('clears is_sponsor on other references when marking one as sponsor', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $refs = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->get();

        $first = $refs->first();
        $second = $refs->last();

        $first->update(['is_sponsor' => true]);

        $response = $this->patch(route('applications.references.update', [
            'application' => $data['application']->id,
            'referenceRequest' => $second->id,
        ]), [
            'reference_name' => $second->reference_name,
            'reference_email' => $second->reference_email,
            'is_sponsor' => true,
        ]);

        $response->assertSessionHas('success');

        $first->refresh();
        expect($first->is_sponsor)->toBeFalse();

        $second->refresh();
        expect($second->is_sponsor)->toBeTrue();
    });

    it('does not affect is_sponsor when updating without sponsor flag', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = applicationManagementCreateApplication();
        $ref = ReferenceRequest::where('caregiver_id', $data['caregiver']->id)->first();
        $ref->update(['is_sponsor' => true]);

        $response = $this->patch(route('applications.references.update', [
            'application' => $data['application']->id,
            'referenceRequest' => $ref->id,
        ]), [
            'reference_name' => $ref->reference_name,
            'reference_email' => $ref->reference_email,
        ]);

        $response->assertSessionHas('success');

        $ref->refresh();
        expect($ref->is_sponsor)->toBeTrue();
    });
});

describe('Application Management - Dashboard Counts', function () {
    it('includes pending applications count in admin dashboard', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        applicationManagementCreateApplication();

        $response = $this->get('/dashboard');
        $response->assertInertia(fn ($page) => $page
            ->has('admin.pendingApplicationsCount')
        );
    });

    it('includes stuck references count in admin dashboard', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get('/dashboard');
        $response->assertInertia(fn ($page) => $page
            ->has('admin.stuckReferencesCount')
        );
    });
});
