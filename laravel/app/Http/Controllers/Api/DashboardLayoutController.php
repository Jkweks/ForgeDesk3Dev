<?php

namespace App\Http\Controllers\Api;

use App\Dashboard\WidgetRegistry;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

/**
 * Customizable dashboard: the widget catalog the signed-in user may use, and
 * reading/saving the layout (per-user, plus an admin-set company default).
 */
class DashboardLayoutController extends Controller
{
    /** Widget catalog filtered to what the signed-in user may see. */
    public function widgets()
    {
        return response()->json(['widgets' => WidgetRegistry::forUser(auth()->user())]);
    }

    /** The layout to render, with where it came from (user | default | built-in). */
    public function show()
    {
        [$layout, $source] = WidgetRegistry::resolveLayout(auth()->user());

        return response()->json(['layout' => $layout, 'source' => $source]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $layout = $this->validatedLayout($request);
        $layout['widgets'] = WidgetRegistry::filterWidgets($user, $layout['widgets']);

        $user->update(['dashboard_prefs' => $layout]);

        return response()->json(['layout' => $layout, 'source' => 'user']);
    }

    /** Drop the user's customization so they fall back to the company/built-in default. */
    public function reset()
    {
        $user = auth()->user();
        $user->update(['dashboard_prefs' => null]);
        [$layout, $source] = WidgetRegistry::resolveLayout($user);

        return response()->json(['layout' => $layout, 'source' => $source]);
    }

    /** Set (or, with `widgets` omitted/null, clear) the company default layout. */
    public function updateDefault(Request $request)
    {
        $setting = CompanySetting::current();

        if ($request->input('widgets') === null) {
            $setting->update(['dashboard_default_layout' => null]);

            return response()->json(['layout' => WidgetRegistry::builtInLayout(), 'source' => 'built-in']);
        }

        $layout = $this->validatedLayout($request);
        $setting->update(['dashboard_default_layout' => $layout]);

        return response()->json(['layout' => $layout, 'source' => 'default']);
    }

    private function validatedLayout(Request $request): array
    {
        $columns = WidgetRegistry::GRID_COLUMNS;
        $data = $request->validate([
            'widgets' => 'required|array|max:50',
            'widgets.*.id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'widgets.*.key' => 'required|string|in:'.implode(',', array_keys(WidgetRegistry::all())),
            'widgets.*.x' => "required|integer|min:0|max:{$columns}",
            'widgets.*.y' => 'required|integer|min:0|max:1000',
            'widgets.*.w' => "required|integer|min:1|max:{$columns}",
            'widgets.*.h' => 'required|integer|min:1|max:30',
            'widgets.*.settings' => 'nullable|array',
        ]);

        $widgets = array_map(fn ($w) => [
            'id' => $w['id'],
            'key' => $w['key'],
            'x' => (int) $w['x'],
            'y' => (int) $w['y'],
            'w' => (int) $w['w'],
            'h' => (int) $w['h'],
            'settings' => WidgetRegistry::sanitizeSettings($w['key'], $w['settings'] ?? []) ?: (object) [],
        ], $data['widgets']);

        return ['version' => WidgetRegistry::LAYOUT_VERSION, 'widgets' => $widgets];
    }
}
