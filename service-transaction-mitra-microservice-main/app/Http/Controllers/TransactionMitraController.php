<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\TransactionMitra;

class TransactionMitraController extends Controller
{
    protected $mitraGraphqlUrl;
    protected $productGraphqlUrl;

    public function __construct()
    {
        // URL use Traefik
        $this->mitraGraphqlUrl = env('MITRA_GRAPHQL_URL', 'http://traefik/api/v1/mitras/graphql');
        $this->productGraphqlUrl = env('PRODUCT_GRAPHQL_URL', 'http://traefik/api/v1/products/graphql');
    }

    // GET /transactions-mitra (list all)
    public function index()
    {
        // Get All Transactions Mitra
        $transactions = TransactionMitra::with('items')->get();

        return response()->json([
            'status' => 200,
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions,
            'errors' => null
        ], 200);
    }

    // GET /transactions-mitra/{id} (get single)
    public function show($id)
    {
        $transaction = TransactionMitra::with('items')->find($id);

        // Check Transaction With transaction_id
        if (!$transaction) {
            return $this->notFoundResponse('transaction_id', 'Transaction not found');
        }

        return response()->json([
            'status' => 200,
            'message' => 'Transaction retrieved successfully',
            'data' => $transaction,
            'errors' => null
        ], 200);
    }

    // POST /transactions-mitra (create)
    public function store(Request $request)
    {
        // Validation Request
        $validator = validator($request->all(), [
            'mitra_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'status' => 'required|in:pending,completed,cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Catch Validation
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation errors',
                'data' => null,
                'errors' => collect($validator->errors())->map(function ($v, $k) {
                    return ['field' => $k, 'message' => $v[0]];
                })->values()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $mitraData = $this->fetchMitra($request->mitra_id);
            if (!$mitraData) {
                return $this->notFoundResponse('mitra_id', 'Mitra not found');
            }

            $totalPrice = 0;
            $transactionItemsData = [];

            foreach ($request->items as $item) {
                $productData = $this->fetchProduct($item['product_id']);

                // Check product in service product
                if (!$productData) {
                    DB::rollBack();
                    return $this->notFoundResponse('product_id', "Product ID {$item['product_id']} not found");
                }

                // Use price Mitra
                $price = $productData['priceFromMitra'];
                if ($price === null) {
                    DB::rollBack();
                    return $this->errorResponse('priceFromMitra', "Price for mitra not set for product {$productData['name']}", 400);
                }

                // Update Stock
                $this->updateProductStock($productData['id'], $productData['stock'] + $item['quantity']);

                $subtotal = $price * $item['quantity'];
                $totalPrice += $subtotal;

                $transactionItemsData[] = [
                    'product_id' => $productData['id'],
                    'product_name' => $productData['name'],
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            // Create Transaction
            $transaction = TransactionMitra::create([
                'transaction_code' => 'TRX-MITRA-' . Str::upper(Str::random(8)),
                'mitra_id' => $mitraData['id'],
                'mitra_name' => $mitraData['name'],
                'total_price' => $totalPrice,
                'transaction_date' => $request->transaction_date,
                'status' => $request->status,
            ]);

            foreach ($transactionItemsData as $itemData) {
                $transaction->items()->create($itemData);
            }

            DB::commit();

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_created',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'mitra_id' => $transaction->mitra_id,
                'mitra_name' => $transaction->mitra_name,
                'phone' => $mitraData['phoneNumber'] ?? null,
                'status' => $transaction->status,
                'products' => collect($transactionItemsData)->map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                    ];
                })->toArray(),
                'message' => 'Transaction Mitra created successfully',
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Transaction Mitra created successfully',
                'data' => $transaction->load('items'),
                'errors' => null
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to save transaction: ' . $e->getMessage(), 500);
        }
    }

    // PUT /transactions-mitra/{id} (update)
    public function update(Request $request, $id)
    {
        // Take transaction mitra
        $transaction = TransactionMitra::with('items')->find($id);
        if (!$transaction) {
            return $this->notFoundResponse('transaction_id', 'Transaction not found');
        }

        // Validation Request
        $validator = validator($request->all(), [
            'mitra_id' => 'sometimes|integer',
            'transaction_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,completed,cancelled',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        // Catch Validation
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation errors',
                'data' => null,
                'errors' => collect($validator->errors())->map(fn($v, $k) => ['field' => $k, 'message' => $v[0]])->values()
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('mitra_id')) {
                // Check mitra
                $mitraData = $this->fetchMitra($request->mitra_id);
                if (!$mitraData) {
                    DB::rollBack();
                    return $this->notFoundResponse('mitra_id', 'Mitra not found');
                }
                $transaction->mitra_id = $mitraData['id'];
                $transaction->mitra_name = $mitraData['name'];
            }

            if ($request->has('transaction_date')) {
                $transaction->transaction_date = $request->transaction_date;
            }

            $totalPrice = 0;
            if ($request->has('items')) {
                // Restore old stock first
                foreach ($transaction->items as $oldItem) {
                    $productData = $this->fetchProduct($oldItem->product_id);
                    if ($productData) {
                        $restoredStock = $productData['stock'] - $oldItem->quantity;
                        if ($restoredStock < 0) {
                            DB::rollBack();
                            return $this->errorResponse('stock', "Cannot reduce stock below 0 for product ID {$oldItem->product_id}", 400);
                        }
                        $this->updateProductStock($oldItem->product_id, $restoredStock);
                    }
                }

                $transaction->items()->delete();

                // Save transaction product
                foreach ($request->items as $item) {
                    $productData = $this->fetchProduct($item['product_id']);
                    if (!$productData) {
                        DB::rollBack();
                        return $this->notFoundResponse('product_id', "Product ID {$item['product_id']} not found");
                    }

                    $price = $productData['priceFromMitra'];
                    if ($price === null) {
                        DB::rollBack();
                        return $this->errorResponse('priceFromMitra', "Price for mitra not set for product {$productData['name']}", 400);
                    }

                    $newStock = $productData['stock'] + $item['quantity'];
                    $this->updateProductStock($productData['id'], $newStock);

                    $subtotal = $price * $item['quantity'];
                    $totalPrice += $subtotal;

                    $transaction->items()->create([
                        'product_id' => $productData['id'],
                        'product_name' => $productData['name'],
                        'price' => $price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                }

                $transaction->total_price = $totalPrice;
            }

            $transaction->save();
            $transaction->load('items');

            DB::commit();

            $mitraData = $this->fetchMitra($transaction->mitra_id);

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_updated',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'mitra_id' => $transaction->mitra_id,
                'mitra_name' => $transaction->mitra_name,
                'status' => $transaction->status,
                'products' => $transaction->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ])->toArray(),
                'message' => 'Transaction Mitra updated successfully',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Transaction Mitra updated successfully',
                'data' => $transaction,
                'errors' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to update transaction: ' . $e->getMessage(), 500);
        }
    }

