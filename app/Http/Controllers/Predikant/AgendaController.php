<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Agenda\AgendaIcalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaIcalService $agendaIcalService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->ensureAgendaToken();

        return response()->json([
            'data' => $this->subscribePayload($token, $user->agenda_token_created_at?->toIso8601String()),
        ]);
    }

    public function regenerate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->regenerateAgendaToken();

        return response()->json([
            'data' => $this->subscribePayload($token, $user->fresh()->agenda_token_created_at?->toIso8601String()),
            'message' => __('api.predikant.agenda_link_refreshed'),
        ]);
    }

    public function feed(string $token): Response
    {
        $user = User::query()
            ->where('agenda_token', $token)
            ->where('active', true)
            ->first();

        if ($user === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $ics = $this->agendaIcalService->buildForUser($user);
        $etag = $this->agendaIcalService->etagForUser($user);

        if (request()->header('If-None-Match') === $etag) {
            return response('', HttpResponse::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($ics, HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="preekrooster.ics"',
            'ETag' => $etag,
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array{subscribe_url: string, webcal_url: string, token_created_at: string|null}
     */
    private function subscribePayload(string $token, ?string $createdAt): array
    {
        $httpsUrl = url('/api/agenda/'.$token.'.ics');
        $webcalUrl = preg_replace('#^https?://#', 'webcal://', $httpsUrl) ?? $httpsUrl;

        return [
            'subscribe_url' => $httpsUrl,
            'webcal_url' => $webcalUrl,
            'token_created_at' => $createdAt,
        ];
    }
}
