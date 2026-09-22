<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorFrameComponent;
use App\Models\ConfiguratorFrameFastener;
use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorFrameSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfiguratorCatalogController extends Controller
{
    /**
     * Full nested catalog tree: systems -> series -> profiles -> components -> fasteners.
     * Used by both the admin screen and the frame-series picker in the builder.
     */
    public function tree()
    {
        $systems = ConfiguratorFrameSystem::with([
            'series.profiles.product',
            'series.profiles.components.product',
            'series.profiles.components.fasteners.product',
        ])->orderBy('sort_order')->get();

        // Nested products here only need identity fields for BOM matching —
        // hide Product's $appends (quantity_available et al) so this tree,
        // which is re-fetched a lot (frame-series picker), doesn't pay for a
        // per-product reservation query on every product in the whole catalog.
        $systems->each(function ($system) {
            $system->series->each(function ($series) {
                $series->profiles->each(function ($profile) {
                    $profile->product?->makeHidden($profile->product->getAppends());
                    $profile->components->each(function ($component) {
                        $component->product?->makeHidden($component->product->getAppends());
                        $component->fasteners->each(function ($fastener) {
                            $fastener->product?->makeHidden($fastener->product->getAppends());
                        });
                    });
                });
            });
        });

        return response()->json(['frame_systems' => $systems]);
    }

    // ---- Frame Systems ----

    public function storeSystem(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30|unique:configurator_frame_systems,code',
            'sort_order' => 'nullable|integer',
        ]);

        $system = ConfiguratorFrameSystem::create($data);

        return response()->json(['frame_system' => $system], 201);
    }

    public function updateSystem(Request $request, $id)
    {
        $system = ConfiguratorFrameSystem::findOrFail($id);

        $data = $this->validateOrFail($request, [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30|unique:configurator_frame_systems,code,'.$id,
            'sort_order' => 'nullable|integer',
        ]);

        $system->update($data);

        return response()->json(['frame_system' => $system]);
    }

    public function destroySystem($id)
    {
        ConfiguratorFrameSystem::findOrFail($id)->delete();

        return response()->json(['message' => 'Frame system deleted']);
    }

    // ---- Frame Series ----

    public function storeSeries(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'frame_system_id' => 'required|exists:configurator_frame_systems,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30',
            'sort_order' => 'nullable|integer',
        ]);

        $series = ConfiguratorFrameSeries::create($data);

        return response()->json(['frame_series' => $series], 201);
    }

    public function updateSeries(Request $request, $id)
    {
        $series = ConfiguratorFrameSeries::findOrFail($id);

        $data = $this->validateOrFail($request, [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30',
            'sort_order' => 'nullable|integer',
        ]);

        $series->update($data);

        return response()->json(['frame_series' => $series]);
    }

    public function destroySeries($id)
    {
        ConfiguratorFrameSeries::findOrFail($id)->delete();

        return response()->json(['message' => 'Frame series deleted']);
    }

    // ---- Frame Profiles (extrusions) ----

    public function storeProfile(Request $request)
    {
        $data = $this->validateOrFail($request, $this->profileRules());

        $profile = ConfiguratorFrameProfile::create($data);
        $profile->load('product');

        return response()->json(['frame_profile' => $profile], 201);
    }

    public function updateProfile(Request $request, $id)
    {
        $profile = ConfiguratorFrameProfile::findOrFail($id);

        $rules = $this->profileRules();
        unset($rules['frame_series_id']);
        $data = $this->validateOrFail($request, $rules);

        $profile->update($data);
        $profile->load('product');

        return response()->json(['frame_profile' => $profile]);
    }

    public function destroyProfile($id)
    {
        ConfiguratorFrameProfile::findOrFail($id)->delete();

        return response()->json(['message' => 'Frame profile deleted']);
    }

    private function profileRules(): array
    {
        return [
            'frame_series_id' => 'required|exists:configurator_frame_series,id',
            'role_label' => 'required|string|max:100',
            'product_id' => 'required|exists:products,id',
            'formula' => 'required|array',
            'formula.*.sign' => 'required|in:1,-1',
            // W/H/TH/fixed/if_threshold, or "section:<role label>"
            'formula.*.var' => 'required|string|max:150',
            'formula.*.value' => 'nullable|numeric',
            'condition' => 'nullable|in:single,pair,transom,threshold,transom_pair',
            'glass_thicknesses' => 'nullable|array',
            'glass_thicknesses.*' => 'numeric',
            'section_height' => 'nullable|numeric',
            'qty_per_opening' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ];
    }

    // ---- Frame Components ----

    public function storeComponent(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'frame_profile_id' => 'required|exists:configurator_frame_profiles,id',
            'label' => 'required|string|max:100',
            'product_id' => 'required|exists:products,id',
            'qty_type' => 'required|in:per_opening,per_door,per_length',
            'qty_per' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $component = ConfiguratorFrameComponent::create($data);
        $component->load('product');

        return response()->json(['frame_component' => $component], 201);
    }

    public function updateComponent(Request $request, $id)
    {
        $component = ConfiguratorFrameComponent::findOrFail($id);

        $data = $this->validateOrFail($request, [
            'label' => 'required|string|max:100',
            'product_id' => 'required|exists:products,id',
            'qty_type' => 'required|in:per_opening,per_door,per_length',
            'qty_per' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $component->update($data);
        $component->load('product');

        return response()->json(['frame_component' => $component]);
    }

    public function destroyComponent($id)
    {
        ConfiguratorFrameComponent::findOrFail($id)->delete();

        return response()->json(['message' => 'Frame component deleted']);
    }

    // ---- Frame Fasteners ----

    public function storeFastener(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'frame_component_id' => 'required|exists:configurator_frame_components,id',
            'label' => 'required|string|max:100',
            'product_id' => 'required|exists:products,id',
            'qty_per' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $fastener = ConfiguratorFrameFastener::create($data);
        $fastener->load('product');

        return response()->json(['frame_fastener' => $fastener], 201);
    }

    public function updateFastener(Request $request, $id)
    {
        $fastener = ConfiguratorFrameFastener::findOrFail($id);

        $data = $this->validateOrFail($request, [
            'label' => 'required|string|max:100',
            'product_id' => 'required|exists:products,id',
            'qty_per' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $fastener->update($data);
        $fastener->load('product');

        return response()->json(['frame_fastener' => $fastener]);
    }

    public function destroyFastener($id)
    {
        ConfiguratorFrameFastener::findOrFail($id)->delete();

        return response()->json(['message' => 'Frame fastener deleted']);
    }

    private function validateOrFail(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            abort(response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
