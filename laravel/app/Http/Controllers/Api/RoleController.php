<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return response()->json($roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'user_count' => $role->users()->count(),
                'permissions' => $role->permissions->pluck('name'),
                'permission_count' => $role->permissions->count(),
                'created_at' => $role->created_at->format('Y-m-d H:i:s'),
            ];
        }));
    }

    /**
     * Get a single role with its permissions
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'user_count' => $role->users()->count(),
            'permissions' => $role->permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'category' => $permission->category,
                ];
            }),
            'created_at' => $role->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name|alpha_dash',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        // Assign permissions if provided
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Update a role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles')->ignore($role->id)],
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update([
            'name' => $validated['name'] ?? $role->name,
            'display_name' => $validated['display_name'] ?? $role->display_name,
            'description' => $validated['description'] ?? $role->description,
        ]);

        // Update permissions if provided
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    /**
     * Delete a role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        if ($role->is_system) {
            return response()->json([
                'message' => 'Cannot delete system roles'
            ], 403);
        }

        // Check if role is in use
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users',
                'user_count' => $role->users()->count()
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get all available permissions
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('category')->orderBy('name')->get();

        // Group permissions by category
        $grouped = $permissions->groupBy('category')->map(function($permissions, $category) {
            return [
                'category' => $category,
                'permissions' => $permissions->map(function($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'description' => $permission->description,
                    ];
                }),
            ];
        })->values();

        return response()->json($grouped);
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'message' => 'Permissions updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }
}
