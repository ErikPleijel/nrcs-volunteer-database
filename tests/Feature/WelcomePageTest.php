<?php

/**
 * Feature tests for the welcome page's "Ready to Make a Difference?" cards:
 * the fee-category name lists WelcomeController passes to the view.
 */

use App\Models\MembershipFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function welcomeFee(string $name, int $amount, bool $volunteer, array $overrides = []): MembershipFee
{
    return MembershipFee::factory()->create(array_merge([
        'name' => $name,
        'amount' => $amount,
        'is_volunteer_fee' => $volunteer,
        'validity_years' => 1,
        'for_organizations' => false,
        'is_active' => true,
    ], $overrides));
}

test('welcome page splits fee names into volunteer-member and supporting-member lists, cheapest first, deduped', function () {
    // Volunteer-and-member fees, created out of amount order.
    welcomeFee('Service Group', 500, true);
    welcomeFee('Junior Unit', 50, true);
    welcomeFee('Associate', 2000, true);
    welcomeFee('Detachment', 200, true);
    // 3-year row sharing a name — must not produce a duplicate or be listed on its own.
    welcomeFee('Detachment', 600, true, ['validity_years' => 3]);

    // Supporting-member fees.
    welcomeFee('Gold', 10000, false);
    welcomeFee('Bronze', 1500, false);
    welcomeFee('Silver', 5000, false);
    welcomeFee('Silver', 15000, false, ['validity_years' => 3]);

    // Excluded: inactive, and organisation-tier rows.
    welcomeFee('Retired Fee', 100, false, ['is_active' => false]);
    welcomeFee('Corporate Gold', 10000, false, ['for_organizations' => true]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewHas('volunteerMemberFeeNames', ['Junior Unit', 'Detachment', 'Service Group', 'Associate']);
    $response->assertViewHas('supportingMemberFeeNames', ['Bronze', 'Silver', 'Gold']);
    $response->assertSee('Categories: Junior Unit, Detachment, Service Group, Associate.');
    $response->assertSee('Categories: Bronze, Silver, Gold.');
});

test('welcome page links each of the four cards to its journey page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee(route('volunteer.journey'));
    $response->assertSee(route('volunteer-member.journey'));
    $response->assertSee(route('membership.journey'));
    $response->assertSee(route('corporate.journey'));
    // No fees configured: the "Categories:" sentence is omitted rather than left dangling.
    $response->assertDontSee('Categories:');
});

test('volunteer-member journey page lists only volunteer fees', function () {
    welcomeFee('Detachment', 200, true);
    welcomeFee('Bronze', 1500, false);

    $response = $this->get(route('volunteer-member.journey'));

    $response->assertOk();
    $response->assertSee('Detachment');
    $response->assertDontSee('Bronze');
});

test('membership journey page no longer lists volunteer fees such as Associate', function () {
    welcomeFee('Associate', 2000, true);
    welcomeFee('Bronze', 1500, false);

    $response = $this->get(route('membership.journey'));

    $response->assertOk();
    $response->assertSee('Bronze');
    $response->assertDontSee('Associate');
});

test('welcome page shows the section subtitle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Join thousands of compassionate individuals who are making a real difference in communities across Nigeria.');
});

test('journey pages render their intro text with a real line break', function (string $route, string $intro) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee($intro."<br>\n", false)
        ->assertSee("Here's how to get started:", false);
})->with([
    'volunteer' => ['volunteer.journey', 'No membership fee, no payment: just your time and skills.'],
    'volunteer & member' => ['volunteer-member.journey', 'register as a paying member too.'],
    'member' => ['membership.journey', 'register and pay online in minutes.'],
]);
