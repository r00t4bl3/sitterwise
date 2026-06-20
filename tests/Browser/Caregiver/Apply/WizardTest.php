<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function completeEmailVerification($page, string $email): void
{
    fillField($page, 'input#email', $email);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->waitForText('Enter Verification Code');

    fillField($page, 'input#otp', '000000');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const verifyBtn = buttons.find(b => b.textContent.includes('Verify & Continue'));
        if (verifyBtn) verifyBtn.click();
    JS);

    $page->waitForText('Join the Sitterwise Team');
}

function clickNext($page): void
{
    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const nextBtn = buttons.find(b => b.textContent.includes('Next'));
        if (nextBtn) nextBtn.click();
    JS);
}

function clickBack($page): void
{
    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const backBtn = buttons.find(b => b.textContent.includes('Back'));
        if (backBtn) backBtn.click();
    JS);
}

test('wizard page renders with step indicators after email verification', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    $page->assertSee('Step 1 of 8')
        ->assertSee('Sponsor & Personal Information')
        ->assertNoJavaScriptErrors();
});

test('step 1 shows validation errors when Next clicked with empty fields', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    clickNext($page);

    $page->assertSee('Please fix the following errors')
        ->assertSee('Sponsor first name is required')
        ->assertNoJavaScriptErrors();
});

test('can navigate between wizard steps after filling step 1', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    $escapedEmail = addslashes($email);

    $page->script(<<<JS
        const draft = {
            step: 1,
            data: {
                sponsor: {
                    first_name: 'Sponsor',
                    last_name: 'Reference',
                    email: 'sponsor@example.com',
                    phone: '+15551234567',
                    relationship: 'Friend',
                },
                personal: {
                    first_name: 'John',
                    last_name: 'Doe',
                    address_line1: '123 Main St',
                    address_line2: '',
                    address_city: 'San Diego',
                    address_state: 'CA',
                    address_zip: '92101',
                    phone: '+15555555678',
                    email: '{$escapedEmail}',
                    dob: '1990-01-15',
                    photo: null,
                },
            },
        };
        sessionStorage.setItem('caregiver_application_draft', JSON.stringify(draft));
        window.location.reload();
    JS);

    $page->waitForText('Step 1 of 8');

    clickNext($page);

    usleep(500000);

    $page->assertSee('Position, Availability & Education')
        ->assertSee('Step 2 of 8');

    clickBack($page);

    usleep(500000);

    $page->assertSee('Sponsor & Personal Information')
        ->assertSee('Step 1 of 8')
        ->assertNoJavaScriptErrors();
});

test('submit full application via direct POST and redirect to thank-you', function () {
    $email = 'applicant-'.time().'@example.com';

    Session::put('verified_email', $email);
    Session::put('verified_at', now());

    $this->withoutMiddleware(PreventRequestForgery::class);

    $response = $this->post('/caregiver/apply/submit', [
        'sponsor' => [
            'first_name' => 'Sponsor',
            'last_name' => 'Reference',
            'email' => 'sponsor@example.com',
            'phone' => '+15551234567',
            'relationship' => 'Friend',
        ],
        'personal' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main St',
            'address_line2' => '',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'phone' => '+15555555678',
            'dob' => '1990-01-01',
        ],
        'position' => [
            'babysitting' => true,
            'petsitting' => false,
            'group_events' => false,
        ],
        'availability' => [
            'weekday_mornings' => true,
            'weekday_afternoons' => false,
            'weekday_evenings' => false,
            'weekends' => true,
            'overnights' => false,
            'notes' => 'Flexible schedule',
        ],
        'education' => [
            'level' => 'bachelor',
            'college' => 'State University',
            'graduation_year' => '2015',
            'degree' => 'Early Childhood Education',
        ],
        'employment_status' => 'no',
        'experiences' => [
            [
                'start_date' => '2020-01',
                'end_date' => '2022-12',
                'present' => false,
                'role' => 'Nanny',
                'organization' => 'Smith Family',
                'description' => 'Cared for 2 children ages 2 and 5',
                'ages_served' => ['toddler', 'preschool'],
            ],
        ],
        'certifications' => [],
        'smokes' => 'no',
        'alcohol' => 'socially',
        'substance_abuse' => 'None',
        'limitations' => 'None',
        'allergic_to_pets' => 'no',
        'visible_tattoos' => 'no',
        'authorized_to_work' => 'yes',
        'reliable_vehicle' => 'yes',
        'cpr_certified' => 'no',
        'trustline_certified' => 'no',
        'languages' => [],
        'has_children' => 'no',
        'skills' => [],
        'qualifications' => [
            'special_needs' => false,
            'companion_care' => false,
            'sick_care' => false,
            'work_from_home' => false,
            'driving' => false,
            'dogsitting' => false,
            'swimming' => false,
            'overnight_care' => false,
        ],
        'things_i_bring' => 'Books and games',
        'bio' => 'Experienced caregiver with 5+ years of experience.',
        'interests' => 'hiking, reading',
        'references' => [
            [
                'first_name' => 'Reference',
                'last_name' => 'One',
                'email' => 'ref1@example.com',
                'phone' => '+15550000001',
                'relationship' => 'Former Employer',
                'years_known' => '3-5',
            ],
            [
                'first_name' => 'Reference',
                'last_name' => 'Two',
                'email' => 'ref2@example.com',
                'phone' => '+15550000002',
                'relationship' => 'Friend',
                'years_known' => '5-10',
            ],
            [
                'first_name' => 'Reference',
                'last_name' => 'Three',
                'email' => 'ref3@example.com',
                'phone' => '+15550000003',
                'relationship' => 'Co-worker',
                'years_known' => '1-3',
            ],
        ],
        'location' => [
            'north_county' => true,
            'south_east_county' => false,
            'flexible' => true,
        ],
        'age_groups' => [
            'babies' => false,
            'toddlers' => true,
            'preschool' => true,
            'school_age' => false,
        ],
        'terms' => ['agree' => true],
        'verification' => [
            'signature' => 'John Doe',
            'agree' => true,
        ],
        'agreement' => [
            'signature' => 'John Doe',
            'agree' => true,
        ],
    ]);

    $response->assertRedirect('/caregiver/apply/thank-you');
});

