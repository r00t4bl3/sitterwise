<?php

use App\Http\Middleware\EnsureUserIsCaregiver;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUserIsCaregiverTest extends TestCase
{
    public function test_allows_caregiver_user()
    {
        $user = User::factory()->make(['role' => 'caregiver']);
        $request = Request::create('/')->setUserResolver(fn () => $user);

        $middleware = new EnsureUserIsCaregiver;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_denies_non_caregiver_user()
    {
        $user = User::factory()->make(['role' => 'admin']);
        $request = Request::create('/')->setUserResolver(fn () => $user);

        $middleware = new EnsureUserIsCaregiver;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getData(true)['message']);
    }

    public function test_denies_unauthenticated_user()
    {
        $request = Request::create('/')->setUserResolver(fn () => null);

        $middleware = new EnsureUserIsCaregiver;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getData(true)['message']);
    }
}
