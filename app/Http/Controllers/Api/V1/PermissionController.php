<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Models\Accounting\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    /**
     * List all permissions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Permission::query();

        // Filter by group
        if ($request->has('group')) {
            $query->where('group', $request->input('group'));
        }

        // Search
        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', ["%{$search}%"]);
            });
        }

        $permissions = $query->orderBy('group')
            ->orderBy('name')
            ->paginate($request->input('per_page', 100));

        return PermissionResource::collection($permissions);
    }

    /**
     * Get permissions grouped by group.
     */
    public function grouped(): JsonResponse
    {
        $grouped = Permission::allGrouped();

        $result = $grouped->map(function ($permissions, $group) {
            return [
                'group' => $group,
                'group_label' => $this->getGroupLabel($group),
                'permissions' => PermissionResource::collection($permissions),
            ];
        })->values();

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get available permission groups.
     */
    public function groups(): JsonResponse
    {
        $groups = Permission::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->map(fn ($group) => [
                'name' => $group,
                'label' => $this->getGroupLabel($group),
            ]);

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Show a permission.
     */
    public function show(Permission $permission): PermissionResource
    {
        return new PermissionResource($permission);
    }

    /**
     * Get group label in Indonesian.
     */
    protected function getGroupLabel(string $group): string
    {
        return match ($group) {
            Permission::GROUP_ACCOUNTS => 'Akun',
            Permission::GROUP_CONTACTS => 'Kontak',
            Permission::GROUP_PRODUCTS => 'Produk',
            Permission::GROUP_INVOICES => 'Faktur Penjualan',
            Permission::GROUP_BILLS => 'Faktur Pembelian',
            Permission::GROUP_PAYMENTS => 'Pembayaran',
            Permission::GROUP_JOURNALS => 'Jurnal',
            Permission::GROUP_INVENTORY => 'Inventori',
            Permission::GROUP_BUDGETS => 'Anggaran',
            Permission::GROUP_REPORTS => 'Laporan',
            Permission::GROUP_SETTINGS => 'Pengaturan',
            Permission::GROUP_USERS => 'Pengguna',
            default => ucfirst($group),
        };
    }
}
