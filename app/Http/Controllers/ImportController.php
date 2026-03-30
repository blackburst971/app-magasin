<?php

namespace App\Http\Controllers;

use App\Services\SnipeItImportService;
use App\Services\LicenseImportService;
use App\Services\ManufacturerImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Import/Index');
    }

    /* ── Équipements (Snipe-IT hardware) ─────────────────────────────────── */

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:51200']);

        $service = new SnipeItImportService();
        $result  = $service->preview($request->file('file'));

        return response()->json($result);
    }

    public function process(Request $request)
    {
        $request->validate([
            'file'    => 'required|file|mimes:csv,txt|max:51200',
            'mapping' => 'required|array',
        ]);

        $service = new SnipeItImportService();
        $result  = $service->import($request->file('file'), $request->mapping);

        return response()->json($result);
    }

    /* ── Licences (Snipe-IT licenses export) ─────────────────────────────── */

    public function previewLicenses(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $service = new LicenseImportService();
        $result  = $service->preview($request->file('file'));

        return response()->json($result);
    }

    public function processLicenses(Request $request)
    {
        $request->validate([
            'file'    => 'required|file|mimes:csv,txt|max:10240',
            'mapping' => 'required|array',
        ]);

        $service = new LicenseImportService();
        $result  = $service->import($request->file('file'), $request->mapping);

        return response()->json($result);
    }

    /* ── Fabricants ─────────────────────────────────────────────────────────── */

    public function previewManufacturers(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $service = new ManufacturerImportService();
        $result  = $service->preview($request->file('file'));

        return response()->json($result);
    }

    public function processManufacturers(Request $request)
    {
        $request->validate([
            'file'    => 'required|file|mimes:csv,txt|max:10240',
            'mapping' => 'required|array',
        ]);

        $service = new ManufacturerImportService();
        $result  = $service->import($request->file('file'), $request->mapping);

        return response()->json($result);
    }
}
