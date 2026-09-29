<?php

/**
 * CampaignSendRunner must never send a recipient twice. Previously the whole
 * batch ran inside one DB transaction: an error after some sends rolled those
 * recipients back to 'pending' and the next run sent them again. Now each
 * recipient is claimed (pending -> queued) before sending and its result is
 * written immediately.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryAttempt;
use App\Campaigns\Delivery\DeliveryChannel;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Email channel that records every delivery and can run a hook first. */
function recordingEmailChannel(?Closure $before = null): DeliveryChannel
{
    return new class($before) implements DeliveryChannel
    {
        /** @var int[] recipient ids, one entry per deliver() call */
        public array $delivered = [];

        public function __construct(private ?Closure $before) {}

        public function name(): string
        {
            return 'email';
        }

        public function supports(MessagingRecipient $recipient): bool
        {
            return ! empty($recipient->email);
        }

        public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
        {
            if ($this->before) {
                ($this->before)($recipient);
            }

            $this->delivered[] = $recipient->id;

            return DeliveryAttempt::success('email', 'fake-'.$recipient->id);
        }
    };
}

function runnerWith(DeliveryChannel $channel): CampaignSendRunner
{
    return new CampaignSendRunner(new CampaignDeliveryService([$channel]));
}

beforeEach(function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->campaign = MessagingCampaign::create([
        'channel' => 'email',
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => '<p>Hello</p>',
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>']],
        'status' => 'sending',
        'created_by' => $admin->id,
        'scope_level' => 'national',
    ]);

    $this->recipients = User::factory()->count(5)->create()->map(fn (User $u) => MessagingRecipient::create([
        'messaging_campaign_id' => $this->campaign->id,
        'recipient_type' => User::class,
        'recipient_id' => $u->id,
        'email' => $u->email,
        'status' => 'pending',
    ]))->values();
});

test('an error after some sends does not cause those recipients to be re-sent', function () {
    [$r1, $r2, $r3, $r4, $r5] = $this->recipients->all();

    // Writing recipient #3's result fails (e.g. the DB connection drops) AFTER it was sent.
    $explode = true;
    MessagingRecipient::updating(function (MessagingRecipient $m) use (&$explode, $r3) {
        if ($explode && $m->id === $r3->id) {
            throw new RuntimeException('simulated DB failure');
        }
    });

    $channel = recordingEmailChannel();
    $runner = runnerWith($channel);

    expect(fn () => $runner->runOneBatch($this->campaign->fresh(), batch: 50, force: true))
        ->toThrow(RuntimeException::class, 'simulated DB failure');

    expect($r1->fresh()->status)->toBe('sent')
        ->and($r2->fresh()->status)->toBe('sent')
        ->and($r3->fresh()->status)->toBe('queued') // in flight: left for review, never auto-retried
        ->and($r4->fresh()->status)->toBe('pending')
        ->and($r5->fresh()->status)->toBe('pending');

    // Next run (DB healthy again) only sends what was never attempted.
    $explode = false;
    $runner->runOneBatch($this->campaign->fresh(), batch: 50, force: true);

    expect(array_count_values($channel->delivered))->toBe([
        $r1->id => 1, $r2->id => 1, $r3->id => 1, $r4->id => 1, $r5->id => 1,
    ]);
    expect($r4->fresh()->status)->toBe('sent')
        ->and($r5->fresh()->status)->toBe('sent')
        ->and($r3->fresh()->status)->toBe('queued');
});

test('a provider exception fails only that recipient and the batch continues', function () {
    [$r1, $r2, $r3, $r4, $r5] = $this->recipients->all();

    $channel = recordingEmailChannel(function (MessagingRecipient $r) use ($r2) {
        if ($r->id === $r2->id) {
            throw new RuntimeException('provider timeout');
        }
    });

    $result = runnerWith($channel)->runOneBatch($this->campaign->fresh(), batch: 50, force: true);

    expect($result)->toBe(['sent' => 4, 'failed' => 1, 'processed' => 5])
        ->and($r2->fresh()->status)->toBe('failed')
        ->and($r2->fresh()->last_error)->toBe('provider timeout')
        ->and($this->campaign->fresh()->daily_sent_count)->toBe(4);

    foreach ([$r1, $r3, $r4, $r5] as $r) {
        expect($r->fresh()->status)->toBe('sent');
    }
});

test('queued recipients are never picked up again', function () {
    $stuck = $this->recipients[0];
    $stuck->update(['status' => 'queued']);

    $channel = recordingEmailChannel();
    runnerWith($channel)->runOneBatch($this->campaign->fresh(), batch: 50, force: true);

    expect($channel->delivered)->not->toContain($stuck->id)
        ->and($stuck->fresh()->status)->toBe('queued');
});

test('a recipient claimed by another runner mid-batch is skipped', function () {
    [$r1, $r2] = $this->recipients->all();

    // While this runner sends #1, a concurrent runner (e.g. the admin "Run once" button)
    // claims #2, which this runner already loaded as pending.
    $channel = recordingEmailChannel(function (MessagingRecipient $r) use ($r1, $r2) {
        if ($r->id === $r1->id) {
            DB::table('messaging_recipients')->where('id', $r2->id)->update(['status' => 'queued']);
        }
    });

    runnerWith($channel)->runOneBatch($this->campaign->fresh(), batch: 50, force: true);

    expect($channel->delivered)->not->toContain($r2->id)
        ->and($channel->delivered)->toContain($r1->id)
        ->and($r2->fresh()->status)->toBe('queued');
});
