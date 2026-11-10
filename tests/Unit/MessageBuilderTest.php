<?php

use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MediaType;
use Gesflow\Sender\Messages\GesflowMessage;
use Gesflow\Sender\Messages\PendingMessage;

it('builds the request body of an SMS', function () {
    $message = GesflowMessage::sms('Bonjour')->to('+242 06 612 34 56');

    expect($message->getChannel())->toBe(Channel::Sms)
        ->and($message->toArray())->toBe(['channel' => 'sms', 'phone' => '242066123456', 'text' => 'Bonjour'])
        ->and($message->toArray('MaMarque'))->toHaveKey('sender_id', 'MaMarque')
        ->and($message->from('Autre')->toArray('MaMarque'))->toHaveKey('sender_id', 'Autre');
});

it('builds the request body of every WhatsApp media', function (string $method, MediaType $type) {
    $message = GesflowMessage::whatsapp()->to('242066123456')->{$method}('https://cdn.example/fichier');

    expect($message->toArray())
        ->toHaveKey('media_type', $type->value)
        ->toHaveKey('media_url', 'https://cdn.example/fichier');
})->with([
    'image' => ['image', MediaType::Image],
    'document' => ['document', MediaType::File],
    'video' => ['video', MediaType::Video],
    'voice note' => ['voice', MediaType::Voice],
]);

it('keeps the options of each channel to that channel', function (Closure $build, string $error) {
    expect($build)->toThrow(InvalidArgumentException::class, $error);
})->with([
    'flash on WhatsApp' => [fn () => GesflowMessage::whatsapp('Bonjour')->flash(), 'Option réservée aux SMS : le mode flash.'],
    'image by SMS' => [fn () => GesflowMessage::sms('Bonjour')->image('https://cdn.example/a.png'), 'Option réservée à WhatsApp : les médias.'],
    'location by SMS' => [fn () => GesflowMessage::sms()->location(-4.26, 15.24, 'Agence'), 'Option réservée à WhatsApp : les positions.'],
    'contact by SMS' => [fn () => GesflowMessage::sms()->contact('Support', '242066000000'), 'Option réservée à WhatsApp : les fiches contact.'],
]);

it('limits a WhatsApp message to ten contact cards', function () {
    $message = GesflowMessage::whatsapp()->to('242066123456');

    foreach (range(1, 10) as $index) {
        $message->contact("Contact {$index}", "24206600000{$index}");
    }

    expect($message->toArray()['contacts'])->toHaveCount(10)
        ->and(fn () => $message->contact('Contact 11', '242066000011'))->toThrow(InvalidArgumentException::class);
});

it('requires a recipient', function () {
    expect(fn () => GesflowMessage::sms('Bonjour')->toArray())->toThrow(InvalidArgumentException::class)
        ->and(fn () => GesflowMessage::sms('Bonjour')->to('+')->toArray())->toThrow(InvalidArgumentException::class);
});

it('cannot send a pending message without a client', function () {
    expect(fn () => PendingMessage::sms('Bonjour')->to('242066123456')->send())->toThrow(LogicException::class);
});
