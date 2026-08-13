<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_renders_with_login_cta(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('PUPTracker');
        $response->assertSee('Access the Login Dashboard');
        // The CTA must link to the access portal.
        $response->assertSee(route('login'));
    }

    public function test_access_portal_renders_at_login_route(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Select a Portal');
        $response->assertSee('Student Portal');
        $response->assertSee('Security Portal');
        $response->assertSee('Administrator');
    }

    public function test_login_route_has_portal_links(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee(route('student.login'));
        $response->assertSee(route('security.login'));
        $response->assertSee(route('admin.login'));
    }
}
