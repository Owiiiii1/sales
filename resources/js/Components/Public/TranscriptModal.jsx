import CallTranscript, { formatTranscriptTime } from '@/Components/Public/CallTranscript';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { useT } from '@/i18n';

function uniqueSpeakers(transcript) {
    const segments = transcript?.segments ?? [];
    const labels = [];
    segments.forEach((segment) => {
        const label = segment.speaker_label || null;
        if (label && !labels.includes(label)) {
            labels.push(label);
        }
    });
    return labels;
}

export default function TranscriptModal({ open, onOpenChange, transcript }) {
    const t = useT();

    if (!transcript) {
        return null;
    }

    const speakers = uniqueSpeakers(transcript);
    const duration = transcript.duration_seconds;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="flex max-h-[90vh] w-full max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl lg:max-w-5xl"
                showCloseButton
            >
                <DialogHeader className="border-b border-slate-200 px-6 py-4">
                    <DialogTitle>{t('progress.transcriptTitle')}</DialogTitle>
                    <DialogDescription className="sr-only">{t('progress.transcriptReady')}</DialogDescription>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                        {duration != null && duration !== '' && (
                            <span>{t('progress.duration')}: {formatTranscriptTime(duration)}</span>
                        )}
                        {transcript.language && (
                            <span>{t('report.language', { code: String(transcript.language).toUpperCase() })}</span>
                        )}
                        {speakers.length > 0 && (
                            <span>{t('progress.speakers')}: {speakers.join(', ')}</span>
                        )}
                    </div>
                </DialogHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <CallTranscript transcript={transcript} framed={false} />
                </div>
                <div className="border-t border-slate-200 px-6 py-3 text-right">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        {t('progress.closeTranscript')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function TranscriptReadyCard({ transcript, onOpen }) {
    const t = useT();

    if (!transcript) {
        return null;
    }

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 className="text-lg font-semibold text-slate-900">{t('progress.transcriptTitle')}</h2>
            <p className="mt-1 text-sm text-slate-600">{t('progress.transcriptReady')}</p>
            <button
                type="button"
                onClick={onOpen}
                className="mt-4 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
            >
                {t('progress.openTranscript')}
            </button>
        </section>
    );
}
