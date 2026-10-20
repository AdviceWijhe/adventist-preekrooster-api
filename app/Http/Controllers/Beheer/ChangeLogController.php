<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\ChangeLog;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChangeLogController extends Controller
{
    public function __construct(
        private readonly InstellingenService $instellingenService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $log = $this->gefilterdeQuery($request)->latest()->paginate(50);

        return response()->json($log);
    }

    /**
     * Geeft de maanden (Y-m) terug waarin daadwerkelijk changelog-vermeldingen bestaan
     * binnen de bewaartermijn, aflopend gesorteerd. Wordt gebruikt om de maandfilter
     * te beperken tot maanden met data.
     */
    public function maanden(): JsonResponse
    {
        $bewaartermijnDagen = $this->instellingenService->changelog()['bewaartermijn_dagen'];
        $vanaf = now()->subDays($bewaartermijnDagen)->startOfDay();
        $periodeExpr = $this->sqlJaarMaandExpr('created_at');

        $maanden = ChangeLog::query()
            ->where('created_at', '>=', $vanaf)
            ->selectRaw("{$periodeExpr} as periode")
            ->groupBy('periode')
            ->orderByDesc('periode')
            ->pluck('periode');

        return response()->json(['data' => $maanden]);
    }

    private function sqlJaarMaandExpr(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function legen(): JsonResponse
    {
        $verwijderd = ChangeLog::query()->delete();

        return response()->json([
            'message' => __('api.beheer.changelog_geleegd', ['aantal' => $verwijderd]),
            'data' => ['verwijderd' => $verwijderd],
        ]);
    }

    public function export(Request $request): Response
    {
        $entries = $this->gefilterdeQuery($request)->latest()->get();
        $maand = (string) $request->input('maand', '');

        $csv = $this->naarCsv($entries);
        $filename = 'changelog-'.($maand !== '' ? $maand : 'alle-maanden').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return Builder<ChangeLog>
     */
    private function gefilterdeQuery(Request $request)
    {
        $request->validate([
            'maand' => ['sometimes', 'nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $bewaartermijnDagen = $this->instellingenService->changelog()['bewaartermijn_dagen'];
        $vanaf = now()->subDays($bewaartermijnDagen)->startOfDay();

        $query = ChangeLog::query()
            ->with('user:id,voornaam,tussenvoegsel,achternaam,initialen,photo')
            ->where('created_at', '>=', $vanaf);

        $maand = (string) $request->input('maand', '');
        if ($maand !== '') {
            $start = Carbon::createFromFormat('Y-m', $maand)->startOfMonth();
            $query->whereBetween('created_at', [$start, $start->copy()->endOfMonth()]);
        }

        return $query;
    }

    /**
     * @param  Collection<int, ChangeLog>  $entries
     */
    private function naarCsv(Collection $entries): string
    {
        $lines = [$this->csvRij(['Datum/tijd', 'Gebruiker', 'Actie', 'Onderdeel'])];

        foreach ($entries as $entry) {
            $lines[] = $this->csvRij([
                $entry->created_at?->format('d-m-Y H:i') ?? '',
                $entry->user ? $entry->user->volledigeNaam() : 'Systeem',
                $entry->actie,
                $entry->model ?? '—',
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string|null>  $velden
     */
    private function csvRij(array $velden): string
    {
        return implode(';', array_map(fn (?string $veld) => $this->escapeCsv((string) ($veld ?? '')), $velden));
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
