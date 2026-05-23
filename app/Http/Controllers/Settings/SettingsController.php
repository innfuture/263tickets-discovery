<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Base for every settings controller. Resolves the viewer's active
 * organization and guards a permission in one call, so individual
 * controller actions stay terse.
 */
abstract class SettingsController extends Controller
{
    /**
     * Active org for the viewer, or 404 if none. Optionally permission-
     * gates the call — pass the Spatie permission name, or `null` for
     * personal pages with no org-scoped check.
     */
    protected function org(Request $request, ?string $permission = null): Organization
    {
        $org = $request->user()?->currentOrganization;
        abort_if($org === null, 404);

        if ($permission !== null) {
            abort_unless($request->user()->can($permission), 403);
        }

        return $org;
    }
}
