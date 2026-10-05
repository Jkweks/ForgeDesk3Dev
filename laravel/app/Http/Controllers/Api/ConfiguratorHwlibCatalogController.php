<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorHwlibCategory;

class ConfiguratorHwlibCatalogController extends Controller
{
    /**
     * Categories with their exposed variables and items (with per-item
     * value overrides) — everything the hardware step UI needs to let
     * someone pick a category, then an item, and see/edit its prep values.
     */
    public function index()
    {
        $categories = ConfiguratorHwlibCategory::with([
            'variables',
            'subcategories',
            'items' => fn ($q) => $q->where('active', true)->orderBy('name'),
            'items.values.variable',
            'items.backers',
            'items.functions',
        ])->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['categories' => $categories]);
    }
}
