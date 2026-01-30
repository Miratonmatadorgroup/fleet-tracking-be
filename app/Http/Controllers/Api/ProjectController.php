<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * List all projects with endpoint count
     */
    // public function index()
    // {
    //     $projects = Project::withCount('endpoints')->get();

    //     return successResponse('Projects fetched', $projects);
    // }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $projects = Project::withCount('endpoints')->get();
        } elseif ($user->hasRole('dev')) {
            // Dev sees:
            // 1. Projects assigned specifically to them
            // 2. Projects marked public to all devs
            $projects = Project::withCount('endpoints')
                ->where('is_public_to_devs', true)
                ->orWhereHas('assignedUsers', fn($q) => $q->where('user_id', $user->id))
                ->get();
        } else {
            return failureResponse('Unauthorized', 403);
        }

        return successResponse('Projects fetched', $projects);
    }


    /**
     * Create a new project
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string',
            'staging_base_url'    => 'required|url',
            'live_base_url'    => 'required|url',
            'description' => 'nullable|string',
        ]);

        $project = Project::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return successResponse('Project created', $project);
    }

    /**
     * Show a single project by UUID
     */
    // public function show(Project $project)
    // {
    //     $project->load('endpoints');

    //     return successResponse('Project details', $project);
    // }

    // public function show(Project $project)
    // {
    //     $project->load([
    //         'endpoints.requests',
    //         'endpoints.headers',
    //         'endpoints.responses',
    //         'endpoints.auth',
    //     ]);

    //     return successResponse(
    //         'Project documentation fetched',
    //         $project
    //     );
    // }

    public function show(Project $project, Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            // Admin sees all
        } elseif ($user->hasRole('dev')) {
            // Dev sees the project if:
            // - It's public to devs
            // - OR assigned to them
            if (!$project->is_public_to_devs && !$user->assignedProjects()->where('id', $project->id)->exists()) {
                return failureResponse('Unauthorized', 403);
            }
        } else {
            return failureResponse('Unauthorized', 403);
        }

        $project->load([
            'endpoints.requests',
            'endpoints.headers',
            'endpoints.responses',
            'endpoints.auth',
        ]);

        return successResponse('Project documentation fetched', $project);
    }



    /**
     * Update a project by UUID
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string',
            'base_url'    => 'sometimes|url',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return successResponse('Project updated', $project);
    }

    /**
     * Delete a project by UUID
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return successResponse('Project deleted');
    }
}
