<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert die OpenAPI-Spezifikation und die Swagger-UI für Admins.
 * Die Spec selber liegt als hand-curiertes YAML in resources/api-docs/openapi.yaml
 * und beschreibt sowohl die eingehende REST-API als auch die Payloads, die
 * OWE nach extern sendet (Webhooks + HTTP-Knoten).
 */
class ApiDocsController extends Controller
{
    public function index(): View
    {
        return view('admin.api-docs.index');
    }

    public function spec(): BinaryFileResponse|Response
    {
        $path = base_path('resources/api-docs/openapi.yaml');
        if (! is_file($path)) {
            return response('OpenAPI-Spec nicht gefunden.', 500);
        }
        return response()->file($path, ['Content-Type' => 'application/yaml']);
    }
}
