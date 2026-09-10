<?php

namespace App\Services\Analysis;

use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Services\Analysis\DTO\SalesAnalysisResult;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class SalesAnalysisWriter
{
    public function replace(Call $call, SalesAnalysisResult $result, ?DateTimeInterface $startedAt = null): SalesAnalysis
    {
        return DB::transaction(function () use ($call, $result, $startedAt) {
            $existing = SalesAnalysis::query()->where('call_id', $call->id)->first();
            $existing?->delete();

            return SalesAnalysis::query()->create([
                'call_id' => $call->id,
                'provider' => $result->provider,
                'model' => $result->model,
                'schema_version' => $result->schemaVersion,
                'overall_score' => $result->overallScore,
                'company_scorecard_score' => $result->companyScorecardScore,
                'summary' => $result->summary,
                'result' => $result->payload,
                'started_at' => $startedAt ?? now(),
                'completed_at' => now(),
                'error_message' => null,
                'company_context_hash' => $result->companyContextHash,
                'scorecard_id' => $result->scorecardId,
                'scorecard_snapshot' => $result->scorecardSnapshot,
                'context_snapshot' => $result->contextSnapshot,
            ]);
        });
    }
}
