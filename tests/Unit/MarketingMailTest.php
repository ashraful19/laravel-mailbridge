<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class MarketingMailTest extends TestCase
{
    public function test_it_subscribes_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::marketing()
            ->list('signup')
            ->subscribe(Subscriber::make('a@example.com')->name('A'));

        Mailbridge::assertSubscribed('signup', 'a@example.com');
    }

    public function test_it_unsubscribes_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::marketing()
            ->list('signup')
            ->subscribe(Subscriber::make('a@example.com'));

        Mailbridge::marketing()
            ->list('signup')
            ->unsubscribe('a@example.com');

        $provider = app(MailbridgeManager::class)->provider('array');

        $this->assertArrayNotHasKey('a@example.com', $provider->subscribers['signup']);
    }

    public function test_it_can_lookup_and_delete_subscriber(): void
    {
        Mailbridge::fake();

        Mailbridge::marketing()
            ->list('signup')
            ->subscribe(Subscriber::make('a@example.com')->name('A'));

        $record = Mailbridge::marketing()->getSubscriber('a@example.com');

        $this->assertSame('a@example.com', $record->email);

        Mailbridge::marketing()->deleteSubscriber('a@example.com');

        $this->assertNull(Mailbridge::marketing()->getSubscriber('a@example.com'));
    }

    public function test_it_supports_campaign_lifecycle_through_array_provider(): void
    {
        Mailbridge::fake();

        $created = Mailbridge::marketing()->createCampaign(
            Campaign::make('Launch')
                ->subject('Hello')
                ->html('<p>Hello</p>')
                ->from('sender@example.com', 'Sender')
                ->list('signup')
        );

        $id = $created->metadata['campaign_id'];

        $this->assertSame('campaign_create', $created->operation);
        $this->assertSame('campaign_schedule', Mailbridge::marketing()->scheduleCampaign($id, '2026-06-01 10:00:00')->operation);
        $this->assertSame('campaign_send', Mailbridge::marketing()->sendCampaign($id)->operation);
        $this->assertSame('campaign_get', Mailbridge::marketing()->getCampaign($id)->operation);
        $this->assertSame('campaign_delete', Mailbridge::marketing()->deleteCampaign($id)->operation);
    }
}
