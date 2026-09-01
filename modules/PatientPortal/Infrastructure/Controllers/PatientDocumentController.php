<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientDocumentService;

class PatientDocumentController extends Controller
{
    public function __construct(
        private PatientDocumentService $documentService
    ) {}

    public function index(Request $request)
    {
        $documents = $this->documentService->index($request->user());

        return response()->json(['documents' => $documents]);
    }

    public function show(Request $request, int $id)
    {
        $document = $this->documentService->show($request->user(), $id);

        return response()->json(['document' => $document]);
    }

    public function download(Request $request, int $id)
    {
        $document = $this->documentService->show($request->user(), $id);

        return $this->documentService->download(
            $request->user(),
            $document,
            $request->ip(),
            $request->userAgent()
        );
    }
}
