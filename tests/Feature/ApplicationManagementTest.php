<?php

use App\Mail\ReferenceRequestMail;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\CaregiverStatus;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    CaregiverStatus::create([
        'name' => 'applicant',
        'color' => '#F48A91',
        'is_active' => true,
        'sort_order' => 1,
    ]);
});

function applicationManagementCreateApplication(array $overrides = []): array
{
    $status = CaregiverStatus::where('name', 'applicant')->first();

    $user = User::factory()->create(['role' => 'caregiver', 'email' => $overrides['applicant_email'] ?? 'applicant@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status_id' => $status->id,
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
            ->component('applications/index')
            ->has('applications.data', 1)
        );
    });

    it('lists applications for super admin users', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        applicationManagementCreateApplication();

        $response = $this->get('/applications');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('applications/index')
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
            ->component('applications/show')
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
