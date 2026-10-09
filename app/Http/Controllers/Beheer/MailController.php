<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Mail\TestMailDiagnostics;
use App\Services\Mail\MailDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailController extends Controller
{
    public function __construct(
        private readonly MailDiagnosticsService $mailDiagnosticsService
    ) {}

    public function verstuurRooster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
        ]);

        $exitCode = Artisan::call('rooster:mail-versturen', [
            'periode' => $data['periode'],
        ]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            Log::error('Roostermail versturen mislukt.', [
                'periode' => $data['periode'],
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            return response()->json([
                'message' => "Versturen van rooster voor {$data['periode']} mislukt.",
            ], 500);
        }

        return response()->json([
            'message' => "Rooster voor {$data['periode']} verstuurd.",
        ]);
    }

    public function diagnostics(): JsonResponse
    {
        return response()->json([
            'data' => $this->mailDiagnosticsService->run(),
        ]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $mode = $this->mailDiagnosticsService->resolveDeliveryMode();
        $mailable = new TestMailDiagnostics(app()->environment());

        try {
            if ($mode === 'queue') {
                Mail::to($data['email'])->queue($mailable);
            } else {
                Mail::to($data['email'])->send($mailable);
            }
        } catch (Throwable $throwable) {
            Log::error('Testmail versturen mislukt.', [
                'email' => $data['email'],
                'mode' => $mode,
                'exception' => $throwable,
            ]);

            return response()->json([
                'message' => __('api.beheer.test_mail_failed'),
                'data' => [
                    'mode' => $mode,
                ],
            ], 500);
        }

        return response()->json([
            'message' => __('api.beheer.test_mail_sent'),
            'data' => [
                'mode' => $mode,
                'email' => $data['email'],
            ],
        ]);
    }
}
