<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Admin\ChangeUserPasswordRequest;
use App\Jobs\SyncUserDeletionJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncUserUpdateJob;
use Illuminate\Database\Eloquent\Collection;


class UserAdminController extends Controller
{
    /**
     * GET Users (Production-Ready with Pagination)
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = Auth::user();
        $perPage = $request->get('per_page', 15); // Allow per_page customization, default to 15

        $query = User::with('domains:id,name,url')
            ->where('is_approved', true)
            ->where('id', '!=', $authUser->id);

        if ($authUser->user_origin === 'site_access_info' && $authUser->external_role === 'Admin') {
            $query->where('user_origin', 'site_access_info');
        } else {
            $query->where('role', '!=', 'admin')
                ->where(function ($subQuery) {
                    $subQuery->where('user_origin', '<>', 'jobfinder')
                        ->orWhere('external_role', 'Admin');
                });
        }

        $paginatedUsers = $query->orderBy('first_name', 'asc')->paginate($perPage);

        $sortedItems = $paginatedUsers->getCollection()->sort(function ($a, $b) {
            return strcasecmp($a->first_name, $b->first_name);
        })->values();

        $paginatedUsers->setCollection($sortedItems);

        return response()->json($paginatedUsers);
    }

    /**
     * Transforms a single User model into a JSON-friendly array.
     * This is more efficient for use with paginator's `through()` method.
     *
     * @param User $user
     * @return array
     */
    private function transformUser(User $user): array
    {
        $domains = $user->domains->map(function (Domain $domain) {
            return [
                'id' => $domain->id,
                'name' => $domain->name,
                'url' => $domain->url,
            ];
        })->toArray();

        return array_merge($user->toArray(), ['domains' => $domains]);
    }

