<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\FdWorkOrder;
use App\Models\PmProduct;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Submittal;
use App\Models\Supplier;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/**
 * One search across Forge. Every group is limited to what the user may see (permissions and job scope),
 * and each hit carries the link to open it. Small, capped result lists: this finds things, it does not report.
 */
class UniversalSearchController extends Controller
{
    private const LIMIT = 8;

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'groups' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $u = $request->user();
        $groups = [];
        $add = function (string $key, string $label, $items) use (&$groups) {
            if (count($items)) {
                $groups[] = ['key' => $key, 'label' => $label, 'items' => array_values($items)];
            }
        };

        if ($u->hasPermission('pm.jobs.view')) {
            $add('pm_jobs', 'Jobs (Project Management)', PmJobScope::query($u)
                ->where(fn ($w) => $w->where('job_number', 'like', $like)->orWhere('job_name', 'like', $like)->orWhere('customer_name', 'like', $like))
                ->orderByDesc('updated_at')->limit(self::LIMIT)->get()
                ->map(fn ($j) => ['title' => "{$j->job_number} — {$j->job_name}", 'sub' => $j->status_label.($j->customer_name ? ' · '.$j->customer_name : ''), 'href' => "/pm/jobs/{$j->id}"])->all());

            $ids = PmJobScope::visibleIds($u);
            $add('job_pos', 'Job purchase orders', PurchaseOrder::jobType()->with('supplier:id,name')
                ->when($ids !== null, fn ($b) => $b->whereIn('business_job_id', $ids))
                ->where(fn ($w) => $w->where('po_number', 'like', $like)->orWhere('notes', 'like', $like)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)))
                ->latest('id')->limit(self::LIMIT)->get()
                ->map(fn ($p) => ['title' => $p->po_number, 'sub' => $p->supplier?->name, 'href' => "/pm/purchase-orders/{$p->id}"])->all());

            $add('submittals', 'Submittals', Submittal::with('job:id,job_number')
                ->when($ids !== null, fn ($b) => $b->whereIn('business_job_id', $ids))
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('spec_section', 'like', $like))
                ->latest('id')->limit(self::LIMIT)->get()
                ->map(fn ($s) => ['title' => "{$s->number} {$s->name}", 'sub' => 'Job '.($s->job?->job_number ?? ''), 'href' => "/pm/jobs/{$s->business_job_id}"])->all());

            $add('library', 'Product library', PmProduct::with('manufacturer:id,name')
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('model', 'like', $like))
                ->limit(self::LIMIT)->get()
                ->map(fn ($p) => ['title' => $p->name, 'sub' => $p->manufacturer?->name.($p->model ? ' · '.$p->model : ''), 'href' => '/pm/library'])->all());
        }

        if ($u->hasPermission('jobs.view')) {
            $add('jobs', 'Jobs', BusinessJob::where(fn ($w) => $w->where('job_number', 'like', $like)->orWhere('job_name', 'like', $like)->orWhere('customer_name', 'like', $like))
                ->orderByDesc('updated_at')->limit(self::LIMIT)->get()
                ->map(fn ($j) => ['title' => "{$j->job_number} — {$j->job_name}", 'sub' => $j->status_label, 'href' => "/jobs?job={$j->id}"])->all());
        }
        if ($u->hasPermission('fabrication.work-orders.view')) {
            $add('work_orders', 'Work orders', FdWorkOrder::with('businessJob:id,job_number,job_name')
                ->whereHas('businessJob', fn ($j) => $j->where('job_number', 'like', $like)->orWhere('job_name', 'like', $like))
                ->orWhere('release_code', 'like', $like)->latest('id')->limit(self::LIMIT)->get()
                ->map(fn ($w) => ['title' => $w->businessJob ? "{$w->businessJob->job_number}-{$w->release_token}" : "WO {$w->id}", 'sub' => $w->businessJob?->job_name, 'href' => '/fabrication/work-orders'])->all());
        }
        if ($u->hasPermission('orders.view')) {
            $add('stock_pos', 'Stock purchase orders', PurchaseOrder::stock()->with('supplier:id,name')
                ->where(fn ($w) => $w->where('po_number', 'like', $like)->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)))
                ->latest('id')->limit(self::LIMIT)->get()
                ->map(fn ($p) => ['title' => $p->po_number, 'sub' => $p->supplier?->name, 'href' => '/purchase-orders'])->all());
        }
        if ($u->hasPermission('inventory.view')) {
            $add('products', 'Stock products', Product::where(fn ($w) => $w->where('sku', 'like', $like)->orWhere('part_number', 'like', $like)->orWhere('description', 'like', $like))
                ->limit(self::LIMIT)->get(['id', 'sku', 'part_number', 'description'])
                ->map(fn ($p) => ['title' => $p->sku, 'sub' => $p->description, 'href' => '/inventory/products?search='.urlencode($p->sku)])->all());
            $add('vendors', 'Vendors', Supplier::where('is_active', true)
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('bidestimate_code', 'like', $like)
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', $like)))
                ->limit(self::LIMIT)->get()
                ->map(fn ($s) => ['title' => $s->name, 'sub' => $s->is_internal ? 'Internal vendor' : $s->code, 'href' => '/suppliers'])->all());
        }

        return response()->json(['query' => $q, 'groups' => $groups]);
    }
}
