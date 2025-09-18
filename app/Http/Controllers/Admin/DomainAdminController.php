<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDomainRequest;
use App\Http\Requests\Admin\UpdateDomainRequest;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DomainAdminController extends Controller
{
    /* GET domains */
    public function index(): JsonResponse
    {
        $domains = Domain::where('key', '!=', 'solucomp')->get();
        return response()->json($domains);
    }

    public function show(Domain $domain): JsonResponse
    {
        // Return domain object with appended image_full_url attribute
        $domain->makeVisible('image_full_url');

        return response()->json($domain);
    }

    /* POST domains */
    public function store(StoreDomainRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('domain_images', 'public');
        }

        $domain = Domain::create([
            'name'      => $data['name'],
            'url'       => $data['url'],
            'image_url' => $data['image_url'] ?? null,
            'detail'    => $data['detail'] ?? null,
        ]);

        return response()->json($domain, 201);
    }

    /* PUT domains */
    public function update(UpdateDomainRequest $request, Domain $domain): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($domain->image_url) {
                Storage::disk('public')->delete($domain->image_url);
            }

            $validated['image_url'] = $request->file('image')->store('domain_images', 'public');
        }

        $domain->update($validated);

        return response()->json($domain);
    }

    /* DELETE domains */
    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // For added security, verify the admin's password before a destructive action.
        if (!$user->isAdmin() || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Unauthorized. Invalid password or permissions.'], 403);
        }

        // Delete the associated image file from storage if it exists
        if ($domain->image_url) {
            Storage::disk('public')->delete($domain->image_url);
        }

        $domain->forceDelete();

        return response()->json(['message' => 'Domain permanently deleted.']);
    }

    /* GET domains for a user */
    public function user_domains(string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $domainIds = $user->domains()
            ->select('domains.id')
            ->pluck('domains.id');

        $domains = Domain::whereIn('id', $domainIds)
            ->select('id', 'name', 'url', 'image_url', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'User domains retrieved successfully',
            'data' => $domains,
        ], Response::HTTP_OK);
    }
}
