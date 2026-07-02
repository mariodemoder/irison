<?php

namespace App\Http\Controllers\Api;

use App\Exports\ClinicBackupExport;
use App\Exports\XlsxWriter;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionBackupController extends Controller
{
    public function download(): StreamedResponse
    {
        $clinicId = currentClinicId();
        $clinic = Clinic::findOrFail($clinicId);

        $tempPath = tempnam(sys_get_temp_dir(), 'backup_') . '.xlsx';

        try {
            $writer = new XlsxWriter;
            $export = new ClinicBackupExport($clinic->id);
            $export->build($writer);
            $writer->save($tempPath);

            return response()->streamDownload(function () use ($tempPath) {
                readfile($tempPath);
                unlink($tempPath);
            }, 'backup-clinica.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }
    }
}
