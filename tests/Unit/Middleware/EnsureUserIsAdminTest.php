<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    public function test_allows_admin_user()
    {
        $user = User::factory()->make(['role' => 'admin']);
        $request = Request::create('/')->setUserResolver(fn () => $user);

        $middleware = new EnsureUserIsAdmin;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_denies_non_admin_user()
    {
        $user = User::factory()->make(['role' => 'caregiver']);
        $request = Request::create('/')->setUserResolver(fn () => $user);

        $middleware = new EnsureUserIsAdmin;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getData(true)['message']);
    }

    public function test_denies_unauthenticated_user()
    {
        $request = Request::create('/')->setUserResolver(fn () => null);

        $middleware = new EnsureUserIsAdmin;
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getData(true)['message']);
    }
}
