<?php

use Gesflow\Sender\Enums\SmsEncoding;
use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\Support\SmsCounter;
use Illuminate\Support\Facades\Http;

it('counts the SMS billed for a text', function (string $text, SmsEncoding $encoding, int $length, int $segments, int $remaining) {
    $count = SmsCounter::count($text);

    expect($count->encoding)->toBe($encoding)
        ->and($count->length)->toBe($length)
        ->and($count->segments)->toBe($segments)
        ->and($count->remaining)->toBe($remaining);
})->with([
    'empty' => ['', SmsEncoding::Gsm7, 0, 0, 160],
    'GSM-7' => ['Votre colis est en route.', SmsEncoding::Gsm7, 25, 1, 135],
    'ê forces UCS-2' => ['Café prêt à 10 h', SmsEncoding::Ucs2, 16, 1, 54],
    'GSM-7 accents only' => ['Réunion à 10 h', SmsEncoding::Gsm7, 14, 1, 146],
    'extension characters' => ['Prix : 10 €', SmsEncoding::Gsm7, 12, 1, 148],
    'emoji' => ['Promo 😀', SmsEncoding::Ucs2, 8, 1, 62],
    'exactly 160' => [str_repeat('a', 160), SmsEncoding::Gsm7, 160, 1, 0],
    'long GSM-7' => [str_repeat('a', 161), SmsEncoding::Gsm7, 161, 2, 145],
    'long UCS-2' => [str_repeat('ê', 71), SmsEncoding::Ucs2, 71, 2, 63],
]);

it('explains the encoding and spots variables', function () {
    $count = SmsCounter::count('Bonjour {{ prenom }}, ça va ? 🎉');

    expect($count->isUnicode())->toBeTrue()
        ->and($count->unicodeCharacters)->toBe(['ç', '🎉'])
        ->and($count->hasVariables)->toBeTrue()
        ->and($count->perSegment)->toBe(70);
});

it('estimates a cost', function () {
    expect(SmsCounter::count(str_repeat('a', 200))->cost(25, 3))->toBe(150.0);
});

it('estimates a cost at the account price', function () {
    Http::fake([apiUrl('account') => Http::response(['data' => ['id' => 3, 'name' => 'Ma Boutique', 'prices' => ['sms' => '25.0000']]])]);

    expect(GesflowSender::smsCost(str_repeat('a', 200), recipients: 10))->toBe(500.0);
});
