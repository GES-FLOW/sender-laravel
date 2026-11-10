<?php

use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('reads the account, its balance and its transactions', function () {
    Http::fake([
        apiUrl('account') => Http::response(['data' => [
            'id' => 3,
            'name' => 'Ma Boutique',
            'credit_balance' => '12500.0000',
            'currency' => 'XAF',
            'prices' => ['sms' => '25.0000', 'whatsapp' => '10.0000'],
            'remaining' => ['sms' => 500, 'whatsapp' => 1250],
        ]]),
        apiUrl('account/transactions?type=payment&page=1') => Http::response(pageOf([[
            'id' => 7,
            'type' => 'payment',
            'label' => 'Recharge',
            'amount' => '10000.0000',
            'balance_after' => '12500.0000',
            'description' => 'Recharge Mobile Money',
            'reference' => ['type' => 'payment', 'id' => 12],
            'created_at' => '2026-11-09T08:00:00.000000Z',
        ]])),
    ]);

    $account = GesflowSender::account();
    $transactions = GesflowSender::transactions('payment');

    expect($account)
        ->name->toBe('Ma Boutique')
        ->balance->toBe(12500.0)
        ->smsPrice->toBe(25.0)
        ->whatsappPrice->toBe(10.0)
        ->remainingSms->toBe(500)
        ->and(GesflowSender::balance())->toBe(12500.0)
        ->and($transactions->items[0])
        ->type->toBe('payment')
        ->amount->toBe(10000.0)
        ->balanceAfter->toBe(12500.0)
        ->reference->toBe(['type' => 'payment', 'id' => 12]);
});

it('lists the sender IDs and checks WhatsApp numbers', function () {
    Http::fake([
        apiUrl('sender-ids') => Http::response(['data' => [
            ['id' => 1, 'value' => 'MaBoutique', 'is_default' => true, 'created_at' => '2026-10-01T08:00:00.000000Z'],
            ['id' => 2, 'value' => 'MaMarque', 'is_default' => false, 'created_at' => '2026-10-02T08:00:00.000000Z'],
        ]]),
        apiUrl('whatsapp/check-number?phone=242066123456') => Http::response(['data' => ['phone' => '242066123456', 'has_whatsapp' => true]]),
    ]);

    $senderIds = GesflowSender::senderIds();

    expect($senderIds)->toHaveCount(2)
        ->and($senderIds[0]->value)->toBe('MaBoutique')
        ->and($senderIds[0]->isDefault)->toBeTrue()
        ->and(GesflowSender::hasWhatsapp('+242 06 612 34 56'))->toBeTrue();
});

it('manages contacts', function () {
    $contact = [
        'id' => 5,
        'name' => 'Awa Mabiala',
        'phone' => '242066123456',
        'tags' => ['vip'],
        'custom_fields' => ['ville' => 'Pointe-Noire'],
        'groups' => [['id' => 4, 'name' => 'Clients', 'description' => null, 'created_at' => '2026-10-01T08:00:00.000000Z']],
        'created_at' => '2026-11-01T08:00:00.000000Z',
        'updated_at' => '2026-11-01T08:00:00.000000Z',
    ];

    Http::fake([
        apiUrl('contacts?search=awa&tag=vip&page=1') => Http::response(pageOf([$contact])),
        apiUrl('contacts') => Http::response(['data' => $contact], 201),
        apiUrl('contacts/5') => Http::sequence()
            ->push(['data' => $contact])
            ->push(['data' => array_merge($contact, ['name' => 'Awa M.'])])
            ->pushStatus(204),
    ]);

    $page = GesflowSender::contacts()->list(search: 'awa', tag: 'vip');
    $created = GesflowSender::contacts()->create('Awa Mabiala', '+242 06 612 34 56', tags: ['vip'], customFields: ['ville' => 'Pointe-Noire'], groupIds: [4]);
    $found = GesflowSender::contacts()->find(5);
    $updated = GesflowSender::contacts()->update(5, 'Awa M.', '242066123456');
    GesflowSender::contacts()->delete(5);

    expect($page->items[0]->customFields)->toBe(['ville' => 'Pointe-Noire'])
        ->and($created->groups[0]->name)->toBe('Clients')
        ->and($found->tags)->toBe(['vip'])
        ->and($updated->name)->toBe('Awa M.');

    $requests = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0]);

    expect($requests[1]->data())->toBe([
        'name' => 'Awa Mabiala',
        'phone' => '242066123456',
        'tags' => ['vip'],
        'custom_fields' => ['ville' => 'Pointe-Noire'],
        'group_ids' => [4],
    ])->and($requests[3]->method())->toBe('PUT')
        ->and($requests[3]->data())->toBe(['name' => 'Awa M.', 'phone' => '242066123456'])
        ->and($requests[4]->method())->toBe('DELETE');
});

