<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProjectAssignmentController extends Controller
{
    public function assign(Request $request)
    {
        $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $project = Project::findOrFail($request->project_id);
        $project->assignedUsers()->syncWithoutDetaching([$request->user_id]);

        return successResponse('User assigned to project', $project->load('assignedUsers'));
    }

    public function unassign(Request $request)
    {
        $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $project = Project::findOrFail($request->project_id);
        $project->assignedUsers()->detach($request->user_id);

        return successResponse('User unassigned from project', $project->load('assignedUsers'));
    }

    public function makePublic(Request $request)
    {
        $request->validate(['project_id' => 'required|uuid|exists:projects,id']);

        $project = Project::findOrFail($request->project_id);
        $project->update(['is_public_to_devs' => true]);

        return successResponse('Project is now public for all devs', $project);
    }

    public function makePrivate(Request $request)
    {
        $request->validate(['project_id' => 'required|uuid|exists:projects,id']);

        $project = Project::findOrFail($request->project_id);
        $project->update(['is_public_to_devs' => false]);

        return successResponse('Project is now private', $project);
    }
}
