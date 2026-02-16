<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\DTOs\RolesAndPermissions\AssignUserRoleDTO;
use App\DTOs\RolesAndPermissions\UnassignUserRoleDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Actions\RolesAndPermissions\AssignUserRoleAction;
use App\DTOs\RolesAndPermissions\AddPermissionsToRoleDTO;
use App\Actions\RolesAndPermissions\UnassignUserRoleAction;
use App\DTOs\RolesAndPermissions\AssignPermissionsToRoleDTO;
use App\DTOs\RolesAndPermissions\CreateOrUpdatePermissionsDTO;
use App\DTOs\RolesAndPermissions\CreateRoleWithPermissionsDTO;
use App\DTOs\RolesAndPermissions\RemovePermissionsFromRoleDTO;
use App\Actions\RolesAndPermissions\AddPermissionsToRoleAction;
use App\DTOs\RolesAndPermissions\GetUserRolesAndPermissionsDTO;
use App\DTOs\RolesAndPermissions\AdminEditRoleWithPermissionsDTO;
use App\Actions\RolesAndPermissions\AssignPermissionsToRoleAction;
use App\Actions\RolesAndPermissions\CreateOrUpdatePermissionsAction;
use App\Actions\RolesAndPermissions\CreateRoleWithPermissionsAction;
use App\Actions\RolesAndPermissions\GetAllRolesAndPermissionsAction;
use App\Actions\RolesAndPermissions\RemovePermissionsFromRoleAction;
use App\Actions\RolesAndPermissions\GetUserRolesAndPermissionsAction;
use App\Actions\RolesAndPermissions\AdminEditRoleWithPermissionsAction;
use App\Actions\RolesAndPermissions\GetAllUsersWithRolesPermissionsAndWalletAction;

class RolePermissionController extends Controller
{
    public function createRoleWithPermissions(Request $request, CreateRoleWithPermissionsAction $action)
    {
        $validated = $request->validate([
            'role' => 'required|string|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        try {
            $dto = new CreateRoleWithPermissionsDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                message: 'Role and permissions created successfully',
                data: [
                    'role' => $role->name,
                    'permissions' => $dto->permissions,
                ]
            );
        } catch (Throwable $th) {
            return failureResponse(
                message: 'Failed to create role and permissions',
                status: 500,
                type: 'role_creation_error',
                th: $th
            );
        }
    }

    public function adminCreateRoleWithPermissions(Request $request, CreateRoleWithPermissionsAction $action)
    {
        $validated = $request->validate([
            'role' => 'required|string|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        try {
            $dto = new CreateRoleWithPermissionsDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                message: 'Role and permissions created successfully',
                data: [
                    'role' => $role->name,
                    'permissions' => $dto->permissions,
                ]
            );
        } catch (Throwable $th) {
            return failureResponse(
                message: 'Failed to create role and permissions',
                status: 500,
                type: 'role_creation_error',
                th: $th
            );
        }
    }

    public function getAllRolesAndPermissions(GetAllRolesAndPermissionsAction $action)
    {
        try {
            $data = $action->execute();

            return successResponse('Roles and permissions fetched successfully', $data);
        } catch (\Throwable $th) {
            return failureResponse(
                message: 'Failed to fetch roles and permissions',
                status: 500,
                type: 'role_fetch_error',
                th: $th
            );
        }
    }

