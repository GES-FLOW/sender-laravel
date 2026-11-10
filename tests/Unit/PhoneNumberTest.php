<?php

use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\GesflowSenderServiceProvider;
use Gesflow\Sender\Support\PhoneNumber;

it('keeps only the digits by default', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'international' => ['+242 06 612 34 56', '242066123456'],
    'digits' => ['242066123456', '242066123456'],
    '00 prefix' => ['00242 06 612 34 56', '242066123456'],
    'local, no default country code' => ['06 612 34 56', '066123456'],
]);

it('prefixes the default country code to local numbers', function (string $input, string $expected) {
    PhoneNumber::defaultCountryCode('+242');

    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'local' => ['06 612 34 56', '242066123456'],
    'already international' => ['242066123456', '242066123456'],
    'foreign with +' => ['+33 6 12 34 56 78', '33612345678'],
    'foreign with 00' => ['0033612345678', '33612345678'],
]);

it('accepts a custom normalization', function () {
    PhoneNumber::normalizeUsing(fn (string $phone): string => '+243 '.ltrim($phone, '0'));

    expect(PhoneNumber::normalize('081 234 5678'))->toBe('243812345678');
});

it('reads the default country code from the configuration', function () {
    config()->set('gesflow-sender.default_country_code', '242');
    (new GesflowSenderServiceProvider(app()))->boot();

    $fake = GesflowSender::fake();
    GesflowSender::sms('06 612 34 56', 'Bonjour')->send();

    $fake->assertSentTo('242066123456');
});
