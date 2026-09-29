<?php

/**
 * The two personalised previews (wizard Step 5 and the admin campaign page)
 * load sample users with a narrow column list. They must select everything
 * the placeholder tokens read — previously they omitted last_first_aid_at
 * (every preview said "no first-aid training on record") and title.
 */

use App\Models\MessagingCampaign;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const PREVIEW_EMAIL_BODY = '<p>{{user.full_name}}</p><p>{{user.time_since_last_first_aid}}</p><p>{{user.current_membership}} until {{user.membership_expiry}}</p>';

beforeEach(function () {
    $permissions = ['manage-admin-panel', 'campaign_request_create', 'campaign_request_approve'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_super_admin' => true]); // excluded from the audience
    $this->admin->assignRole('national_db_administrator');

    // Expired member with first-aid training on record.
    $this->member = User::factory()->create([
        'title' => 'Dr.', 'first_name' => 'Ada', 'last_name' => 'Obi',
        'last_first_aid_at' => now()->subYears(4)->toDateString(),
    ]);
    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $this->member->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['name' => 'Detachment'])->id,
        'payment_date' => '2016-11-24',
        'expiry_date' => '2017-11-24',
    ]);

    $this->campaign = MessagingCampaign::create([
        'channel' => 'email',
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => PREVIEW_EMAIL_BODY,
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => PREVIEW_EMAIL_BODY]],
        'status' => 'draft',
        'created_by' => $this->admin->id,
        'scope_level' => 'national', // set from the creator's access level on wizard create

    ]);
});

test('wizard Step 5 preview renders title, first-aid age and expired membership', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('campaigns.wizard.step5', $this->campaign))
        ->assertOk();

    $preview = $response->viewData('samplePreviews')[$this->member->id]['email_body'];

    expect($preview)->toBe('<p>Dr. Ada Obi</p><p>4 years</p><p>Detachment until 24 Nov 2017</p>');
});

test('admin campaign page preview renders title, first-aid age and expired membership', function () {
    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.show', $this->campaign))
        ->assertOk()
        ->assertSee('Dr. Ada Obi', false)
        ->assertSee('4 years', false)
        ->assertSee('Detachment until 24 Nov 2017', false);
    // No assertDontSee on the first-aid fallback: the payment factory's submitter
    // user is also in the audience and genuinely has no first-aid record.
});
