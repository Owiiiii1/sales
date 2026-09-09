export function formatTranscriptTime(seconds) {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

export default function CallTranscript({ transcript, heading = 'Transcript' }) {
    if (!transcript) {
        return null;
    }

    const segments = transcript.segments ?? [];

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">{heading}</h2>
            {transcript.language && (
                <p className="mt-1 text-sm text-slate-500">Language: {transcript.language.toUpperCase()}</p>
            )}
            <div className="mt-6 space-y-4">
                {segments.length > 0 ? (
                    segments.map((segment, index) => (
                        <div key={`${segment.speaker}-${segment.start_seconds}-${index}`}>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {segment.start_label || formatTranscriptTime(segment.start_seconds)}{' '}
                                {segment.speaker_label || `Speaker ${(segment.speaker ?? 0) + 1}`}
                            </p>
                            <p className="mt-1 text-sm leading-6 text-slate-800">{segment.text}</p>
                        </div>
                    ))
                ) : (
                    <p className="whitespace-pre-wrap text-sm leading-6 text-slate-800">{transcript.text}</p>
                )}
            </div>
        </section>
    );
}
