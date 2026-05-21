<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SaveRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manages the org-scoped role catalogue at /settings/roles.
 *
 * System roles (is_system=true) are read-only: the UI exposes their
 * permission set for reference but the routes guard against edits.
 * Custom roles can be created, edited, or deleted freely.
 */
class RoleController extends Controller
{
    public function __construct(private PermissionRegistrar $registrar)
    {
    }

    public function index(Request $request): Response
    {
        $org = $this->currentOrgOrThrow($request);
        Gate::authorize('viewRoles', $org);

        $this->registrar->setPermissionsTeamId($org->id);

        $roles = Role::query()
            ->where('organization_id', $org->id)
            ->with('permissions')
            ->orderByDesc('level')
            ->orderBy('name')
            ->withCount('users')
            ->get()
            ->map(fn (Role $r) => $this->rolePayload($r));

        return Inertia::render('settings/roles/index', [
            'roles' => $roles,
            'permissionGroups' => $this->groupedPermissions(),
            'canManage' => $request->user()->can(PermissionEnum::RoleManage->value),
        ]);
    }

    public function show(Request $request, Role $role): Response
    {
        $org = $this->currentOrgOrThrow($request);
        Gate::authorize('viewRoles', $org);
        $this->assertRoleInOrg($role, $org);

        return Inertia::render('settings/roles/show', [
            'role' => $this->rolePayload($role->load('permissions')->loadCount('users')),
            'permissionGroups' => $this->groupedPermissions(),
            'canManage' => $request->user()->can(PermissionEnum::RoleManage->value),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $org = $this->currentOrgOrThrow($request);
        Gate::authorize('manageRoles', $org);

        $this->registrar->setPermissionsTeamId($org->id);

        $role = DB::transaction(function () use ($request, $org) {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $request->validated('name'),
                'organization_id' => $org->id,
                'guard_name' => 'web',
                'level' => $request->validated('level') ?? 30,
                'is_system' => false,
                'description' => $request->validated('description'),
            ]);

            $role->syncPermissions($request->validated('permissions') ?? []);

            return $role;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.show', ['role' => $role->id]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $org = $this->currentOrgOrThrow($request);
        Gate::authorize('manageRoles', $org);
        $this->assertRoleInOrg($role, $org);

        abort_if((bool) $role->is_system, 403, __('System roles cannot be edited.'));

        $this->registrar->setPermissionsTeamId($org->id);

        DB::transaction(function () use ($request, $role) {
            $role->update([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'level' => $request->validated('level') ?? $role->level,
            ]);

            $role->syncPermissions($request->validated('permissions') ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $org = $this->currentOrgOrThrow($request);
        Gate::authorize('manageRoles', $org);
        $this->assertRoleInOrg($role, $org);

        abort_if((bool) $role->is_system, 403, __('System roles cannot be deleted.'));
        abort_if($role->users()->exists(), 422, __('Reassign members before deleting this role.'));

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    private function currentOrgOrThrow(Request $request): \App\Models\Organization
    {
        $org = $request->user()?->currentOrganization;
        abort_if($org === null, 404);

        return $org;
    }

    private function assertRoleInOrg(Role $role, \App\Models\Organization $org): void
    {
        abort_unless((int) $role->organization_id === $org->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'level' => (int) $role->level,
            'isSystem' => (bool) $role->is_system,
            'memberCount' => (int) ($role->users_count ?? $role->users()->count()),
            'permissions' => $role->permissions->pluck('name')->values(),
        ];
    }

    /**
     * Permission catalogue grouped by resource bucket for the UI.
     *
     * @return array<int, array{group: string, items: array<int, array{value: string, label: string}>}>
     */
    private function groupedPermissions(): array
    {
        return collect(PermissionEnum::cases())
            ->groupBy(fn (PermissionEnum $p) => $p->group())
            ->map(fn ($cases, $group) => [
                'group' => $group,
                'items' => collect($cases)
                    ->map(fn (PermissionEnum $p) => [
                        'value' => $p->value,
                        'label' => $p->label(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
