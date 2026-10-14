<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Services\Branding\BrandingService;
use App\Services\Mail\MailPreviewSampleData;
use App\Services\Mail\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailTemplateController extends Controller
{
    /**
     * Voorbeeldwaarden voor `{{placeholders}}` in de voorbeeldweergave. `app_name`
     * wordt altijd overschreven met de echte branding-naam.
     *
     * @var array<string, string>
     */
    private const VOORBEELD_VARIABELEN = [
        'voornaam' => 'Jan',
        'app_name' => 'Preekrooster',
        'dagen' => '14',
        'spreker' => 'Ds. de Vries',
        'gemeente' => 'Wijhe',
        'periode' => '2026-07',
        'maand' => 'juli 2026',
    ];

    public function __construct(
        private readonly MailTemplateService $mailTemplateService,
        private readonly BrandingService $brandingService,
        private readonly MailPreviewSampleData $mailPreviewSampleData,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->mailTemplateService->alle(),
        ]);
    }

    public function show(string $sleutel): JsonResponse
    {
        return response()->json([
            'data' => $this->mailTemplateService->get($sleutel),
        ]);
    }

    public function update(Request $request, string $sleutel): JsonResponse
    {
        $data = $request->validate([
            'onderwerp' => ['required', 'array'],
            'onderwerp.nl' => ['required', 'string', 'max:255'],
            'onderwerp.en' => ['required', 'string', 'max:255'],
            'inhoud' => ['required', 'array'],
            'inhoud.nl' => ['required', 'string', 'max:5000'],
            'inhoud.en' => ['required', 'string', 'max:5000'],
        ]);

        return response()->json([
            'message' => __('api.beheer.mail_template_updated'),
            'data' => $this->mailTemplateService->update($sleutel, $data['onderwerp'], $data['inhoud']),
        ]);
    }

    public function reset(string $sleutel): JsonResponse
    {
        return response()->json([
            'message' => __('api.beheer.mail_template_reset'),
            'data' => $this->mailTemplateService->resetten($sleutel),
        ]);
    }

    public function signature(): JsonResponse
    {
        return response()->json([
            'data' => $this->mailTemplateService->signature(),
        ]);
    }

    public function updateSignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inhoud' => ['present', 'string', 'max:2000'],
        ]);

        return response()->json([
            'message' => __('api.beheer.mail_signature_updated'),
            'data' => $this->mailTemplateService->updateSignature($data['inhoud']),
        ]);
    }

    public function resetSignature(): JsonResponse
    {
        return response()->json([
            'message' => __('api.beheer.mail_signature_reset'),
            'data' => $this->mailTemplateService->resetSignature(),
        ]);
    }

    /**
     * Rendert een voorbeeldweergave (HTML + platte tekst) van een concept-onderwerp/
     * inhoud/handtekening, zodat de beheerder kan zien hoe een e-mail eruit komt te
     * zien vóórdat deze wordt opgeslagen. Placeholders worden ingevuld met
     * voorbeeldgegevens; `{{app_name}}` gebruikt altijd de echte branding-naam.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sleutel' => ['sometimes', 'nullable', 'string'],
            'onderwerp' => ['required', 'string', 'max:255'],
            'inhoud' => ['required', 'string', 'max:5000'],
            'handtekening' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $branding = $this->brandingService->mailBranding();
        $variabelen = [...self::VOORBEELD_VARIABELEN, 'app_name' => $branding['app_name']];

        $onderwerp = $this->mailTemplateService->interpoleerVoorbeeld($data['onderwerp'], $variabelen);
        $inhoud = $this->mailTemplateService->interpoleerVoorbeeld($data['inhoud'], $variabelen);

        $signatureOverride = array_key_exists('handtekening', $data)
            ? $this->mailTemplateService->interpoleerVoorbeeld((string) ($data['handtekening'] ?? ''), $variabelen)
            : null;

        $shared = [
            'onderwerp' => $onderwerp,
            'inhoud' => $inhoud,
            'branding' => $branding,
            'previewSignatureOverride' => $signatureOverride,
        ];

        $sleutel = isset($data['sleutel']) && is_string($data['sleutel']) && $data['sleutel'] !== ''
            ? $data['sleutel']
            : null;

        $sample = $sleutel !== null ? $this->mailPreviewSampleData->for($sleutel) : null;

        if ($sample !== null) {
            $viewData = [...$sample['with'], ...$shared];

            return response()->json([
                'data' => [
                    'onderwerp' => $onderwerp,
                    'html' => view($sample['htmlView'], $viewData)->render(),
                    'text' => view($sample['textView'], $viewData)->render(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'onderwerp' => $onderwerp,
                'html' => view('mail.preview_html', $shared)->render(),
                'text' => view('mail.preview_text', $shared)->render(),
            ],
        ]);
    }
}
