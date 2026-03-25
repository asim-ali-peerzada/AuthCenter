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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;


class UserAdminController extends Controller
{
    /**
     * GET Users with pagination and role-based filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getValidatedPerPage($request);
        $authUser = Auth::user();

        $users = $this->buildUserQuery($authUser)
            ->paginate($perPage);

        return response()->json(
            $this->sortByFirstName($users)
        );
    }

    /**
     * Validate and return per_page parameter.
     */
    private function getValidatedPerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 15);

        return min(max($perPage, 1), 100); // Clamp between 1-100
    }

    /**
     * Build the base query with all filters applied.
     */
    private function buildUserQuery(User $authUser): Builder
    {
        $query = User::with('domains:id,name,url')
            ->where('is_approved', true)
            ->where('id', '!=', $authUser->id)
            ->orderBy('first_name', 'asc');

        $this->applyRoleBasedFilters($query, $authUser);

        return $query;
    }

    /**
     * Apply filters based on authenticated user's role and origin.
     */
    private function applyRoleBasedFilters(Builder $query, User $authUser): void
    {
        if ($this->isSiteAccessAdmin($authUser)) {
            $query->where('user_origin', 'site_access_info');
            return;
        }

        $query->where('role', '!=', 'admin')
            ->where(function (Builder $subQuery) {
                $subQuery->where('user_origin', '!=', 'jobfinder')
                    ->orWhere('external_role', 'Admin');
            });
    }

    /**
     * Check if user is a site_access_info Admin.
     */
    private function isSiteAccessAdmin(User $user): bool
    {
        return $user->user_origin === 'site_access_info'
            && $user->external_role === 'Admin';
    }

    /**
     * Case-insensitive sort by first name on paginated collection.
     */
    private function sortByFirstName(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $sorted = $paginator->getCollection()
            ->sortBy('first_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $paginator->setCollection($sorted);

        return $paginator;
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

    /**
     * Search users with filtering and role-based access control.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $authUser = Auth::user();

        $users = $this->buildSearchQuery($request, $authUser)
            ->orderBy('first_name', 'asc')
            ->get()
            ->map(fn($user) => $this->formatUserResponse($user));

        return response()->json($users);
    }

    /**
     * Build the search query with all filters applied.
     */
    private function buildSearchQuery(Request $request, User $authUser): Builder
    {
        $query = User::query()
            ->where('is_approved', $request->query('pending') === 'true' ? false : true)
            ->where('id', '!=', $authUser->id)
            ->with('domains:id,name,url');

        $this->applyRoleBasedFilters($query, $authUser);
        $this->applySearchFilter($query, $request->query('query'));
        $this->applyStatusFilter($query, $request->query('status'));

        return $query;
    }

    /**
     * Apply search filter to name and email.
     */
    private function applySearchFilter(Builder $query, ?string $search): void
    {
        if (empty($search)) {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Apply status filter if provided.
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
    }

    /**
     * Format user model for JSON response.
     */
    private function formatUserResponse(User $user): array
    {
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
            'domains' => $user->domains->map(fn($domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
                'url' => $domain->url,
            ]),
        ];
    }

    /**
     * Get filtered users with pagination and role-based access control.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filtered(Request $request): JsonResponse
    {
        $perPage = $this->getValidatedPerPage($request);
        $authUser = Auth::user();

        $users = $this->buildFilteredQuery($request, $authUser)
            ->orderBy('first_name', 'asc')
            ->paginate($perPage);

        return response()->json(
            $this->transformPaginatedUsers($users)
        );
    }

    /**
     * Build the filtered query with all conditions applied.
     */
    private function buildFilteredQuery(Request $request, User $authUser): Builder
    {
        $query = User::query()
            ->where('is_approved', true)
            ->where('id', '!=', $authUser->id)
            ->with('domains:id,name,url');

        $this->applyFilteredRoleBasedFilters($query, $authUser);
        $this->applyFilteredDomainFilter($query, $request->input('domain'));
        $this->applyFilteredStatusFilter($query, $request->input('status'));
        $this->applyFilteredRoleFilter($query, $request->input('role'), $request->input('domain'));

        return $query;
    }

    /**
     * Apply role-based access control filters for filtered endpoint.
     */
    private function applyFilteredRoleBasedFilters(Builder $query, User $authUser): void
    {
        if ($this->isSiteAccessAdminFiltered($authUser)) {
            $query->where('user_origin', 'site_access_info');
            return;
        }

        $query->where('role', '!=', 'admin');
    }

    /**
     * Check if user is a site_access_info Admin for filtered endpoint.
     */
    private function isSiteAccessAdminFiltered(User $user): bool
    {
        return $user->user_origin === 'site_access_info'
            && $user->external_role === 'Admin';
    }

    /**
     * Apply domain filter if provided.
     */
    private function applyFilteredDomainFilter(Builder $query, ?string $domain): void
    {
        if (empty($domain) || $domain === 'all') {
            return;
        }

        $query->whereHas('domains', fn(Builder $q) => $q->where('key', $domain));
    }

    /**
     * Apply status filter if provided.
     */
    private function applyFilteredStatusFilter(Builder $query, ?string $status): void
    {
        if (empty($status) || $status === 'all') {
            return;
        }

        if ($status === 'locked') {
            $query->whereNotNull('locked_until');
            return;
        }

        $query->where('status', $status);
    }

    /**
     * Apply role filter with domain-aware column selection.
     */
    private function applyFilteredRoleFilter(Builder $query, ?string $role, ?string $domain): void
    {
        // Handle legacy 'admin' status parameter
        if ($role === 'admin') {
            $role = 'Admin';
        }

        $isExternalSearch = !empty($domain) && $domain !== 'all';
        $roleColumn = $isExternalSearch ? 'external_role' : 'role';
        $searchValue = $isExternalSearch && $role === 'admin' ? 'Admin' : $role;

        if (!empty($searchValue) && $searchValue !== 'all') {
            $query->where($roleColumn, $searchValue);
            return;
        }

        // Default: exclude admins
        $query->where('role', '!=', 'admin');
    }

    /**
     * Transform paginated users for response.
     */
    private function transformPaginatedUsers(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $transformed = $paginator->getCollection()->map(
            fn($user) => $this->transformFilteredUser($user)
        );

        $paginator->setCollection($transformed);

        return $paginator;
    }

    /**
     * Transform user model for JSON response.
     */
    private function transformFilteredUser(User $user): array
    {
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
            'domains' => $user->domains->map(fn($domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
                'url' => $domain->url,
            ]),
        ];
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