    public function assignRole(Request $request, AssignUserRoleAction $action)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'role' => 'required|string|exists:roles,name',
        ]);

        try {
            $dto = new AssignUserRoleDTO($validated);
            $user = $action->execute($dto);

            return successResponse('Role assigned successfully', [
                'user' => $user->only(['id', 'name', 'email']),
                'role' => $dto->role
            ]);
        } catch (ModelNotFoundException $e) {
            return failureResponse('User not found', 404, 'user_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to assign role',
                500,
                'assign_role_error',
                $th
            );
        }
    }

    public function adminAssignRole(Request $request, AssignUserRoleAction $action)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'role' => [
                'required',
                'string',
                'exists:roles,name',
                Rule::notIn(['driver', 'partner', 'investor']),
            ],
        ]);

        try {
            $dto = new AssignUserRoleDTO($validated);
            $user = $action->execute($dto);

            return successResponse('Role assigned successfully', [
                'user' => $user->only(['id', 'name', 'email']),
                'role' => $dto->role
            ]);
        } catch (ModelNotFoundException $e) {
            return failureResponse('User not found', 404, 'user_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to assign role',
                500,
                'admin_assign_role_error',
                $th
            );
        }
    }

    public function adminUnassignRole(Request $request, UnassignUserRoleAction $action)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'role' => [
                'required',
                'string',
                'exists:roles,name',
                Rule::notIn(['driver', 'partner', 'investor']),
            ],
        ]);

        try {
            $dto = new UnassignUserRoleDTO($validated);
            $user = $action->execute($dto);

            return successResponse('Role unassigned successfully', [
                'user' => $user->only(['id', 'name', 'email']),
                'role' => $dto->role
            ]);
        } catch (ModelNotFoundException $e) {
            return failureResponse('User not found', 404, 'user_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to unassign role',
                500,
                'admin_unassign_role_error',
                $th
            );
        }
    }


    public function getUserRoleAndPermissions(Request $request, GetUserRolesAndPermissionsAction $action)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
        ]);

        try {
            $dto = new GetUserRolesAndPermissionsDTO($validated);
            $data = $action->execute($dto);

            return successResponse('User role and permissions retrieved successfully', $data);
        } catch (ModelNotFoundException $e) {
            return failureResponse('User not found', 404, 'user_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to fetch user role and permissions',
                500,
                'get_user_role_error',
                $th
            );
        }
    }

    public function getAllUsersWithRolesAndPermissions(
        Request $request,
        GetAllUsersWithRolesPermissionsAndWalletAction $action
    ) {
        try {
            $search = $request->input('search');      // Get the search term from query param (e.g., ?search=John)
            $perPage = $request->input('per_page', 10);

            $data = $action->execute($search, $perPage);  // Pass both to the action

            return successResponse(
                'All users with their roles, permissions, and wallet details fetched successfully.',
                $data
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to fetch users with roles and permissions.',
                500,
                'user_fetch_error',
                $th
            );
        }
    }



    public function assignPermissionsToRole(Request $request, AssignPermissionsToRoleAction $action)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $dto = new AssignPermissionsToRoleDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                'Permissions assigned successfully to role and all users with that role',
                [
                    'role'        => $role->name,
                    'permissions' => $dto->permissions,
                    'users'       => $role->users()->with('permissions')->get(),
                ]
            );
        } catch (ModelNotFoundException $e) {
            return failureResponse('Role not found', 404, 'role_not_found');
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to assign permissions to role',
                500,
                'assign_permissions_error',
                $th
            );
        }
    }


    public function addPermissionsToRole(Request $request, AddPermissionsToRoleAction $action)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        try {
            $dto = new AddPermissionsToRoleDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                'Permissions added and assigned to role successfully.',
                [
                    'role' => $role->name,
                    'permissions' => $dto->permissions,
                ]
            );
        } catch (ModelNotFoundException $e) {
            return failureResponse('Role not found', 404, 'role_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to add permissions to role',
                500,
                'add_permissions_error',
                $th
            );
        }
    }

    public function removePermissionsFromRole(Request $request, RemovePermissionsFromRoleAction $action)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        try {
            $dto = new RemovePermissionsFromRoleDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                'Permissions removed from role successfully.',
                [
                    'role' => $role->name,
                    'permissions_removed' => $dto->permissions,
                ]
            );
        } catch (ModelNotFoundException $e) {
            return failureResponse('Role not found', 404, 'role_not_found');
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to remove permissions from role',
                500,
                'remove_permissions_error',
                $th
            );
        }
    }


    public function adminEditRoleWithPermissions(Request $request, AdminEditRoleWithPermissionsAction $action)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'role' => [
                'required',
                'string',
                Rule::unique('roles', 'name')->ignore($request->role_id),
            ],
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $dto = new AdminEditRoleWithPermissionsDTO($validated);
            $role = $action->execute($dto);

            return successResponse(
                message: 'Role updated successfully',
                data: [
                    'role' => $role->name,
                    'permissions' => $dto->permissions,
                ]
            );
        } catch (Throwable $th) {
            return failureResponse(
                message: 'Failed to update role',
                status: 500,
                type: 'role_update_error',
                th: $th
            );
        }
    }

    public function adminCreateOrUpdatePermissions(Request $request, CreateOrUpdatePermissionsAction $action)
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string',
        ]);

        try {
            $dto = new CreateOrUpdatePermissionsDTO($validated['permissions']);
            $permissions = $action->execute($dto);

            return successResponse(
                message: 'Permissions created/updated successfully',
                data: $permissions
            );
        } catch (\Throwable $th) {
            return failureResponse(
                message: 'Failed to create/update permissions',
                status: 500,
                type: 'permission_creation_update_error',
                th: $th
            );
        }
    }


    public function createOrUpdatePermissions(Request $request, CreateOrUpdatePermissionsAction $action)
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string',
        ]);

        try {
            $dto = new CreateOrUpdatePermissionsDTO($validated['permissions']);
            $permissions = $action->execute($dto);

            return successResponse(
                message: 'Permissions created/updated successfully',
                data: $permissions
            );
        } catch (\Throwable $th) {
            return failureResponse(
                message: 'Failed to create/update permissions',
                status: 500,
                type: 'permission_creation_update_error',
                th: $th
            );
        }
    }


    public function assignAllPermissionsToAdmin($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (!$user->hasRole('super_admin')) {
                return failureResponse('User does not have the admin role.', 403, 'role_check');
            }

            $permissions = Permission::all();

            // Assign all permissions to the admin role
            $adminRole = Role::where('name', 'admin')->firstOrFail();
            $adminRole->syncPermissions($permissions);

            // Assign all permissions to all users with the admin role
            $adminUsers = User::role('admin')->get();
            foreach ($adminUsers as $adminUser) {
                $adminUser->syncPermissions($permissions);
            }

            return successResponse(
                'All permissions assigned to admin role and all admin users successfully.',
                [
                    'role'  => $adminRole->load('permissions'),
                    'users' => $adminUsers->load('roles', 'permissions')
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return failureResponse('User or role not found.', 404, null, $e);
        } catch (\Throwable $th) {
            return failureResponse('Failed to assign permissions.', 500, 'server_error', $th);
        }
    }


    public function adminAssignAllPermissionsToAdmin($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (!$user->hasRole('admin')) {
                return failureResponse('User does not have the admin role.', 403, 'role_check');
            }

            $permissions = Permission::all();

            // Assign all permissions to the admin role
            $adminRole = Role::where('name', 'admin')->firstOrFail();
            $adminRole->syncPermissions($permissions);

            // Assign all permissions to all users with the admin role
            $adminUsers = User::role('admin')->get();
            foreach ($adminUsers as $adminUser) {
                $adminUser->syncPermissions($permissions);
            }

            return successResponse(
                'All permissions assigned to admin role and all admin users successfully.',
                [
                    'role'  => $adminRole->load('permissions'),
                    'users' => $adminUsers->load('roles', 'permissions')
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return failureResponse('User or role not found.', 404, null, $e);
        } catch (\Throwable $th) {
            return failureResponse('Failed to assign permissions.', 500, 'server_error', $th);
        }
    }
}