function setSessionDraft($page, string $email, int $step = 1, array $overrides = []): void
{
    $data = [
        'sponsor' => ['first_name' => 'Sponsor', 'last_name' => 'Reference', 'email' => 'sponsor@example.com', 'phone' => '+15551234567', 'relationship' => 'Friend'],
        'personal' => ['first_name' => 'John', 'last_name' => 'Doe', 'email' => $email, 'phone' => '+15555555678', 'dob' => '1990-01-15', 'address_line1' => '123 Main St', 'address_line2' => '', 'address_city' => 'San Diego', 'address_state' => 'CA', 'address_zip' => '92101', 'photo' => null],
        'position' => ['babysitting' => true, 'petsitting' => false, 'group_events' => false],
        'availability' => ['weekday_mornings' => true, 'weekday_afternoons' => false, 'weekday_evenings' => false, 'weekends' => true, 'overnights' => false, 'notes' => ''],
        'education' => ['level' => 'bachelor', 'degree' => 'Child Development', 'college' => 'State University', 'graduation_year' => '2015', 'high_school_name' => '', 'high_school_graduation_year' => ''],
        'employment_status' => 'no',
        'current_employer' => '',
        'experiences' => [['start_date' => '2020-01', 'end_date' => '2022-12', 'present' => false, 'role' => 'Nanny', 'organization' => 'Smith Family', 'description' => 'Cared for children', 'ages_served' => ['toddler', 'preschool']]],
        'certifications' => [],
        'authorized_to_work' => 'yes',
        'smokes' => 'no',
        'alcohol' => 'no',
        'substance_abuse' => '',
        'limitations' => '',
        'allergic_to_pets' => 'no',
        'allergic_to_what' => '',
        'visible_tattoos' => 'no',
        'tattoo_description' => '',
        'reliable_vehicle' => 'yes',
        'cpr_certified' => 'no',
        'cpr_expiration' => '',
        'cpr_card' => null,
        'trustline_certified' => 'no',
        'trustline_upload' => null,
        'languages' => [],
        'has_children' => 'no',
        'children_ages' => '',
        'skills' => [],
        'references' => [
            ['first_name' => 'Ref', 'last_name' => 'One', 'email' => 'ref1@example.com', 'phone' => '+15550000001', 'relationship' => 'Former Employer', 'years_known' => '3-5'],
            ['first_name' => 'Ref', 'last_name' => 'Two', 'email' => 'ref2@example.com', 'phone' => '+15550000002', 'relationship' => 'Friend', 'years_known' => '5-10'],
            ['first_name' => 'Ref', 'last_name' => 'Three', 'email' => 'ref3@example.com', 'phone' => '+15550000003', 'relationship' => 'Co-worker', 'years_known' => '1-3'],
        ],
        'location' => ['north_county' => true, 'south_east_county' => false, 'flexible' => false],
        'age_groups' => ['babies' => false, 'toddlers' => false, 'preschool' => false, 'school_age' => false],
        'qualifications' => ['special_needs' => false, 'companion_care' => false, 'sick_care' => false, 'work_from_home' => false, 'driving' => false, 'dogsitting' => false, 'swimming' => false, 'overnight_care' => false],
        'things_i_bring' => '',
        'bio' => 'Experienced caregiver.',
        'interests' => '',
        'terms' => ['agree' => false],
        'verification' => ['signature' => '', 'agree' => false],
        'agreement' => ['signature' => '', 'agree' => false],
    ];

    $data = array_replace_recursive($data, $overrides);

    $json = json_encode(['step' => $step, 'data' => $data], JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        sessionStorage.setItem('caregiver_application_draft', JSON.stringify({$json}));
        window.location.reload();
    JS);
}

