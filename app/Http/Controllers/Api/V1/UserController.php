<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->where('status', 'active')
            ->orderBy('name')
            ->paginate(20);

        $users->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->primaryRole()?->name,
        ]);

        return response()->json($users);
    }

    public function pending(Request $request): JsonResponse
    {
        $users = User::where('status', 'pending')
            ->orderBy('created_at')
            ->paginate(20);

        $users->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'registered_at' => $user->created_at,
        ]);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'status' => 'active', // admin-created accounts are active immediately
        ]);

        $role = Role::where('name', $request->validated('role'))->firstOrFail();
        $user->roles()->sync([$role->id]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role->name,
        ], 201);
    }

    public function approve(ApproveUserRequest $request, User $user): JsonResponse
    {
        if (! $user->isPending()) {
            return response()->json([
                'message' => 'This user is not pending approval.',
            ], 409);
        }

        $role = Role::where('name', $request->validated('role'))->firstOrFail();

        $user->update(['status' => 'active']);
        $user->roles()->sync([$role->id]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role->name,
            'status' => 'active',
        ]);
    }

    public function reject(User $user): JsonResponse
    {
        if (! $user->isPending()) {
            return response()->json([
                'message' => 'This user is not pending approval.',
            ], 409);
        }

        $user->update(['status' => 'rejected']);

        return response()->json([
            'id' => $user->id,
            'status' => 'rejected',
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $actingUser = $request->user();

        if ($actingUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        if ($user->hasRole('admin') && $this->isLastAdmin($user)) {
            return response()->json([
                'message' => 'Cannot remove the last remaining admin.',
            ], 403);
        }

        $newRole = Role::where('name', $request->validated('role'))->firstOrFail();

        $user->roles()->sync([$newRole->id]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $newRole->name,
        ]);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        $actingUser = $request->user();

        if ($actingUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 403);
        }

        if ($user->hasRole('admin') && $this->isLastAdmin($user)) {
            return response()->json([
                'message' => 'Cannot deactivate the last remaining admin.',
            ], 403);
        }

        $user->delete(); // soft delete

        return response()->json(['message' => 'User deactivated.']);
    }

    public function activate(string $id): JsonResponse
{
    $user = User::withTrashed()->findOrFail($id);

    $user->restore();

    return response()->json(['message' => 'User activated.']);
}

    private function isLastAdmin(User $user): bool
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (! $adminRole) {
            return false;
        }

        return $adminRole->users()->count() <= 1;
    }
}