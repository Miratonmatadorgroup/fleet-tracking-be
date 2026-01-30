<?php
namespace App\Services;

use App\Models\Project;
use App\Models\ApiEndpoint;
use App\Enums\ApiEndpointsMethodEnums;

class ApiEndpointService
{
    public function create(array $data): ApiEndpoint
    {
        $project = Project::findOrFail($data['project_id']);

        return ApiEndpoint::create([
            'project_id'  => $project->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'method'      => ApiEndpointsMethodEnums::from($data['method']),
            'path'        => $data['path'],
            'full_url'    => rtrim($project->base_url, '/') . $data['path'],
            'version'     => $data['version'] ?? 'v1',
            'is_active'   => $data['is_active'] ?? true,
        ]);
    }

    public function update(ApiEndpoint $endpoint, array $data): ApiEndpoint
    {
        if (isset($data['method'])) {
            $data['method'] = ApiEndpointsMethodEnums::from($data['method']);
        }

        if (isset($data['path'])) {
            $project = $endpoint->project;
            $data['full_url'] = rtrim($project->base_url, '/') . $data['path'];
        }

        $endpoint->update($data);

        return $endpoint;
    }

    public function delete(ApiEndpoint $endpoint): void
    {
        $endpoint->delete();
    }
}