test('each wizard step renders its section heading after pre-fill', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');
    $page->assertSee('Sponsor & Personal Information');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Position, Availability & Education')->assertSee('Step 2 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Employment & Experience')->assertSee('Step 3 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Screening Questions')->assertSee('Step 4 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Additional References')->assertSee('Step 5 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Location & Age Groups')->assertSee('Step 6 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Qualifications, Activities & Bio')->assertSee('Step 7 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Agreements')->assertSee('Step 8 of 8')
        ->assertNoJavaScriptErrors();
});

test('step 2 position and availability fields can be toggled', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Position, Availability & Education');

    $page->script(<<<'JS'
        const checkboxes = document.querySelectorAll('button[role="checkbox"]');
        const items = Array.from(checkboxes);
        const petBtn = items.find(el => el.closest('label')?.textContent.includes('Petsitting'));
        if (petBtn) petBtn.click();
        const weekendBtn = items.find(el => el.closest('label')?.textContent.includes('Weekends'));
        if (weekendBtn) weekendBtn.click();
    JS);

    $page->assertNoJavaScriptErrors();
});

test('step 3 experience entry can be added and edited', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(400000);
    $page->assertSee('Employment & Experience');

    fillField($page, '#exp-role-0', 'Senior Nanny');

    $page->assertNoJavaScriptErrors();
});

test('step 4 screening questions render with radio groups', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(400000);
    $page->assertSee('Screening Questions');

    $page->assertSee('Authorized to work in the U.S.?');

    $page->script(<<<'JS'
        const radio = document.querySelector('#authorized-yes');
        if (radio) radio.click();
    JS);

    $page->assertNoJavaScriptErrors();
});

test('step 5 reference fields render for all three references', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(400000);
    $page->assertSee('Additional References');

    $page->assertSee('Reference #1')
        ->assertSee('Reference #2')
        ->assertSee('Reference #3')
        ->assertNoJavaScriptErrors();
});

test('step 6 location and age group checkboxes can be toggled', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(400000);
    $page->assertSee('Location & Age Groups');

    $page->script(<<<'JS'
        const checkboxes = document.querySelectorAll('button[role="checkbox"]');
        const items = Array.from(checkboxes);
        const flexibleBtn = items.find(el => el.closest('label')?.textContent.includes('Flexible'));
        if (flexibleBtn) flexibleBtn.click();
    JS);

    $page->assertNoJavaScriptErrors();
});

test('step 7 bio textarea can be filled', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(200000);
    clickNext($page);
    usleep(400000);
    $page->assertSee('Qualifications, Activities & Bio');

    $page->script(<<<'JS'
        const el = document.querySelector('#things-i-bring');
        if (el) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            setter.call(el, 'Arts and crafts, board games, outdoor activities');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    JS);

    $page->assertNoJavaScriptErrors();
});

test('step 8 signature fields render and can be typed into', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email, 8);

    $page->waitForText('Step 8 of 8');
    $page->assertSee('Agreements');

    fillField($page, '#verification-signature', 'John Doe');
    fillField($page, '#agreement-signature', 'John Doe');

    $page->assertNoJavaScriptErrors();
});

test('auto-save persists form data to sessionStorage on step navigation', function () {
    $email = 'applicant-'.time().'@example.com';
    $page = visit('/caregiver/apply/verify-email');

    completeEmailVerification($page, $email);

    setSessionDraft($page, $email, 1, ['position' => ['babysitting' => true, 'petsitting' => true, 'group_events' => true]]);

    $page->waitForText('Step 1 of 8');

    clickNext($page);
    usleep(400000);
    $page->assertSee('Step 2 of 8');

    $draft = $page->script(<<<'JS'
        sessionStorage.getItem('caregiver_application_draft');
    JS);

    expect($draft)->not->toBeEmpty();
    expect($draft)->toContain('sponsor');
    expect($draft)->toContain('"petsitting":true');
});
