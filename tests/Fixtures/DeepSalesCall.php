<?php

namespace Tests\Fixtures;

use App\Models\Call;
use App\Models\Transcript;
use App\Models\TranscriptSegment;

class DeepSalesCall
{
    /**
     * Synthetic inbound call: greeting, discovery, price objection, missed signal, weak close.
     *
     * @return array<int, array{speaker:int, start:float, end:float, text:string}>
     */
    public static function segments(): array
    {
        return [
            ['speaker' => 0, 'start' => 0.0, 'end' => 8.0, 'text' => 'Hi, thanks for taking my call. This is Alex from Northwind Software.'],
            ['speaker' => 1, 'start' => 8.5, 'end' => 16.0, 'text' => 'Hi Alex. We are looking at tools because our current process is too slow for the team.'],
            ['speaker' => 0, 'start' => 16.5, 'end' => 22.0, 'text' => 'Got it. Our platform has dashboards, alerts, and a mobile app.'],
            ['speaker' => 1, 'start' => 22.5, 'end' => 30.0, 'text' => 'The delay is killing us at month end. Implementation time is my other worry.'],
            ['speaker' => 0, 'start' => 30.5, 'end' => 38.0, 'text' => 'Pricing starts at two thousand a month, but I can do fifteen hundred if you sign this week.'],
            ['speaker' => 1, 'start' => 38.5, 'end' => 36.0 + 10, 'text' => 'That still feels high. I need to think about it.'],
            ['speaker' => 0, 'start' => 47.0, 'end' => 54.0, 'text' => 'Okay, I will send a brochure and be in touch.'],
        ];
    }

    public static function attachTo(Call $call): Call
    {
        $segments = self::segments();
        $text = collect($segments)->pluck('text')->implode(' ');

        $transcript = Transcript::factory()->create([
            'call_id' => $call->id,
            'raw_text' => $text,
            'language' => 'en',
            'duration_seconds' => 54,
        ]);

        foreach ($segments as $index => $segment) {
            TranscriptSegment::factory()->create([
                'transcript_id' => $transcript->id,
                'speaker' => $segment['speaker'],
                'sequence' => $index,
                'start_seconds' => $segment['start'],
                'end_seconds' => $segment['end'],
                'text' => $segment['text'],
            ]);
        }

        return $call->fresh(['transcript.segments']);
    }
}
