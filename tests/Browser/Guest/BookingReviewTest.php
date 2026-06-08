<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('guest can view review page via signed url', function () {
    [$booking] = createCompletedBooking();

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $booking->ulid,
    ]);

    visit($signedUrl)
        ->assertSee($booking->caregiver->first_name)
        ->assertSee($booking->caregiver->last_name)
        ->assertSee('Submit Review')
        ->assertNoJavaScriptErrors();
});

test('guest can submit review with rating and comment', function () {
    [$booking] = createCompletedBooking();

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $booking->ulid,
    ]);

    $page = visit($signedUrl);

    $page->script(<<<'JS'
        const stars = document.querySelectorAll('.flex.items-center.gap-1 > div');
        if (stars.length >= 4) {
            const star = stars[3];
            const rect = star.getBoundingClientRect();
            star.dispatchEvent(new MouseEvent('click', {
                clientX: rect.left + rect.width * 0.75,
                clientY: rect.top + rect.height / 2,
                bubbles: true
            }));
        }
    JS);

    $page->script(<<<'JS'
        const textarea = document.querySelector('textarea');
        if (textarea) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            setter.call(textarea, 'Great service!');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }
    JS);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->assertSee('Thank You for Your Review!');
    $page->assertSee($booking->caregiver->first_name);
    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $booking->id,
        'rating' => 4,
    ]);
});

test('tip input shows stripe card input when filled', function () {
    [$booking] = createCompletedBooking();

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $booking->ulid,
    ]);

    $page = visit($signedUrl);

    fillField($page, 'input[type="number"]', '10');

    $page->assertSee('Payment Details');
    $page->assertNoJavaScriptErrors();
});

test('tip input hides stripe card input when cleared', function () {
    [$booking] = createCompletedBooking();

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $booking->ulid,
    ]);

    $page = visit($signedUrl);

    fillField($page, 'input[type="number"]', '10');
    $page->assertSee('Payment Details');

    fillField($page, 'input[type="number"]', '');
    $page->assertDontSee('Payment Details');
    $page->assertNoJavaScriptErrors();
});

test('submit with tip but no card shows error', function () {
    [$booking] = createCompletedBooking();

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $booking->ulid,
    ]);

    $page = visit($signedUrl);

    $page->script(<<<'JS'
        const stars = document.querySelectorAll('.flex.items-center.gap-1 > div');
        if (stars.length >= 4) {
            const star = stars[3];
            const rect = star.getBoundingClientRect();
            star.dispatchEvent(new MouseEvent('click', {
                clientX: rect.left + rect.width * 0.75,
                clientY: rect.top + rect.height / 2,
                bubbles: true
            }));
        }
    JS);

    fillField($page, 'input[type="number"]', '10');

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->assertSee('Please enter your card details');
    $page->assertNoJavaScriptErrors();
});
