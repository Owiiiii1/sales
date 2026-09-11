<?php

namespace App\Services\Analysis;

use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyFact;
use App\Models\CompanyScorecard;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Support\LanguageCode;
use Illuminate\Support\Facades\Log;

class AnalysisContextBuilder
{
    public function __construct(private AnalysisSettingsRepository $analysisSettings) {}

    public function build(Call $call): AnalysisContext
    {
        $language = $call->transcript?->language;

        if ($call->company_id === null) {
            return $this->generic($language, $call);
        }

        $company = $call->company()->with($this->relations())->first();

        if ($company === null) {
            return $this->generic($language, $call);
        }

        $this->loadScorecardChildren($company);

        $scorecard = $this->defaultScorecard($company);
        $packed = $this->pack($company, $scorecard);
        $hash = hash('sha256', json_encode($packed['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($packed['truncated']) {
            Log::warning('Company analysis context was truncated to the character budget.', [
                'call_id' => $call->id,
                'company_id' => $company->id,
                'budget' => $this->budget(),
            ]);
        }

        return new AnalysisContext(
            language: $language,
            reportLanguage: $this->resolveReportLanguage($call, $language, $company->profile?->report_language),
            companyName: $company->name,
            companyContextUsed: true,
            companyId: $company->id,
            companyContextHash: $hash,
            scorecardId: $scorecard?->id,
            snapshot: $packed['snapshot'],
            scorecardSnapshot: $packed['snapshot']['scorecard'] ?? null,
            companyContextText: $packed['text'],
            contextTruncated: $packed['truncated'],
        );
    }

    public function generic(?string $language = null, ?Call $call = null): AnalysisContext
    {
        $snapshot = ['company' => null];
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new AnalysisContext(
            language: $language,
            reportLanguage: $this->resolveReportLanguage($call, $language, null, generic: true),
            companyName: null,
            companyContextUsed: false,
            companyContextHash: $hash,
            snapshot: $snapshot,
        );
    }

    /**
     * @return array{used:int, budget:int, percent:int, truncated:bool, warning:bool}
     */
    public function usage(Company $company): array
    {
        $fresh = Company::query()->with($this->relations())->find($company->id) ?? $company;
        $this->loadScorecardChildren($fresh);

        $scorecard = $this->defaultScorecard($fresh);
        $packed = $this->pack($fresh, $scorecard);
        $budget = $this->budget();
        $used = $this->chars($packed['unconstrained']);
        $percent = $budget > 0 ? (int) round(($used / $budget) * 100) : 0;

        return [
            'used' => $used,
            'budget' => $budget,
            'percent' => $percent,
            'truncated' => $packed['truncated'] || $used > $budget,
            'warning' => $percent >= 85 || $packed['truncated'] || $used > $budget,
        ];
    }

    /**
     * @return array{snapshot: array<string, mixed>, text: string, truncated: bool, unconstrained: string}
     */
    private function pack(Company $company, ?CompanyScorecard $scorecard): array
    {
        $profile = $company->profile;
        $chunks = [
            [
                'key' => 'scorecard',
                'text' => $this->scorecardText($scorecard),
                'data' => $this->scorecardSnapshot($scorecard),
            ],
            [
                'key' => 'facts',
                'text' => $this->factsText($company),
                'data' => $this->factsSnapshot($company),
            ],
            [
                'key' => 'core_profile',
                'text' => $this->coreProfileText($company),
                'data' => [
                    'short_description' => $profile?->short_description,
                    'target_audience' => $profile?->target_audience,
                    'ideal_customer_profile' => $profile?->ideal_customer_profile,
                    'value_proposition' => $profile?->value_proposition,
                    'usp' => $profile?->usp,
                    'customer_pains' => $profile?->customer_pains,
                    'pricing_context' => $profile?->pricing_context,
                    'sales_goals' => $profile?->sales_goals,
                    'desired_next_steps' => $profile?->desired_next_steps,
                    'sales_context' => $profile?->sales_context,
                ],
            ],
            [
                'key' => 'mandatory_questions',
                'text' => $this->labeled('Mandatory questions', $profile?->mandatory_questions),
                'data' => $profile?->mandatory_questions,
            ],
            [
                'key' => 'forbidden_claims',
                'text' => $this->labeled('Forbidden claims', $profile?->forbidden_claims),
                'data' => $profile?->forbidden_claims,
            ],
            [
                'key' => 'offerings',
                'text' => $this->offeringsText($company),
                'data' => $company->offerings->map(fn ($offering): array => [
                    'id' => $offering->id,
                    'type' => $offering->type,
                    'name' => $offering->name,
                    'description' => $offering->description,
                    'target_customer' => $offering->target_customer,
                    'value_proposition' => $offering->value_proposition,
                    'pricing' => $offering->pricing,
                    'differentiators' => $offering->differentiators,
                    'common_use_cases' => $offering->common_use_cases,
                ])->all(),
            ],
            [
                'key' => 'scripts',
                'text' => $this->scriptsText($company),
                'data' => $company->salesScripts->map(fn ($script): array => [
                    'id' => $script->id,
                    'name' => $script->name,
                    'script_text' => $script->script_text,
                ])->all(),
            ],
            [
                'key' => 'objections',
                'text' => $this->objectionsText($company),
                'data' => $company->objections->map(fn ($objection): array => [
                    'id' => $objection->id,
                    'objection' => $objection->objection,
                    'recommended_response' => $objection->recommended_response,
                    'priority' => $objection->priority,
                ])->all(),
            ],
            [
                'key' => 'competitors_notes',
                'text' => trim($this->labeled('Competitors', $profile?->competitors)."\n".$this->labeled('Notes', $profile?->notes)),
                'data' => [
                    'competitors' => $profile?->competitors,
                    'notes' => $profile?->notes,
                ],
            ],
        ];

        $unconstrainedParts = [];
        foreach ($chunks as $chunk) {
            $text = trim((string) $chunk['text']);
            if ($text !== '') {
                $unconstrainedParts[] = $text;
            }
        }

        $budget = $this->budget();
        $remaining = $budget;
        $truncated = false;
        $included = [];
        $texts = [];

        foreach ($chunks as $chunk) {
            $text = trim((string) $chunk['text']);
            if ($text === '') {
                continue;
            }

            $length = $this->chars($text);

            if ($length <= $remaining) {
                $included[$chunk['key']] = $chunk['data'];
                $texts[] = $text;
                $remaining -= $length + 2;

                continue;
            }

            $suffix = '…[truncated]';
            $keep = $remaining - $this->chars($suffix);

            if ($keep > 0) {
                $texts[] = mb_substr($text, 0, $keep, 'UTF-8').$suffix;
                $included[$chunk['key']] = $chunk['data'];
            }

            $truncated = true;
            break;
        }

        $snapshot = [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'industry' => $company->industry,
                'website' => $company->website,
            ],
            'scorecard' => $included['scorecard'] ?? null,
            'facts' => $included['facts'] ?? [],
            'mandatory_questions' => $included['mandatory_questions'] ?? null,
            'forbidden_claims' => $included['forbidden_claims'] ?? null,
            'scripts' => $included['scripts'] ?? [],
            'offerings' => $included['offerings'] ?? [],
            'objections' => $included['objections'] ?? [],
            'profile' => $included['core_profile'] ?? [],
            'competitors' => $included['competitors_notes']['competitors'] ?? null,
            'notes' => $included['competitors_notes']['notes'] ?? null,
            'truncated' => $truncated,
        ];

        return [
            'snapshot' => $snapshot,
            'text' => implode("\n\n", $texts),
            'truncated' => $truncated,
            'unconstrained' => implode("\n\n", $unconstrainedParts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function relations(): array
    {
        return [
            'profile',
            'facts' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            'offerings' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'objections' => fn ($query) => $query->where('is_active', true)->orderBy('priority')->orderBy('id'),
            'salesScripts' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'scorecards' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_default')->orderBy('id'),
        ];
    }

    private function loadScorecardChildren(Company $company): void
    {
        $company->scorecards->load([
            'criteria' => fn ($query) => $query->where('is_active', true)->orderBy('sequence'),
            'caps' => fn ($query) => $query->where('is_active', true)->orderBy('sequence'),
        ]);
    }

    private function defaultScorecard(Company $company): ?CompanyScorecard
    {
        return $company->scorecards->first(fn (CompanyScorecard $scorecard): bool => $scorecard->is_default)
            ?? $company->scorecards->first();
    }

    private function budget(): int
    {
        return max(1000, (int) config('sales-analyzer.analysis.context_budget_characters', 50000));
    }

    private function chars(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }

    private function resolveReportLanguage(?Call $call, ?string $transcriptLanguage, ?string $companyMode, bool $generic = false): string
    {
        $uiLocale = LanguageCode::normalize($call?->ui_locale);
        if (LanguageCode::isSupported($uiLocale)) {
            return $uiLocale;
        }

        if (! $generic && filled($companyMode) && $companyMode !== 'same_as_call') {
            return LanguageCode::normalize($companyMode) ?: 'en';
        }

        if ($generic) {
            $global = $this->analysisSettings->current()->report_language_mode;
            if (filled($global) && $global !== 'same_as_call') {
                return LanguageCode::normalize($global) ?: 'en';
            }
        }

        return LanguageCode::normalize($transcriptLanguage) ?: 'en';
    }

    private function labeled(string $label, ?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        return $label.":\n".$value;
    }

    private function factsText(Company $company): string
    {
        $facts = $company->facts ?? collect();
        if ($facts->isEmpty()) {
            return '';
        }

        $current = [];
        $outdated = [];

        foreach ($facts as $fact) {
            $line = '- '.$fact->label.': '.$fact->value;
            if ($fact->valid_until) {
                $line .= ' (valid until '.$fact->valid_until->toDateString().')';
            }
            if (filled($fact->source)) {
                $line .= ' [source: '.$fact->source.']';
            }

            if ($fact->status === CompanyFact::STATUS_OUTDATED) {
                $outdated[] = $line;
            } else {
                $current[] = $line;
            }
        }

        $blocks = ['VERIFIABLE COMPANY FACTS'];
        $blocks[] = 'CURRENT:';
        $blocks[] = $current === [] ? '(none)' : implode("\n", $current);
        $blocks[] = 'OUTDATED:';
        $blocks[] = $outdated === [] ? '(none)' : implode("\n", $outdated);

        return implode("\n", $blocks);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function factsSnapshot(Company $company): array
    {
        return ($company->facts ?? collect())->map(fn (CompanyFact $fact): array => [
            'id' => $fact->id,
            'label' => $fact->label,
            'value' => $fact->value,
            'status' => $fact->status,
            'valid_until' => $fact->valid_until?->toDateString(),
            'source' => $fact->source,
        ])->all();
    }

    private function scorecardText(?CompanyScorecard $scorecard): string
    {
        if ($scorecard === null) {
            return '';
        }

        $lines = ['Company scorecard: '.$scorecard->name];
        if (filled($scorecard->description)) {
            $lines[] = $scorecard->description;
        }

        foreach ($scorecard->criteria as $criterion) {
            $lines[] = sprintf(
                '- [%s] %s (weight %s, max %d%s)%s',
                $criterion->key,
                $criterion->name,
                rtrim(rtrim(number_format((float) $criterion->weight, 2, '.', ''), '0'), '.'),
                $criterion->max_score,
                $criterion->is_critical ? ', critical' : '',
                filled($criterion->ai_instructions) ? ' — '.$criterion->ai_instructions : '',
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function scorecardSnapshot(?CompanyScorecard $scorecard): ?array
    {
        if ($scorecard === null) {
            return null;
        }

        return [
            'id' => $scorecard->id,
            'name' => $scorecard->name,
            'description' => $scorecard->description,
            'score_bands' => $scorecard->score_bands,
            'criteria' => $scorecard->criteria->map(fn ($criterion): array => [
                'key' => $criterion->key,
                'name' => $criterion->name,
                'description' => $criterion->description,
                'weight' => (float) $criterion->weight,
                'max_score' => $criterion->max_score,
                'is_critical' => (bool) $criterion->is_critical,
                'ai_instructions' => $criterion->ai_instructions,
                'sequence' => $criterion->sequence,
            ])->all(),
            'caps' => $scorecard->caps->map(fn ($cap): array => [
                'id' => $cap->id,
                'name' => $cap->name,
                'criterion_key' => $cap->criterion_key,
                'trigger_type' => $cap->trigger_type,
                'max_total_score' => (int) $cap->max_total_score,
                'description' => $cap->description,
            ])->all(),
        ];
    }

    private function scriptsText(Company $company): string
    {
        if ($company->salesScripts->isEmpty()) {
            return '';
        }

        $blocks = ['Sales scripts:'];
        foreach ($company->salesScripts as $script) {
            $blocks[] = '## '.$script->name."\n".$script->script_text;
        }

        return implode("\n\n", $blocks);
    }

    private function offeringsText(Company $company): string
    {
        if ($company->offerings->isEmpty()) {
            return '';
        }

        $lines = ['Offerings:'];
        foreach ($company->offerings as $offering) {
            $lines[] = sprintf(
                '- [%s] %s: %s',
                $offering->type,
                $offering->name,
                collect([
                    $offering->description,
                    $offering->value_proposition,
                    $offering->pricing,
                ])->filter()->implode(' | '),
            );
        }

        return implode("\n", $lines);
    }

    private function objectionsText(Company $company): string
    {
        if ($company->objections->isEmpty()) {
            return '';
        }

        $lines = ['Known objections and expected responses:'];
        foreach ($company->objections as $objection) {
            $lines[] = '- '.$objection->objection.': '.($objection->recommended_response ?: 'No recommended response stored.');
        }

        return implode("\n", $lines);
    }

    private function coreProfileText(Company $company): string
    {
        $profile = $company->profile;
        $parts = [
            $this->labeled('What the company sells', $profile?->short_description),
            $this->labeled('Target customer', $profile?->target_audience),
            $this->labeled('Ideal customer', $profile?->ideal_customer_profile),
            $this->labeled('Customer pains', $profile?->customer_pains),
            $this->labeled('Value proposition', $profile?->value_proposition),
            $this->labeled('USP', $profile?->usp),
            $this->labeled('Pricing context', $profile?->pricing_context),
            $this->labeled('Sales goals', $profile?->sales_goals),
            $this->labeled('Desired next steps', $profile?->desired_next_steps),
            $this->labeled('Additional sales context', $profile?->sales_context),
        ];

        $text = trim(implode("\n\n", array_filter($parts)));

        return $text === '' ? '' : "Company profile:\n".$text;
    }
}
