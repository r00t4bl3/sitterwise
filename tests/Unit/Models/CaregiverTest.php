<?php

use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $this->assertInstanceOf(Caregiver::class, $caregiver);
    }

    public function test_has_correct_fillable_fields()
    {
        $user = User::factory()->create();
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '555-1234',
            'address_line1' => '123 Main St',
            'address_line2' => null,
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'date_of_birth' => '1990-01-15',
            'rating' => 4.50,
            'biography' => 'Experienced caregiver',
            'notes' => 'Some notes',
        ]);

        $this->assertEquals('Jane', $caregiver->first_name);
        $this->assertEquals('Smith', $caregiver->last_name);
        $this->assertEquals('555-1234', $caregiver->phone);
        $this->assertEquals('123 Main St', $caregiver->address_line1);
        $this->assertEquals('San Diego', $caregiver->address_city);
        $this->assertEquals('CA', $caregiver->address_state);
        $this->assertEquals('92101', $caregiver->address_zip);
        $this->assertEquals(4.50, $caregiver->rating);
        $this->assertEquals('Experienced caregiver', $caregiver->biography);
        $this->assertEquals('Some notes', $caregiver->notes);
    }

    public function test_casts_date_of_birth_as_date()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make([
            'status_id' => $status->id,
            'date_of_birth' => '1990-01-15',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $caregiver->date_of_birth);
        $this->assertEquals('1990-01-15', $caregiver->date_of_birth->toDateString());
    }

    public function test_casts_rating_as_decimal()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make([
            'status_id' => $status->id,
            'rating' => 4.75,
        ]);

        $this->assertEquals(4.75, $caregiver->rating);
    }

    public function test_defines_user_relationship()
    {
        $user = User::factory()->create();
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);

        $relation = $caregiver->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
    }

    public function test_defines_status_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->status();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(CaregiverStatus::class, $relation->getRelated());
    }

    public function test_defines_availability_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->availabilities();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Availability::class, $relation->getRelated());
    }

    public function test_defines_bookings_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->bookings();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Booking::class, $relation->getRelated());
    }

    public function test_defines_attribute_definitions_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->attributes();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_returns_full_name()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make([
            'status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $this->assertEquals('Jane Smith', $caregiver->full_name);
    }

    public function test_syncs_user_name_on_save()
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        $status = CaregiverStatus::factory()->create();

        // Create caregiver manually to avoid factory's configure() method
        $caregiver = new Caregiver([
            'user_id' => $user->id,
            'status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'slug' => 'jane-smith-'.rand(10000, 99999),
        ]);
        $caregiver->save();

        $user->refresh();
        $this->assertEquals('Jane Smith', $user->name);
    }

    public function test_preferred_locations_scope()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->preferredLocations();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_willing_locations_scope()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->willingLocations();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_defines_blocked_clients_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);

        $relation = $caregiver->blockedClients();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_generates_slug_with_last_initial()
    {
        $status = CaregiverStatus::factory()->create();

        $caregiver = Caregiver::factory()->make([
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Statham',
            'slug' => '',
        ]);
        $caregiver->save();

        $this->assertEquals('jason-s', $caregiver->refresh()->slug);
    }

    public function test_generates_unique_slug_for_duplicate_base()
    {
        $status = CaregiverStatus::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $caregiver1 = new Caregiver([
            'user_id' => $user1->id,
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Momoa',
            'slug' => '',
        ]);
        $caregiver1->save();

        $caregiver2 = new Caregiver([
            'user_id' => $user2->id,
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Michael',
            'slug' => '',
        ]);
        $caregiver2->save();

        $this->assertEquals('jason-m', $caregiver1->refresh()->slug);
        $this->assertEquals('jason-m-2', $caregiver2->refresh()->slug);
    }

    public function test_generates_sequential_slugs_for_multiple_duplicates()
    {
        $status = CaregiverStatus::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $caregiver1 = new Caregiver([
            'user_id' => $user1->id,
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Momoa',
            'slug' => '',
        ]);
        $caregiver1->save();

        $caregiver2 = new Caregiver([
            'user_id' => $user2->id,
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Michael',
            'slug' => '',
        ]);
        $caregiver2->save();

        $caregiver3 = new Caregiver([
            'user_id' => $user3->id,
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => 'Miller',
            'slug' => '',
        ]);
        $caregiver3->save();

        $this->assertEquals('jason-m', $caregiver1->refresh()->slug);
        $this->assertEquals('jason-m-2', $caregiver2->refresh()->slug);
        $this->assertEquals('jason-m-3', $caregiver3->refresh()->slug);
    }

    public function test_generates_slug_for_special_last_name()
    {
        $status = CaregiverStatus::factory()->create();

        $caregiver = Caregiver::factory()->make([
            'status_id' => $status->id,
            'first_name' => 'Jason',
            'last_name' => "O'Brien",
            'slug' => '',
        ]);
        $caregiver->save();

        $this->assertEquals('jason-o', $caregiver->refresh()->slug);
    }
}
