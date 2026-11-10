<?php

use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends with another account API key and sender ID', function () {
    reconfigure(['sender_id' => 'MaMarque']);
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData()], 201)]);

    GesflowSender::withApiKey('34|cle-boutique', 'Boutique')->sms('242066123456', 'Bonjour')->send();
    GesflowSender::withApiKey('35|autre-cle')->sms('242066123456', 'Bonjour')->send();
    GesflowSender::sms('242066123456', 'Bonjour')->send();

    $requests = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0]);

    expect($requests[0]->header('Authorization'))->toBe(['Bearer 34|cle-boutique'])
        ->and($requests[0]['sender_id'])->toBe('Boutique')
        ->and($requests[1]->header('Authorization'))->toBe(['Bearer 35|autre-cle'])
        ->and($requests[1]['sender_id'])->toBe('MaMarque')
        ->and($requests[2]->header('Authorization'))->toBe(['Bearer '.TestCase::API_KEY]);
});

it('records the sends of every account in the fake', function () {
    $fake = GesflowSender::fake();

    GesflowSender::withApiKey('34|cle-boutique')->sms('242066123456', 'Bonjour')->send();

    $fake->assertSentTo('242066123456');
});