it('manages contact groups and their members', function () {
    $group = ['id' => 4, 'name' => 'Clients', 'description' => 'Tous les clients', 'contacts_count' => 2, 'created_at' => '2026-10-01T08:00:00.000000Z'];

    Http::fake([
        apiUrl('contact-groups') => Http::response(['data' => $group], 201),
        apiUrl('contact-groups/4/contacts') => Http::response(['data' => $group]),
    ]);

    $created = GesflowSender::contactGroups()->create('Clients', 'Tous les clients');
    GesflowSender::contactGroups()->attach(4, [5, 6]);
    GesflowSender::contactGroups()->detach(4, [6]);

    expect($created->contactsCount)->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === apiUrl('contact-groups/4/contacts') && $request['contact_ids'] === [5, 6]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === apiUrl('contact-groups/4/contacts') && $request['contact_ids'] === [6]);
});

it('manages templates', function () {
    $template = ['id' => 2, 'name' => 'Relance', 'content' => 'Bonjour {{ prenom }}, votre facture est disponible.', 'created_at' => '2026-10-01T08:00:00.000000Z', 'updated_at' => '2026-10-01T08:00:00.000000Z'];

    Http::fake([
        apiUrl('templates') => Http::response(['data' => $template], 201),
        apiUrl('templates?search=Relance&page=1') => Http::response(pageOf([$template])),
    ]);

    expect(GesflowSender::templates()->create('Relance', $template['content'])->content)->toBe($template['content'])
        ->and(GesflowSender::templates()->list('Relance')->items[0]->name)->toBe('Relance');
});

it('lists the received messages since a date, in UTC', function () {
    Http::fake([apiUrl('inbound-messages*') => Http::response(pageOf([[
        'id' => 11,
        'channel' => 'sms',
        'phone' => '242066123456',
        'to' => 'MaBoutique',
        'text' => 'STOP',
        'media_url' => null,
        'media_mimetype' => null,
        'received_at' => '2026-11-10T08:15:00.000000Z',
        'read_at' => null,
    ]]))]);

    $page = GesflowSender::inboundMessages()->list('+242 06 612 34 56', Carbon::parse('2026-11-10 09:00', 'Africa/Brazzaville'));

    expect($page->items[0])
        ->text->toBe('STOP')
        ->channel->toBe(Channel::Sms)
        ->and($page->items[0]->receivedAt?->toIso8601ZuluString())->toBe('2026-11-10T08:15:00Z');

    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'phone' => '242066123456',
        'since' => '2026-11-10 08:00:00',
        'page' => 1,
    ]);
});

it('reads the recharge fees and initiates a payment', function () {
    Http::fake([
        apiUrl('payments/fees-config') => Http::response([
            'min_amount' => 500,
            'provider_fee_percentage' => 0.035,
            'provider_fee_fixed' => 25,
            'tax_vat_percentage' => 0.18,
            'tax_ca_percentage' => 0.05,
            'country' => 'cg',
            'currency' => 'XAF',
            'phone_country_code' => '+242',
        ]),
        apiUrl('payments/initiate') => Http::response(['data' => [
            'id' => 12,
            'reference' => 'PAY-12',
            'status' => 'pending',
            'status_label' => 'En attente',
            'base_amount' => '10000.0000',
            'service_fee' => '441.1500',
            'transaction_fee' => '1890.0000',
            'amount' => '12331.1500',
            'currency' => 'XAF',
            'phone_number' => '242066123456',
            'operator' => 'mtn',
            'country' => 'cg',
            'failure_message' => null,
            'created_at' => '2026-11-10T09:30:00+00:00',
            'updated_at' => '2026-11-10T09:30:00+00:00',
        ]], 201),
    ]);

    $fees = GesflowSender::payments()->fees();
    $payment = GesflowSender::payments()->initiate(10000, '242066123456', 'mtn', idempotencyKey: 'recharge-1');

    expect($fees->minAmount)->toBe(500.0)
        ->and(round($fees->taxesFor(10000), 4))->toBe(1890.0)
        ->and(round($fees->providerFeeFor(10000), 4))->toBe(441.15)
        ->and(round($fees->totalFor(10000), 4))->toBe(12331.15)
        ->and($payment->status)->toBe('pending')
        ->and($payment->isSuccessful())->toBeFalse()
        ->and($payment->amount)->toBe(12331.15);

    Http::assertSent(fn (Request $request): bool => $request->url() === apiUrl('payments/initiate')
        && $request->header('Idempotency-Key') === ['recharge-1']);
});

it('cancels a campaign', function () {
    Http::fake([apiUrl('campaigns/9/cancel') => Http::response([
        'data' => [
            'id' => 9,
            'name' => 'Promo de Noël',
            'channel' => 'sms',
            'text' => 'Promo !',
            'from' => 'MaBoutique',
            'recipients_count' => 120,
            'scheduled_at' => '2026-12-20T08:00:00.000000Z',
            'created_at' => '2026-11-10T09:30:00.000000Z',
            'stats' => ['total' => 120, 'sent' => 0, 'failed' => 120, 'pending' => 0, 'scheduled' => 0, 'delivery_rate' => 0, 'failure_rate' => 100, 'cost' => 0],
        ],
        'meta' => ['cancelled' => 120],
    ])]);

    $cancellation = GesflowSender::campaigns()->cancel(9);

    expect($cancellation->cancelledMessages)->toBe(120)
        ->and($cancellation->campaign->stats->failed)->toBe(120)
        ->and($cancellation->campaign->recipientsCount)->toBe(120);
});
