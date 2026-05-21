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

        $data = $this->loadData();
        $filename = 'berechtigungen-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'wb');
            fputs($out, "\xEF\xBB\xBF"); // BOM fuer Excel/Umlaute

            // Abschnitt 1: User -> Rollen
            fputcsv($out, ['Abschnitt 1: Benutzer und Rollen'], ';');
            fputcsv($out, ['User', 'E-Mail', 'Status', 'Rollen'], ';');
            foreach ($data['users'] as $u) {
                $status = $u->is_service_account ? 'Service' : (! $u->is_active ? 'inaktiv' : 'aktiv');
                $roles = $u->roles->pluck('name')->join(', ');
                fputcsv($out, [$u->name, $u->email, $status, $roles ?: '—'], ';');
            }
            fputcsv($out, [], ';'); // Leerzeile

            // Abschnitt 2: Rollen -> Permissions
            fputcsv($out, ['Abschnitt 2: Rollen und Permissions'], ';');
            fputcsv($out, ['Rolle', 'Slug', 'User-Anzahl', 'Permission-Gruppe', 'Permission-Slug', 'Permission-Name'], ';');
            foreach ($data['roles'] as $r) {
                if ($r->permissions->isEmpty()) {
                    fputcsv($out, [$r->name, $r->slug, $r->users_count, '—', '', '— keine Permissions —'], ';');
                    continue;
                }
                foreach ($r->permissions as $p) {
                    fputcsv($out, [$r->name, $r->slug, $r->users_count, $p->group ?? '', $p->slug, $p->name], ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->loadData();
        $data['generatedAt'] = now();
        $data['generator'] = $request->user()->name;
        // SHA-256-Fingerprint ueber die Daten — fuer Audit-Trailer im PDF.
        $data['dataHash'] = substr(hash('sha256', json_encode([
            'users' => $data['users']->map(fn ($u) => [
                'id' => $u->id, 'email' => $u->email,
                'roles' => $u->roles->pluck('slug')->all(),
            ])->all(),
            'roles' => $data['roles']->map(fn ($r) => [
                'slug' => $r->slug,
                'permissions' => $r->permissions->pluck('slug')->all(),
            ])->all(),
            'at' => $data['generatedAt']->toIso8601String(),
        ])), 0, 16) . '…';

        $this->audit->log('report.permissions.exported', null, null,
            ['format' => 'pdf', 'data_hash' => $data['dataHash']],
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

}
