<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Contracts\Pos\PosServiceInterface;
use App\Http\Controllers\Api\V1\Controller;
use App\Http\Requests\Api\V1\Pos\CheckoutPosSaleRequest;
use App\Http\Requests\Api\V1\Pos\ClosePosSessionRequest;
use App\Http\Requests\Api\V1\Pos\HoldPosCartRequest;
use App\Http\Requests\Api\V1\Pos\OpenPosSessionRequest;
use App\Http\Requests\Api\V1\Pos\VoidPosSaleRequest;
use App\Http\Resources\Api\V1\Pos\PosSaleResource;
use App\Http\Resources\Api\V1\Pos\PosSessionHoldResource;
use App\Http\Resources\Api\V1\Pos\PosSessionResource;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\Pos\PosSessionHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PosSessionController extends Controller
{
    public function __construct(private PosServiceInterface $posService) {}

    public function store(OpenPosSessionRequest $request): JsonResponse
    {
        $session = $this->posService->openSession($request->validated());

        return (new PosSessionResource($session->load('warehouse')))
            ->response()
            ->setStatusCode(201);
    }

    public function outlets(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'pos.session.open', 'Anda tidak boleh membuka sesi kasir.');

        $outlets = Warehouse::query()
            ->where('is_active', true)
            ->where('code', 'not like', 'WH-E2E-%')
            ->where('code', 'not like', 'WH-OP-%')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return $this->success($outlets->all());
    }

    public function current(Request $request): PosSessionResource|JsonResponse
    {
        $user = $request->user();
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');

        $session = $this->posService->currentOpenSession((int) $user->id);
        if ($session === null) {
            return $this->error('Tidak ada sesi kasir yang terbuka.', 404);
        }

        return new PosSessionResource($session->load('warehouse', 'holds'));
    }

    public function show(Request $request, PosSession $pos_session): PosSessionResource
    {
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');
        $this->assertOwnSession($request, $pos_session);

        return new PosSessionResource($pos_session->load('warehouse', 'holds'));
    }

    public function sales(Request $request, PosSession $pos_session): AnonymousResourceCollection
    {
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');
        $this->assertOwnSession($request, $pos_session);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $sales = $pos_session->sales()
            ->with(['items', 'tenders'])
            ->latest('sold_at')
            ->latest('id')
            ->paginate($perPage);

        return PosSaleResource::collection($sales);
    }

    public function close(ClosePosSessionRequest $request, PosSession $pos_session): PosSessionResource
    {
        $this->assertOwnSession($request, $pos_session);

        $session = $this->posService->closeSession($pos_session, $request->validated());

        return new PosSessionResource($session->load('warehouse'));
    }

    public function catalog(Request $request, PosSession $pos_session): JsonResponse
    {
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');
        $this->assertOwnSession($request, $pos_session);

        $stocks = ProductStock::query()
            ->where('warehouse_id', $pos_session->warehouse_id)
            ->get()
            ->keyBy('product_id');

        $stockedIds = $stocks->keys()->all();

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->where(function ($query) use ($stockedIds): void {
                $query->whereIn('id', $stockedIds)
                    ->orWhere(function ($untracked): void {
                        $untracked->where('track_inventory', false)
                            ->whereHas('category', function ($category): void {
                                $category->where('code', 'like', 'POS-%');
                            });
                    });
            })
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($stocks, $pos_session) {
                $button = (! $pos_session->usesAddOnPricing() && $product->is_taxable)
                    ? (int) $product->selling_price_with_tax
                    : (int) $product->selling_price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'category' => $product->category?->name,
                    'button_price' => $button,
                    'is_taxable' => (bool) $product->is_taxable,
                    'track_inventory' => (bool) $product->track_inventory,
                    'quantity' => $product->track_inventory
                        ? (int) ($stocks[$product->id]->quantity ?? 0)
                        : null,
                    'image_url' => $this->catalogImageUrl($product->sku),
                ];
            });

        return $this->success($products->values()->all());
    }

    public function checkout(CheckoutPosSaleRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->assertOwnSession($request, $pos_session);

        $key = (string) $request->header('Idempotency-Key', '');
        $sale = $this->posService->checkout($pos_session, $request->validated(), $key);

        return (new PosSaleResource($sale))->response()->setStatusCode(201);
    }

    public function voidSale(VoidPosSaleRequest $request, PosSession $pos_session, PosSale $sale): PosSaleResource
    {
        $this->assertOwnSession($request, $pos_session);

        $sale = $this->posService->voidSale($pos_session, $sale, $request->validated('reason'));

        return new PosSaleResource($sale);
    }

    public function storeHold(HoldPosCartRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->assertOwnSession($request, $pos_session);

        $hold = $this->posService->hold($pos_session, $request->validated('lines'));

        return (new PosSessionHoldResource($hold))->response()->setStatusCode(201);
    }

    public function holds(Request $request, PosSession $pos_session): JsonResponse
    {
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');
        $this->assertOwnSession($request, $pos_session);

        return $this->success(
            PosSessionHoldResource::collection($this->posService->listHolds($pos_session))->resolve()
        );
    }

    public function takeHold(Request $request, PosSession $pos_session, PosSessionHold $hold): PosSessionHoldResource
    {
        $this->ensurePermission($request, 'pos.sale.checkout', 'Anda tidak boleh memakai kasir.');
        $this->assertOwnSession($request, $pos_session);

        return new PosSessionHoldResource($this->posService->takeHold($pos_session, $hold));
    }

    private function catalogImageUrl(?string $sku): ?string
    {
        if ($sku === null || $sku === '') {
            return null;
        }

        $safe = basename(str_replace('\\', '/', $sku));
        if ($safe !== $sku || preg_match('/[^A-Za-z0-9._-]/', $safe) === 1) {
            return null;
        }

        $directory = public_path('pos/kopitiam');
        $path = $directory.DIRECTORY_SEPARATOR.$safe.'.jpg';
        if (! is_file($path)) {
            return null;
        }

        $realDirectory = realpath($directory);
        $realPath = realpath($path);
        if ($realDirectory === false || $realPath === false) {
            return null;
        }
        if (! str_starts_with($realPath, $realDirectory.DIRECTORY_SEPARATOR) && $realPath !== $realDirectory) {
            return null;
        }

        return '/pos/kopitiam/'.$safe.'.jpg';
    }

    private function ensurePermission(Request $request, string $permission, string $message): void
    {
        abort_unless((bool) $request->user()?->hasPermission($permission), 403, $message);
    }

    private function assertOwnSession(Request $request, PosSession $session): void
    {
        $user = $request->user();
        if ($user?->isAdmin()) {
            return;
        }
        if ((int) $session->opened_by !== (int) $user?->id) {
            abort(403, 'Sesi kasir milik kasir lain.');
        }
    }
}
