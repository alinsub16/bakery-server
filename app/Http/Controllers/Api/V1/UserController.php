<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('roles:id,name')
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

        // A user has exactly one role: replace, don't accumulate.
        $user->roles()->sync([$newRole->id]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $newRole->name,
        ]);
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