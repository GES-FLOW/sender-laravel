<?php

use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function contactsFile(string $contents, string $name = 'contacts.csv'): string
{
    $path = sys_get_temp_dir().'/'.uniqid('gesflow-', true).'-'.$name;
    file_put_contents($path, $contents);

    return $path;
}

it('uploads a contact file with the bulk send', function () {
    Http::fake([apiUrl('messages/bulk') => Http::response([
        'data' => [messageData(['id' => 1, 'phone' => '242066000001'])],
        'meta' => ['count' => 1, 'skipped_rows' => 1, 'campaign_id' => 4],
    ], 201)]);

    $path = contactsFile("nom;telephone\nAwa;06 600 00 01\n");

    $batch = GesflowSender::bulkSms('Promo !')
        ->toNumbersFromFile($path)
        ->to(['242066000009'])
        ->flash()
        ->idempotencyKey('promo-1')
        ->send();

    expect($batch->skippedRows)->toBe(1);

    Http::assertSent(function (Request $request) use ($path): bool {
        $body = $request->body();

        return $request->isMultipart()
            && $request->header('Idempotency-Key') === ['promo-1']
            && str_contains($body, 'name="file"; filename="'.basename($path).'"')
            && str_contains($body, "nom;telephone\nAwa;06 600 00 01")
            && str_contains($body, 'name="phones[]"')
            && str_contains($body, '242066000009')
            && str_contains($body, 'name="flash"')
            && str_contains($body, 'name="text"');
    });
});

it('keeps the original name of an uploaded file', function () {
    Http::fake([apiUrl('messages/bulk') => Http::response(['data' => [], 'meta' => ['count' => 0]], 201)]);

    GesflowSender::bulkSms('Promo !')
        ->toNumbersFromFile(UploadedFile::fake()->createWithContent('clients.csv', "242066000001\n"))
        ->send();

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'filename="clients.csv"'));
});

it('refuses other formats and missing files', function () {
    expect(fn () => GesflowSender::bulkSms('Promo')->toNumbersFromFile(contactsFile('x', 'contacts.pdf')))
        ->toThrow(InvalidArgumentException::class, 'CSV ou XLSX')
        ->and(fn () => GesflowSender::bulkSms('Promo')->toNumbersFromFile('/nulle/part.csv'))
        ->toThrow(InvalidArgumentException::class, 'introuvable');
});

it('reads the numbers of a CSV file in the fake', function () {
    $fake = GesflowSender::fake();

    $batch = GesflowSender::bulkSms('Promo !')
        ->toNumbersFromFile(contactsFile("nom,telephone\nAwa,+242 06 600 00 01\nBob,242066000002\nsans numéro\n"))
        ->send();

    expect($batch)->toHaveCount(2);

    $fake->assertSentTo('242066000001');
    $fake->assertBulkSent(fn (array $payload): bool => $payload['text'] === 'Promo !');
    expect($fake->requests()[0]->files['file']['filename'])->toEndWith('contacts.csv');
});
