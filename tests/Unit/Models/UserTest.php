<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $user = User::factory()->make();

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_has_correct_fillable_fields()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret',
            'role' => 'admin',
            'profile_photo_path' => '/path/to/photo.jpg',
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals('/path/to/photo.jpg', $user->profile_photo_path);
    }

    public function test_hides_sensitive_fields_from_array_representation()
    {
        $user = User::factory()->create([
            'password' => 'secret123',
            'two_factor_secret' => 'secret2fa',
            'two_factor_recovery_codes' => '["code1","code2"]',
            'remember_token' => 'token123',
        ]);

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('two_factor_secret', $userArray);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_casts_attributes_correctly()
    {
        $now = now();
        $user = User::factory()->create([
            'email_verified_at' => $now,
            'two_factor_confirmed_at' => $now,
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $user->email_verified_at);
        $this->assertEquals($now->timestamp, $user->email_verified_at->timestamp);
        $this->assertStringStartsWith('$2y$', $user->password); // bcrypt hash (from factory)
        $this->assertInstanceOf(CarbonImmutable::class, $user->two_factor_confirmed_at);
        $this->assertEquals($now->timestamp, $user->two_factor_confirmed_at->timestamp);
    }

    public function test_defines_caregiver_relationship()
    {
        $user = User::factory()->create();
        $caregiver = $user->caregiver()->getRelated();

        $this->assertInstanceOf(Caregiver::class, $caregiver);
    }

    public function test_defines_client_relationship()
    {
        $user = User::factory()->create();
        $client = $user->client()->getRelated();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_identifies_admin_role_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $client = User::factory()->create(['role' => 'client']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($caregiver->isAdmin());
        $this->assertFalse($client->isAdmin());
    }

    public function test_identifies_caregiver_role_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $client = User::factory()->create(['role' => 'client']);

        $this->assertFalse($admin->isCaregiver());
        $this->assertTrue($caregiver->isCaregiver());
        $this->assertFalse($client->isCaregiver());
    }

    public function test_identifies_client_role_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $client = User::factory()->create(['role' => 'client']);

        $this->assertFalse($admin->isClient());
        $this->assertFalse($caregiver->isClient());
        $this->assertTrue($client->isClient());
    }

    public function test_returns_correct_role_label()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $client = User::factory()->create(['role' => 'client']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->assertEquals('Admin', $admin->getRoleLabelAttribute());
        $this->assertEquals('Caregiver', $caregiver->getRoleLabelAttribute());
        $this->assertEquals('Client', $client->getRoleLabelAttribute());
        $this->assertEquals('Unknown', $superAdmin->getRoleLabelAttribute()); // super_admin not in match statement
    }
}
