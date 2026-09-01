<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for complexity tiers (fd_stage_template_sets). Each elevation type keeps
 * at least one tier; exactly one is the default used when an elevation doesn't
 * name a tier.
 */
class StageTemplateSetController extends Controller
{
    public function index(Request $request)
    {
        $query = FdStageTemplateSet::query()->with('templates.defaultUser')->orderBy('sort_order');

        if ($request->filled('elevation_type_id')) {
            $query->where('elevation_type_id', $request->elevation_type_id);
        }

        return response()->json([
            'stage_template_sets' => $query->get()->map(fn ($s) => $this->fmt($s)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'elevation_type_id' => 'required|integer|exists:fd_elevation_types,id',
            'name'              => 'required|string|max:100',
            'is_default'        => 'sometimes|boolean',
        ]);

        if ($this->nameTaken($data['elevation_type_id'], $data['name'])) {
            return response()->json(['message' => 'A tier with that name already exists for this type.'], 422);
        }

        $set = DB::transaction(function () use ($data) {
            $max = FdStageTemplateSet::where('elevation_type_id', $data['elevation_type_id'])->max('sort_order') ?? -1;

            $set = FdStageTemplateSet::create([
                'elevation_type_id' => $data['elevation_type_id'],
                'name'       => $data['name'],
                'sort_order' => $max + 1,
                'is_default' => $data['is_default'] ?? false,
            ]);

            if ($set->is_default) {
                $this->clearOtherDefaults($set);
            }

            return $set;
        });

        return response()->json(['id' => $set->id, 'stage_template_set' => $this->fmt($set->load('templates'))], 201);
    }

    public function update(Request $request, int $id)
    {
        $set  = FdStageTemplateSet::findOrFail($id);
        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:100',
            'sort_order' => 'sometimes|integer',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($data['name']) && $this->nameTaken($set->elevation_type_id, $data['name'], $set->id)) {
            return response()->json(['message' => 'A tier with that name already exists for this type.'], 422);
        }

        DB::transaction(function () use ($set, $data) {
            if (isset($data['name'])) {
                $set->name = $data['name'];
            }
            if (array_key_exists('sort_order', $data)) {
                $set->sort_order = (int) $data['sort_order'];
            }
            // Only ever promote a tier to default; demote the rest. To change the
            // default, promote a different tier.
            if (! empty($data['is_default'])) {
                $set->is_default = true;
            }
            $set->save();

            if ($set->is_default) {
                $this->clearOtherDefaults($set);
            }
        });

        return response()->json(['stage_template_set' => $this->fmt($set->fresh('templates'))]);
    }

    public function destroy(Request $request, int $id)
    {
        $set = FdStageTemplateSet::findOrFail($id);

        $siblings = FdStageTemplateSet::where('elevation_type_id', $set->elevation_type_id)
            ->where('id', '!=', $set->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        if ($siblings->isEmpty()) {
            return response()->json(['message' => 'An elevation type must keep at least one tier.'], 422);
        }

        $templateCount = FdStageTemplate::where('template_set_id', $set->id)->count();
        if ($templateCount > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => "This tier has {$templateCount} step(s). Delete with force=1 to move them to \"{$siblings->first()->name}\".",
                'code'    => 'tier_not_empty',
            ], 422);
        }

        DB::transaction(function () use ($set, $siblings, $templateCount) {
            $fallback = $siblings->first();

            if ($templateCount > 0) {
                FdStageTemplate::where('template_set_id', $set->id)
                    ->update(['template_set_id' => $fallback->id]);
            }

            $wasDefault = $set->is_default;
            $set->delete();

            if ($wasDefault) {
                $fallback->update(['is_default' => true]);
            }
        });

        return response()->json(['deleted' => $id]);
    }

    private function nameTaken(int $typeId, string $name, ?int $exceptId = null): bool
    {
        return FdStageTemplateSet::where('elevation_type_id', $typeId)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    private function clearOtherDefaults(FdStageTemplateSet $set): void
    {
        FdStageTemplateSet::where('elevation_type_id', $set->elevation_type_id)
            ->where('id', '!=', $set->id)
            ->update(['is_default' => false]);
    }

    private function fmt(FdStageTemplateSet $s): array
    {
        return [
            'id'                => $s->id,
            'elevation_type_id' => $s->elevation_type_id,
            'name'              => $s->name,
            'sort_order'        => $s->sort_order,
            'is_default'        => $s->is_default,
            'stage_templates'   => $s->relationLoaded('templates')
                ? $s->templates->map(fn ($t) => [
                    'id'              => $t->id,
                    'name'            => $t->name,
                    'description'     => $t->description,
                    'sort_order'      => $t->sort_order,
                    'blocks_next'     => (bool) $t->blocks_next,
                    'default_user_id' => $t->default_user_id,
                    'default_user'    => $t->defaultUser ? ['id' => $t->defaultUser->id, 'name' => $t->defaultUser->name] : null,
                ])->values()
                : [],
        ];
    }
}
