<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berechtigungs-Report: Welcher User in welcher Rolle, welche
 * Berechtigung hat diese Rolle? Drei Output-Formate:
 *  - HTML (auf der Seite anschauen)
 *  - CSV (Excel-Import, Filtern)
 *  - PDF (Audit, Archivierung mit Hash der Datei)
 */
class PermissionsReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('admin.reports.permissions', $this->loadData());
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->audit->log('report.permissions.exported', null, null, ['format' => 'csv'],
            'Berechtigungs-Report als CSV exportiert', $request->user()->id);

        $rows = $this->flatRows();
        $filename = 'berechtigungen-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputs($out, "\xEF\xBB\xBF"); // BOM fuer Excel/Umlaute
            fputcsv($out, ['User', 'E-Mail', 'Aktiv', 'Service-Account', 'Rolle', 'Permission-Slug', 'Permission-Name', 'Gruppe'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['user_name'], $r['user_email'], $r['user_active'] ? 'ja' : 'nein',
                    $r['user_service'] ? 'ja' : 'nein',
                    $r['role'], $r['permission_slug'], $r['permission_name'], $r['permission_group'],
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->loadData();
        $data['generatedAt'] = now();
        $data['generator'] = $request->user()->name;
        $this->audit->log('report.permissions.exported', null, null, ['format' => 'pdf'],
            'Berechtigungs-Report als PDF exportiert', $request->user()->id);

        $pdf = Pdf::loadView('admin.reports.permissions_pdf', $data)
            ->setPaper('a4', 'portrait');
        return $pdf->download('berechtigungen-' . now()->format('Ymd-Hi') . '.pdf');
    }

    /**
     * @return array{users: \Illuminate\Support\Collection<int, User>, roles: \Illuminate\Support\Collection<int, Role>, totalPermissions: int}
     */
    private function loadData(): array
    {
        $users = User::query()
            ->with(['roles.permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->orderBy('name')->get();

        $roles = Role::query()
            ->with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')->get();

        $totalPermissions = Permission::count();

        return compact('users', 'roles', 'totalPermissions');
    }

    /**
     * Flache Tabelle User x Rolle x Permission — eine Zeile pro Tripel,
     * fuer CSV-Export und maschinelle Weiterverarbeitung.
     */
    private function flatRows(): Collection
    {
        $rows = collect();
        $users = User::query()->with('roles.permissions')->orderBy('name')->get();

        foreach ($users as $u) {
            if ($u->roles->isEmpty()) {
                $rows->push([
                    'user_name' => $u->name,
                    'user_email' => $u->email,
                    'user_active' => (bool) $u->is_active,
                    'user_service' => (bool) $u->is_service_account,
                    'role' => '— keine Rolle —',
                    'permission_slug' => '',
                    'permission_name' => '',
                    'permission_group' => '',
                ]);
                continue;
            }
            foreach ($u->roles as $r) {
                if ($r->permissions->isEmpty()) {
                    $rows->push([
                        'user_name' => $u->name, 'user_email' => $u->email,
                        'user_active' => (bool) $u->is_active, 'user_service' => (bool) $u->is_service_account,
                        'role' => $r->name,
                        'permission_slug' => '', 'permission_name' => '— keine Permissions —', 'permission_group' => '',
                    ]);
                    continue;
                }
                foreach ($r->permissions as $p) {
                    $rows->push([
                        'user_name' => $u->name, 'user_email' => $u->email,
                        'user_active' => (bool) $u->is_active, 'user_service' => (bool) $u->is_service_account,
                        'role' => $r->name,
                        'permission_slug' => $p->slug,
                        'permission_name' => $p->name,
                        'permission_group' => $p->group ?? '',
                    ]);
                }
            }
        }
        return $rows;
    }
}
