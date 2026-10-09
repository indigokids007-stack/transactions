<?php

use App\Models\Category;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Receipts\ReceiptOcr;
use App\Services\Receipts\ReceiptParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function receiptPhoto(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/receipt-sample.png'), 'receipt.png', 'image/png', null, true);
}

it('reads a receipt without writing expenses and confirms a reviewed batch only once', function () {
    Storage::fake('local');
    Sanctum::actingAs($user = User::factory()->create());
    $category = Category::factory()->create(['name' => 'Grechka']);
    $this->mock(ReceiptOcr::class)->shouldReceive('read')->once()->andReturn("Grechka 2.5 kg x 20000 = 50000\nJAMI 50000");
    $draft = $this->post('/api/receipts', ['image' => receiptPhoto()], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.items.0.quantity_unit', 'kg')->assertJsonPath('data.items.0.category_id', $category->id);
    expect(Transaction::count())->toBe(0);
    $id = $draft->json('data.id');
    $body = ['confirmed' => true, 'occurred_on' => today()->toDateString(), 'currency' => 'UZS', 'total' => '50000', 'items' => [['name' => 'Grechka', 'category_id' => $category->id, 'quantity' => '2.5', 'quantity_unit' => 'kg', 'amount' => '50000']]];
    $this->postJson("/api/receipts/{$id}/confirm", [...$body, 'confirmed' => false])->assertUnprocessable();
    $this->postJson("/api/receipts/{$id}/confirm", [...$body, 'total' => '60000'])->assertUnprocessable();
    expect(Transaction::count())->toBe(0);
    $response = $this->postJson("/api/receipts/{$id}/confirm", $body)->assertOk()
        ->assertJsonPath('data.0.is_market_purchase', true)->assertJsonPath('data.0.receipt_id', $id)->assertJsonPath('data.0.quantity', '2.500');
    $this->postJson("/api/receipts/{$id}/confirm", $body)->assertOk()->assertJsonPath('data.0.id', $response->json('data.0.id'));
    expect(Transaction::count())->toBe(1);
    $this->post('/api/receipts', ['image' => receiptPhoto()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.confirmed', true)->assertJsonPath('data.id', $id);
    $this->get("/api/receipts/{$id}/image")->assertOk();
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/receipts/{$id}/image")->assertForbidden();
    $this->postJson("/api/receipts/{$id}/confirm", $body)->assertForbidden();
});

it('rejects an entire receipt with an invalid category or quantity', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $category = Category::factory()->create();
    $receipt = Receipt::create(['user_id' => $user->id, 'image_path' => 'receipts/sample.png', 'image_hash' => str_repeat('a', 64), 'ocr_text' => '', 'draft' => []]);
    $valid = ['name' => 'Sut', 'category_id' => $category->id, 'quantity' => '3', 'quantity_unit' => 'litr', 'amount' => '30000'];
    $body = ['confirmed' => true, 'occurred_on' => today()->toDateString(), 'currency' => 'UZS', 'total' => '60000', 'items' => [$valid, [...$valid, 'category_id' => 999999]]];
    $this->postJson("/api/receipts/{$receipt->id}/confirm", $body)->assertUnprocessable();
    $this->postJson("/api/receipts/{$receipt->id}/confirm", [...$body, 'items' => [$valid, [...$valid, 'quantity' => '0']]])->assertUnprocessable();
    expect(Transaction::count())->toBe(0)->and($receipt->fresh()->confirmed_at)->toBeNull();
});

it('keeps receipt quantities separate for kilograms litres and pieces', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $category = Category::factory()->create();
    $receipt = Receipt::create(['user_id' => $user->id, 'image_path' => 'receipts/sample.png', 'image_hash' => str_repeat('b', 64), 'ocr_text' => '', 'draft' => []]);
    $items = collect(['kg', 'litr', 'dona'])->map(fn ($unit) => ['name' => 'Tovar '.$unit, 'category_id' => $category->id, 'quantity' => '2', 'quantity_unit' => $unit, 'amount' => '10000'])->all();
    $this->postJson("/api/receipts/{$receipt->id}/confirm", ['confirmed' => true, 'occurred_on' => today()->toDateString(), 'currency' => 'UZS', 'total' => '30000', 'items' => $items])
        ->assertOk()->assertJsonCount(3, 'data');
    expect(Transaction::orderBy('id')->pluck('quantity_unit')->all())->toBe(['kg', 'litr', 'dona']);
});

it('stores the market purchase marker without affecting the amount', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $response = $this->postJson('/api/transactions', ['type' => 'expense', 'amount' => '10000', 'currency' => 'UZS', 'occurred_on' => today()->toDateString(), 'category_id' => $category->id, 'is_market_purchase' => true])->assertCreated()->assertJsonPath('data.is_market_purchase', true);
    $this->patchJson('/api/transactions/'.$response->json('data.id'), ['is_market_purchase' => false])->assertOk()->assertJsonPath('data.is_market_purchase', false)->assertJsonPath('data.amount_minor', 10000);
});

it('parses photographed receipt text with mixed units and grouped money', function () {
    $parsed = app(ReceiptParser::class)->parse("Grechka 2.5 kg x 20 000 = 50 000.00\nSut 3 litr x 10 000 = 30 000.00\nNon 4 dona 12 000.00\nJAMI: 92 000.00");
    expect($parsed['total'])->toBe('92000')->and(array_column($parsed['items'], 'quantity_unit'))->toBe(['kg', 'litr', 'dona']);
    expect(array_column($parsed['items'], 'amount'))->toBe(['50000', '30000', '12000']);
});

it('reads a real receipt image with the installed OCR engine', function () {
    if (! is_executable('/usr/bin/tesseract')) {
        $this->markTestSkipped('Tesseract is not installed.');
    }
    $text = app(ReceiptOcr::class)->read(base_path('tests/Fixtures/receipt-sample.png'));
    $parsed = app(ReceiptParser::class)->parse($text);
    expect($parsed['total'])->toBe('80000')->and(count($parsed['items']))->toBe(2);
    expect(array_column($parsed['items'], 'quantity_unit'))->toBe(['kg', 'litr']);
});