    // PATCH /transactions-mitra/{id}/{status} (patch)
    public function updateStatus($id, $status)
    {
        if (!in_array($status, ['pending', 'completed', 'cancelled'])) {
            return $this->errorResponse('status', 'Invalid status value', 422);
        }

        DB::beginTransaction();
        try {
            $transaction = TransactionMitra::with('items')->find($id);
            if (!$transaction) {
                DB::rollBack();
                return $this->notFoundResponse('transaction_id', 'Transaction not found');
            }

            $transaction->status = $status;
            $transaction->save();

            DB::commit();

            $mitraData = $this->fetchMitra($transaction->mitra_id);

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_status_updated',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'mitra_id' => $transaction->mitra_id,
                'mitra_name' => $transaction->mitra_name,
                'status' => $transaction->status,
                'phone' => $mitraData['phoneNumber'] ?? null,
                'products' => $transaction->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ])->toArray(),
                'message' => 'Transaction Mitra status updated successfully',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Transaction Mitra status updated successfully',
                'data' => $transaction,
                'errors' => null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to update transaction status: ' . $e->getMessage(), 500);
        }
    }

    // DELETE /transactions-mitra/{id} (delete)
    public function destroy($id)
    {
        $transaction = TransactionMitra::with('items')->find($id);
        if (!$transaction) {
            return $this->notFoundResponse('transaction_id', 'Transaction not found');
        }

        DB::beginTransaction();
        try {

            // Cek Stock
            foreach ($transaction->items as $item) {
                $productData = $this->fetchProduct($item->product_id);
                if ($productData) {
                    $newStock = $productData['stock'] - $item->quantity;
                    if ($newStock < 0) {
                        DB::rollBack();
                        return $this->errorResponse('stock', "Cannot reduce stock below 0 for product ID {$item->product_id}", 400);
                    }
                    $this->updateProductStock($item->product_id, $newStock);
                }
            }

            $items = $transaction->items;
            $transaction->items()->delete();
            $transaction->delete();

            DB::commit();

            $mitraData = $this->fetchMitra($transaction->mitra_id);

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_deleted',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'mitra_id' => $transaction->mitra_id,
                'mitra_name' => $transaction->mitra_name,
                'phone' => $mitraData['phoneNumber'] ?? null,
                'products' => $items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ])->toArray(),
                'message' => 'Transaction Mitra deleted successfully',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Transaction Mitra deleted successfully',
                'data' => null,
                'errors' => null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to delete transaction: ' . $e->getMessage(), 500);
        }
    }

    // Fetch Mitra
    private function fetchMitra($id)
    {
        $query = <<<'GRAPHQL'
        query getMitraById($id: Int!) {
            mitraById(id: $id) {
                id
                name
                phoneNumber
            }
        }
        GRAPHQL;

        $response = Http::post($this->mitraGraphqlUrl, ['query' => $query, 'variables' => ['id' => $id]]);
        return $response->successful() ? $response['data']['mitraById'] ?? null : null;
    }

    // Fetch Product
    private function fetchProduct($id)
    {
        $query = <<<'GRAPHQL'
        query getProductById($id: Int!) {
            productById(id: $id) {
                id
                name
                priceFromMitra
                stock
            }
        }
        GRAPHQL;

        $response = Http::post($this->productGraphqlUrl, ['query' => $query, 'variables' => ['id' => $id]]);
        return $response->successful() ? $response['data']['productById'] ?? null : null;
    }

    // Update Stock
    private function updateProductStock($id, $stock)
    {
        $mutation = <<<'GRAPHQL'
        mutation updateStock($id: Int!, $stock: Int!) {
            updateStockProduct(id: $id, stock: $stock)
        }
        GRAPHQL;

        $response = Http::post($this->productGraphqlUrl, ['query' => $mutation, 'variables' => ['id' => $id, 'stock' => $stock]]);
        if ($response->failed()) {
            throw new \Exception("Failed to update stock for product ID $id");
        }
    }

    // Error Response
    private function errorResponse($field, $message, $status)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => null,
            'errors' => [['field' => $field, 'message' => $message]]
        ], $status);
    }

    // Not Found Response
    private function notFoundResponse($field, $message)
    {
        return $this->errorResponse($field, $message, 404);
    }
}