    // GET User by UUID
    public function show(string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'user' => $user,
        ]);
    }

    /* POST users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['password'] = $request->password;

        if ($request->has('is_approved') && $request->boolean('is_approved')) {
            $payload['is_approved'] = true;
        }

        // Handle user_origin if provided
        if ($request->has('user_origin') && $request->user_origin) {
            $payload['user_origin'] = $request->user_origin;
        }

        // Special handling for site_access_info users with Admin role
        if (
            $request->has('user_origin') && $request->user_origin === 'site_access_info' &&
            $request->has('role') && $request->role === 'admin'
        ) {
            // Set external_role to Admin and role to User for site_access_info users
            $payload['external_role'] = 'Admin';
            $payload['role'] = 'user';
        }

        $user = User::create($payload);

        return response()->json($user, 201);
    }

    /* PUT users */
    public function update(UpdateUserRequest $request, string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        // Initialize $data with basic user fields
        $data = $request->only(['first_name', 'last_name', 'email']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('user_images', 'public');
            $data['image_url'] = $path;
        }

        // Special handling for site_access_info users
        if ($user->user_origin === 'site_access_info' && $request->has('role')) {
            if ($request->role === 'admin') {
                // Set external_role to Admin and role to User for site_access_info users
                $data['external_role'] = 'Admin';
                $data['role'] = 'user';
            } elseif ($request->role === 'user') {
                // Set external_role to User for site_access_info users
                $data['external_role'] = 'User';
            }
        }

        $user->update($data);

        // Dispatch job to sync with other apps
        SyncUserUpdateJob::dispatch($user->uuid);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user,
        ]);
    }

    // POST users image upload
    public function updateImage(Request $request, string $uuid): JsonResponse
    {

        $user = User::where('uuid', $uuid)->firstOrFail();

        // Validate image file
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120', // 5MB max
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:255'
        ]);

        $data = $request->only(['first_name', 'last_name', 'email']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('user_images', 'public');
            $data['image_url'] = $path;
        }

        $user->update($data);

        // Dispatch job to sync with other apps
        SyncUserUpdateJob::dispatch($user->uuid);

        return response()->json([
            'message' => 'User image updated successfully.',
            'user' => $user,
        ]);
    }


    // PATCH users status
    public function toggleStatus(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'uuid|exists:users,uuid',
            'status'    => 'required|in:active,inactive,suspended', // extendable
        ]);

        $status = $request->input('status');
        $userIds = $request->input('user_ids');

        DB::beginTransaction();

        try {
            $updatedUsers = User::whereIn('uuid', $userIds)->get();

            foreach ($updatedUsers as $user) {
                if ($user->status !== $status) {
                    $user->update(['status' => $status]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User statuses updated successfully.',
                'data' => $updatedUsers->map(fn($user) => [
                    'uuid'   => $user->uuid,
                    'status' => $user->status,
                ]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update user statuses.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* POST reset-password */
    public function resetPassword(User $user): JsonResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return response()->json(['message' => 'Password reset email sent']);
    }

    // Delete user
    public function destroy($uuid): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::where('uuid', $uuid)->with('domains:key')->firstOrFail();

            $domainKeys = $user->domains->pluck('key')->toArray();

            // Permanently delete the user from AuthCenter.
            $user->forceDelete();

            // Dispatch a job to propagate the deletion to other linked services.
            if (!empty($domainKeys)) {
                SyncUserDeletionJob::dispatch($uuid, $domainKeys);
            }

            DB::commit();

            return response()->json([
                'message' => 'User permanently deleted and deletion sync job dispatched.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user', [
                'user_id' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting the user.'
            ], 500);
        }
    }

    // POST change-password
    public function changePassword(ChangeUserPasswordRequest $request, string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $user->password = Hash::make($request->password);
        $user->save();

        $syncAll = $user->role === 'admin';

        SyncUserUpdateJob::dispatch($user->uuid, $syncAll);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    // Users search
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('query');
        $status = $request->query('status');
        $pending = $request->query('pending');
        $authUser = Auth::user();

        $usersQuery = User::query()
            ->when($pending === 'true', function ($q) {
                $q->where('is_approved', false);
            }, function ($q) {
                $q->where('is_approved', true);
            })
            ->where('id', '!=', $authUser->id) // Exclude authenticated user's own record
            ->with('domains:id,name,url');

        // Check if authenticated user is site access info admin
        if ($authUser->user_origin === 'site_access_info' && $authUser->external_role === 'Admin') {
            // Site access info admin: only show site_access_info users
            $usersQuery->where('user_origin', 'site_access_info');
        } else {
            // Traditional admin: exclude admin role and jobfinder users
            $usersQuery->where('role', '!=', 'admin')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('user_origin', '<>', 'jobfinder')
                            ->orWhere(function ($subSubQ) {
                                $subSubQ->where('user_origin', 'jobfinder')
                                    ->where('external_role', 'Admin');
                            });
                    });
                });
        }
        // Apply search if query parameter exists
        if ($query) {
            $usersQuery->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        // Apply status filter if provided and not 'all'
        if ($status && $status !== 'all') {
            $usersQuery->where('status', $status);
        }

        $users = $usersQuery
            ->orderBy('first_name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role' => $user->role,
                    'user_origin' => $user->user_origin,
                    'is_approved' => $user->is_approved,
                    'locked_until' => $user->locked_until,
                    'created_at' => $user->created_at,
                    'domains' => $user->domains->map(function ($domain) {
                        return [
                            'id' => $domain->id,
                            'name' => $domain->name,
                            'url' => $domain->url
                        ];
                    })
                ];
            });

        if ($users->isEmpty()) {
            return response()->json([]);
        }

        return response()->json($users);
    }

    // Users filtered
    public function filtered(Request $request)
    {
        $authUser = Auth::user();
        $perPage = $request->get('per_page', 15);

        $query = User::query()
            ->where('is_approved', true)
            ->where('id', '!=', $authUser->id); // Exclude authenticated user's own record

        // Check if authenticated user is site access info admin
        if ($authUser->user_origin === 'site_access_info' && $authUser->external_role === 'Admin') {
            // Site access info admin: only show site_access_info users
            $query->where('user_origin', 'site_access_info');
        } else {
            // Traditional admin: exclude admin role
            $query->where('role', '!=', 'admin');
        }

        $domain = $request->input('domain');
        $status = $request->input('status');
        $role = $request->input('role');

        if ($status === 'admin') {
            $role = $status;
            $status = null;
        }

        if ($domain && $domain !== 'all') {
            $query->whereHas('domains', function ($q) use ($domain) {
                $q->where('key', $domain);
            });
        }

        if ($status && $status !== 'all') {
            if ($status === 'locked') {
                $query->whereNotNull('locked_until');
            } else {
                $query->where('status', $status);
            }
        }

        if ($role && $role !== 'all') {
            $isExternalSearch = ($domain && $domain !== 'all');
            $roleColumn = $isExternalSearch ? 'external_role' : 'role';

            $searchValue = ($isExternalSearch && $role === 'admin') ? 'Admin' : $role;
            $query->where($roleColumn, $searchValue);
        } else {
            $query->where('role', '!=', 'admin');
        }

        // Exclude users whose user_origin is 'jobfinder', except those whose external_role is 'Admin'
        $query->where(function ($q) {
            $q->where('user_origin', '<>', 'jobfinder')
                ->orWhere(function ($q2) {
                    $q2->where('user_origin', 'jobfinder')
                        ->where('external_role', 'Admin');
                });
        });

        $paginatedUsers = $query->orderBy('first_name', 'asc')->paginate($perPage);

         $transformedItems = $paginatedUsers->getCollection()->map(function ($user) {
            return $this->transformUser($user);
        });

        $paginatedUsers->setCollection($transformedItems);

        return response()->json($paginatedUsers);
    }

    /**
     * Unlock a user account by resetting 'locked_until' and 'failed_attempts'.
     *
     * @param Request $request The incoming request.
     * @return JsonResponse
     */
    public function unlockUser(Request $request): JsonResponse
    {
        $request->validate([
            'uuids' => 'required|array|min:1',
            'uuids.*' => 'uuid|exists:users,uuid',
        ]);

        $uuids = $request->input('uuids');

        try {
            User::whereIn('uuid', $uuids)->update([
                'locked_until' => null,
                'failed_attempts' => 0,
            ]);

            return response()->json([
                'message' => 'User accounts unlocked successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unlock user accounts', [
                'user_ids' => $uuids,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to unlock user accounts.',
            ], 500);
        }
    }
}
