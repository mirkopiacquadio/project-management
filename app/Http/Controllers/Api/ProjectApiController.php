<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API read-only dei progetti, consumata dal gestionale esterno (omnianextsrl).
 * Protetta dal middleware EnsureGestionaleToken (vedi routes/api.php).
 */
class ProjectApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with('members')
            ->orderBy('name')
            ->paginate(50);

        return ProjectResource::collection($projects);
    }

    public function show(Project $project): ProjectResource
    {
        $project->load('members');

        return new ProjectResource($project);
    }
}
