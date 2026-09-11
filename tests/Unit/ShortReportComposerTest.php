<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Support\SalesAnalysisPresenter;
use App\Support\ShortReportComposer;
use Database\Factories\SalesAnalysisFactory;
use Tests\TestCase;

class ShortReportComposerTest extends TestCase
{
    public function test_short_report_limits_strengths_problems_and_actions_to_three(): void
    {
        $strengths = [];
        $mistakes = [];
        $coaching = [];
        for ($i = 1; $i <= 5; $i++) {
            $strengths[] = ['text' => 'Strength '.$i, 'why' => 'Why'];
            $mistakes[] = [
                'mistake' => 'Problem '.$i,
                'impact' => 'high',
                'why' => 'Why',
                'better_action' => 'Fix it',
                'example_phrase' => '',
            ];
            $coaching[] = [
                'priority' => $i,
                'skill' => 'Skill '.$i,
                'why' => 'Why',
                'evidence' => [],
                'practice' => 'Practice '.$i,
                'success_criteria' => 'Done',
            ];
        }

        $short = ShortReportComposer::from(SalesAnalysisFactory::validPayload([
            'what_to_repeat' => $strengths,
            'critical_mistakes' => $mistakes,
            'coaching_priorities' => $coaching,
        ]));

        $this->assertCount(3, $short['strengths']);
        $this->assertCount(3, $short['problems']);
        $this->assertCount(3, $short['next_actions']);
        $this->assertSame('Strength 1', $short['strengths'][0]['text']);
        $this->assertSame('Problem 1', $short['problems'][0]['mistake']);
    }

    public function test_empty_mistake_is_not_presented(): void
    {
        $call = new Call([
            'status' => 'completed',
        ]);
        $analysis = new SalesAnalysis([
            'overall_score' => 40,
            'summary' => 'Summary',
            'result' => SalesAnalysisFactory::validPayload([
                'critical_mistakes' => [[
                    'mistake' => '',
                    'impact' => 'high',
                    'why' => '',
                    'better_action' => '',
                    'example_phrase' => '',
                ]],
                'better_phrases' => [[
                    'original' => '',
                    'better' => '',
                    'problem' => '',
                    'why_better' => '',
                ]],
            ]),
        ]);
        $call->setRelation('analysis', $analysis);

        $presented = SalesAnalysisPresenter::public($call);

        $this->assertSame([], $presented['critical_mistakes']);
        $this->assertSame([], $presented['better_phrases']);
    }
}
