<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsFilter;
use App\Support\ScoreBand;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_period_is_last_30_days(): void
    {
        $filter = AnalyticsFilter::fromRequest(Request::create('/dashboard', 'GET'));

        $this->assertSame('last_30', $filter->period);
        $this->assertSame('2026-08-12', $filter->from?->toDateString());
        $this->assertSame('2026-09-10', $filter->to?->toDateString());
        $this->assertSame('day', $filter->grouping());
    }

    public function test_custom_period_and_previous_window(): void
    {
        $filter = AnalyticsFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'period' => 'custom',
            'from' => '2026-09-01',
            'to' => '2026-09-10',
        ]));

        $this->assertSame('2026-09-01', $filter->from?->toDateString());
        $this->assertSame('2026-09-10', $filter->to?->toDateString());

        $previous = $filter->previous();
        $this->assertNotNull($previous);
        $this->assertSame('2026-08-22', $previous->from?->toDateString());
        $this->assertSame('2026-08-31', $previous->to?->toDateString());
    }

    public function test_all_time_has_no_previous_period(): void
    {
        $filter = AnalyticsFilter::fromRequest(Request::create('/dashboard', 'GET', ['period' => 'all_time']));

        $this->assertNull($filter->from);
        $this->assertNull($filter->previous());
        $this->assertSame('week', $filter->grouping());
    }

    public function test_score_bands(): void
    {
        $this->assertSame('good', ScoreBand::for(80));
        $this->assertSame('warning', ScoreBand::for(60));
        $this->assertSame('poor', ScoreBand::for(59));
        $this->assertNull(ScoreBand::for(null));
    }
}
