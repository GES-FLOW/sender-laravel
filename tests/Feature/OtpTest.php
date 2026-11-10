<?php

use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\OtpStatus;
use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends a verification code', function () {
    reconfigure(['sender_id' => 'MaMarque']);
    Http::fake([apiUrl('verify/send') => Http::response(['data' => [
        'id' => '01JCX3V8T6Q9W2E4R5T6Y7U8I9',
        'phone' => '242066123456',
        'channel' => 'sms',
        'status' => 'pending',
        'message_id' => 7,
        'expires_at' => '2026-11-10T09:35:00.000000Z',
        'sandbox' => true,
        'code' => '482913',
    ]], 201)]);

    $verification = GesflowSender::otp()->send('+242 06 612 34 56', codeLength: 6, expiresIn: 300);

    expect($verification)
        ->id->toBe('01JCX3V8T6Q9W2E4R5T6Y7U8I9')
        ->channel->toBe(Channel::Sms)
        ->messageId->toBe(7)
        ->sandbox->toBeTrue()
        ->code->toBe('482913')
        ->and($verification->expiresAt?->toIso8601ZuluString())->toBe('2026-11-10T09:35:00Z');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key') && $request->data() === [
        'phone' => '242066123456',
        'channel' => 'sms',
        'code_length' => 6,
        'expires_in' => 300,
        'sender_id' => 'MaMarque',
    ]);
});

it('sends a WhatsApp code with a custom template and no sender ID', function () {
    reconfigure(['sender_id' => 'MaMarque']);
    Http::fake([apiUrl('verify/send') => Http::response(['data' => [
        'id' => '01JCX3V8T6Q9W2E4R5T6Y7U8I9',
        'phone' => '242066123456',
        'channel' => 'whatsapp',
        'status' => 'pending',
        'message_id' => 8,
        'expires_at' => '2026-11-10T09:35:00.000000Z',
        'sandbox' => false,
    ]], 201)]);

    $verification = GesflowSender::otp()->send('242066123456', 'whatsapp', template: 'Code {{ code }}, valable {{ minutes }} min.');

    expect($verification->channel)->toBe(Channel::Whatsapp)
        ->and($verification->code)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'phone' => '242066123456',
        'channel' => 'whatsapp',
        'template' => 'Code {{ code }}, valable {{ minutes }} min.',
    ]);
});

it('checks a code', function (array $data, bool $valid, OtpStatus $status) {
    Http::fake([apiUrl('verify/check') => Http::response(['data' => $data])]);

    $check = GesflowSender::otp()->check('242066123456', ' 482913 ');

    expect($check->valid)->toBe($valid)
        ->and($check->status)->toBe($status)
        ->and($check->attemptsRemaining)->toBe($data['attempts_remaining']);

    Http::assertSent(fn (Request $request): bool => $request->data() === ['phone' => '242066123456', 'code' => '482913']);
})->with([
    'approved' => [['id' => '01JCX3', 'phone' => '242066123456', 'valid' => true, 'status' => 'approved', 'attempts_remaining' => 4], true, OtpStatus::Approved],
    'invalid' => [['id' => '01JCX3', 'phone' => '242066123456', 'valid' => false, 'status' => 'invalid', 'attempts_remaining' => 3], false, OtpStatus::Invalid],
    'expired' => [['id' => '01JCX3', 'phone' => '242066123456', 'valid' => false, 'status' => 'expired', 'attempts_remaining' => 5], false, OtpStatus::Expired],
    'not found' => [['id' => null, 'phone' => '242066123456', 'valid' => false, 'status' => 'not_found', 'attempts_remaining' => 0], false, OtpStatus::NotFound],
]);
