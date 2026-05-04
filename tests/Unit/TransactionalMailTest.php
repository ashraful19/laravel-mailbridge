<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class TransactionalMailTest extends TestCase
{
    public function test_it_sends_raw_email_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->to('a@example.com')
            ->subject('Hello')
            ->text('Hello')
            ->send();

        Mailbridge::assertTransactionalSent();
    }

    public function test_template_and_template_id_are_mutually_exclusive(): void
    {
        $this->expectException(MailbridgeValidationException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->templateId('direct-id')
            ->send();
    }
}
