<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GdprService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GdprController extends Controller
{
    public function __construct(private readonly GdprService $gdpr) {}

    public function index(): View
    {
        return view('admin.gdpr.index');
    }

    public function exportAccess(Request $request): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $result = $this->gdpr->exportForEmail($data['email']);

        return response()->download($result['path'], $result['filename'])
            ->deleteFileAfterSend();
    }

    public function anonymize(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
            'confirm_text' => ['required', 'string', 'in:ANONYMISIEREN'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'Kein User mit dieser Email gefunden.']);
        }
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['email' => 'Du kannst dich nicht selbst anonymisieren.']);
        }

        $result = $this->gdpr->anonymize($user, $data['reason']);

        return back()->with('status',
            'User #'.$result['user_id'].' anonymisiert. Neue Email: '.$result['anonymized_email']);
    }
}
