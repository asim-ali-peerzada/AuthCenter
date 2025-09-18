<?php

namespace App\Http\Controllers\AccessRequest;

use App\Http\Requests\AccessRequest\AccessRequestStoreRequest;
use App\Models\AccessRequest;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class AccessRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Use policy to authorize
        Gate::authorize('viewAny', AccessRequest::class);

        // Build query based on user role
        $query = AccessRequest::query();

        // Non-admin users can only see their own requests
        if (!($user->role === 'admin')) {
            $query->where('user_uuid', $user->uuid);
        }

        // Apply filters
        $status = $request->input('status');
        $requestType = $request->input('request_type');
        $domainId = $request->input('domain_id');

        // Filter by status (Pending, Approved, Rejected)
        if ($status && $status !== '') {
            $query->where('status', $status);
        }

        // Filter by request type (Access Request, Activation Request)
        if ($requestType && $requestType !== '') {
            $query->where('request_type', $requestType);
        }

        // Filter by domain
        if ($domainId && $domainId !== '') {
            $query->where('domain_id', $domainId);
        }

        $requests = $query->with(['user', 'domain'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        $response = $requests->toArray();

        $response['un_approved_request_count'] = AccessRequest::where('status', 'pending')->count();

        return response()->json($response);
    }

    /**
     * Search and filter access requests for admin
     */
    public function search(Request $request)
    {
        $user = $request->user();

        // Only admins can use this search endpoint
        if (!($user->role === 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Build query with relationships
        $query = AccessRequest::query()->with(['user', 'domain']);

        // Search filters
        $searchTerm = $request->input('search');
        $status = $request->input('status');
        $domainId = $request->input('domain_id');
        $requestType = $request->input('request_type');

        // Apply search across multiple fields
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                // Search in user's first name, last name, and email
                $q->whereHas('user', function ($userQuery) use ($searchTerm) {
                    $userQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                })
                    // Search in domain name
                    ->orWhereHas('domain', function ($domainQuery) use ($searchTerm) {
                        $domainQuery->where('name', 'LIKE', "%{$searchTerm}%");
                    })
                    // Search in domain_name column (fallback)
                    ->orWhere('domain_name', 'LIKE', "%{$searchTerm}%")
                    // Search in message
                    ->orWhere('message', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by status (Pending, Approved, Rejected)
        if ($status && $status !== '') {
            $query->where('status', $status);
        }

        // Filter by request type (Access Request, Activation Request)
        if ($requestType && $requestType !== '') {
            $query->where('request_type', $requestType);
        }

        // Filter by domain
        if ($domainId && $domainId !== '') {
            $query->where('domain_id', $domainId);
        }

        // Get paginated results
        $requests = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($requests);
    }

    public function store(AccessRequestStoreRequest $request)
    {
        $authUser = $request->user();

        // Use policy to authorize
        Gate::authorize('create', AccessRequest::class);

        $validated = $request->validated();
        $domain = Domain::find($validated['domain_id']);

        if (!$domain) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        // Check if user already has access to this domain
        if ($authUser->domains()->where('domain_id', $validated['domain_id'])->exists()) {
            return response()->json(['message' => 'You already have access to this domain'], 409);
        }

        // Prefer authenticated user identity for consistency
        $userUuid = $authUser->uuid;
        $userId = $authUser->id;

        // Check for existing pending request (additional validation)
        $existing = AccessRequest::where('user_uuid', $userUuid)
            ->where('domain_id', $validated['domain_id'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Request already pending',
                'request_id' => $existing->id,
                'created_at' => $existing->created_at
            ], 409);
        }

        // Check for rate limiting (max 10 pending requests per user)
        $pendingCount = AccessRequest::where('user_uuid', $userUuid)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= 10) {
            return response()->json(['message' => 'You have reached the maximum number of pending requests'], 429);
        }

        try {
            $requestModel = AccessRequest::create([
                'user_uuid' => $userUuid,
                'user_id' => $userId,
                'domain_id' => $validated['domain_id'],
                'domain_name' => $domain->name ?? ($validated['domain_name'] ?? null),
                'request_type' => 'access', // Always 'access' for this endpoint
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Access request submitted successfully',
                'request' => $requestModel->load(['user', 'domain'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create access request', [
                'error' => $e->getMessage(),
                'user_uuid' => $userUuid,
                'domain_id' => $validated['domain_id']
            ]);

            return response()->json(['message' => 'Failed to submit access request'], 500);
        }
    }

    /**
     * Store activation request for inactive users
     */
    public function storeActivation(AccessRequestStoreRequest $request)
    {
        $authUser = $request->user();

        // Use policy to authorize
        Gate::authorize('create', AccessRequest::class);

        $validated = $request->validated();
        $domain = Domain::find($validated['domain_id']);

        if (!$domain) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        // For activation requests, user should already have access to the domain
        // but their account is inactive on that domain
        if (!$authUser->domains()->where('domain_id', $validated['domain_id'])->exists()) {
            return response()->json(['message' => 'You do not have access to this domain. Please request access first.'], 409);
        }

        // Prefer authenticated user identity for consistency
        $userUuid = $authUser->uuid;
        $userId = $authUser->id;

        // Check for existing pending activation request
        $existing = AccessRequest::where('user_uuid', $userUuid)
            ->where('domain_id', $validated['domain_id'])
            ->where('request_type', 'activation')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Activation request already pending',
                'request_id' => $existing->id,
                'created_at' => $existing->created_at
            ], 409);
        }

        // Check for rate limiting (max 10 pending requests per user)
        $pendingCount = AccessRequest::where('user_uuid', $userUuid)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= 10) {
            return response()->json(['message' => 'You have reached the maximum number of pending requests'], 429);
        }

        try {
            $requestModel = AccessRequest::create([
                'user_uuid' => $userUuid,
                'user_id' => $userId,
                'domain_id' => $validated['domain_id'],
                'domain_name' => $domain->name ?? ($validated['domain_name'] ?? null),
                'request_type' => 'activation', // Always 'activation' for this endpoint
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Activation request submitted successfully',
                'request' => $requestModel->load(['user', 'domain'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create activation request', [
                'error' => $e->getMessage(),
                'user_uuid' => $userUuid,
                'domain_id' => $validated['domain_id']
            ]);

            return response()->json(['message' => 'Failed to submit activation request'], 500);
        }
    }

    public function approve(Request $request, $accessRequestId)
    {
        $accessRequest = AccessRequest::find($accessRequestId);

        if (!$accessRequest) {
            Log::error('AccessRequest not found', [
                'requested_id' => $accessRequestId,
                'available_ids' => AccessRequest::pluck('id')->toArray()
            ]);

            return response()->json([
                'message' => 'Access request not found',
                'requested_id' => $accessRequestId,
                'available_ids' => AccessRequest::pluck('id')->toArray()
            ], 404);
        }

        // Use policy to authorize
        Gate::authorize('approve', $accessRequest);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Request already processed',
                'current_status' => $accessRequest->status
            ], 409);
        }

        try {
            $accessRequest->status = 'approved';
            $accessRequest->acted_by = Auth::id();
            $accessRequest->acted_at = now();
            $accessRequest->save();

            // Attach domain to user if exists
            if ($accessRequest->user_id) {
                $user = User::find($accessRequest->user_id);
                if ($user) {
                    $user->domains()->syncWithoutDetaching([$accessRequest->domain_id]);

                    // Special logic for solucomp sub-domains
                    $domain = $accessRequest->domain;
                    if (in_array($domain->key, ['solucomp_cop', 'solucomp_compare'])) {
                        $mainDomain = Domain::where('key', 'solucomp')->first();
                        if ($mainDomain) {
                            $user->domains()->syncWithoutDetaching([$mainDomain->id]);
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Request approved successfully',
                'request' => $accessRequest->load(['user', 'domain'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve access request', [
                'error' => $e->getMessage(),
                'request_id' => $accessRequest->id
            ]);

            return response()->json(['message' => 'Failed to approve request'], 500);
        }
    }

    public function reject(Request $request, $accessRequestId)
    {
        // Manually find the access request
        $accessRequest = AccessRequest::find($accessRequestId);

        if (!$accessRequest) {
            return response()->json([
                'message' => 'Access request not found',
                'requested_id' => $accessRequestId,
                'available_ids' => AccessRequest::pluck('id')->toArray()
            ], 404);
        }

        // Use policy to authorize
        Gate::authorize('reject', $accessRequest);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Request already processed',
                'current_status' => $accessRequest->status
            ], 409);
        }

        try {
            $accessRequest->status = 'rejected';
            $accessRequest->acted_by = Auth::id();
            $accessRequest->acted_at = now();
            $accessRequest->save();

            return response()->json([
                'message' => 'Request rejected successfully',
                'request' => $accessRequest->load(['user', 'domain'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject access request', [
                'error' => $e->getMessage(),
                'request_id' => $accessRequest->id
            ]);

            return response()->json(['message' => 'Failed to reject request'], 500);
        }
    }

    public function destroy(AccessRequest $accessRequest)
    {
        // Use policy to authorize
        Gate::authorize('delete', $accessRequest);

        try {

            $accessRequest->delete();

            return response()->json([
                'message' => 'Request deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete access request', [
                'error' => $e->getMessage(),
                'request_id' => $accessRequest->id
            ]);

            return response()->json(['message' => 'Failed to delete request'], 500);
        }
    }

    /**
     * Resubmit an access request (change status back to pending)
     */
    public function resubmit(Request $request, $accessRequestId)
    {
        $accessRequest = AccessRequest::find($accessRequestId);

        if (!$accessRequest) {
            return response()->json([
                'message' => 'Access request not found',
                'requested_id' => $accessRequestId,
                'available_ids' => AccessRequest::pluck('id')->toArray()
            ], 404);
        }

        // Use policy to authorize
        Gate::authorize('resubmit', $accessRequest);

        // Only allow resubmission of rejected requests
        if ($accessRequest->status !== 'rejected') {
            return response()->json([
                'message' => 'Only rejected requests can be resubmitted',
                'current_status' => $accessRequest->status
            ], 409);
        }

        try {
            $accessRequest->status = 'pending';
            $accessRequest->acted_by = null;
            $accessRequest->acted_at = null;

            // Replace with automatic message for resubmission
            $automaticMessage = "User requested access again for this domain. Please review it. (Automatic message - " . now()->format('Y-m-d H:i:s') . ")";
            $accessRequest->message = $automaticMessage;

            $accessRequest->save();

            return response()->json([
                'message' => 'Request resubmitted successfully',
                'request' => $accessRequest->load(['user', 'domain'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resubmit access request', [
                'error' => $e->getMessage(),
                'request_id' => $accessRequest->id
            ]);

            return response()->json(['message' => 'Failed to resubmit request'], 500);
        }
    }
}
