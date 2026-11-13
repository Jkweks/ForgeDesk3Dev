<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'contact_email',
        'contact_phone',
        'fax',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'website',
        'notes',
        'default_lead_time_days',
        'minimum_order_amount',
        'is_active',
        'is_internal',
        'products_services',
        'bidestimate_code',
        'extra_phones',
        'extra_emails',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_internal' => 'boolean',
        'extra_phones' => 'array',
        'extra_emails' => 'array',
        'minimum_order_amount' => 'decimal:2',
    ];

    public function contacts()
    {
        return $this->hasMany(SupplierContact::class)->orderBy('sort_order')->orderBy('id');
    }

    public function aliases()
    {
        return $this->hasMany(SupplierAlias::class);
    }

    /**
     * Resolve a vendor from an estimate's Supplier column (BidEstimate code, a
     * learned alias, the supplier code, or the name; punctuation/case-insensitive).
     */
    public static function findByEstimateTag(string $tag): ?self
    {
        $key = SupplierAlias::normalize($tag);
        if ($key === '') {
            return null;
        }

        $alias = SupplierAlias::where('alias_normalized', $key)->first();
        if ($alias) {
            return $alias->supplier;
        }

        return self::query()->get()->first(function (self $s) use ($key) {
            foreach ([$s->bidestimate_code, $s->code, $s->name] as $candidate) {
                if ($candidate !== null && SupplierAlias::normalize($candidate) === $key) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Replace the contact list (ordered as given). */
    public function syncContacts(array $contacts): void
    {
        $this->contacts()->delete();
        foreach (array_values($contacts) as $i => $c) {
            $this->contacts()->create([
                'name' => $c['name'],
                'title' => $c['title'] ?? null,
                'email' => $c['email'] ?? null,
                'phone' => $c['phone'] ?? null,
                'is_preferred_rep' => (bool) ($c['is_preferred_rep'] ?? false),
                'sort_order' => $i,
            ]);
        }
    }

    /** Replace manually-managed aliases; learned (non-manual) aliases are kept. */
    public function syncAliases(array $aliases): void
    {
        $this->aliases()->where('source', 'manual')->delete();
        foreach ($aliases as $alias) {
            $key = SupplierAlias::normalize((string) $alias);
            if ($key === '') {
                continue;
            }
            $this->aliases()->updateOrCreate(
                ['alias_normalized' => $key],
                ['alias' => trim($alias), 'source' => 'manual']
            );
        }
    }

    /**
     * Get all products from this supplier
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all purchase orders for this supplier
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
