import FullAnalysisReport from '@/Components/Public/FullAnalysisReport';
import TranscriptModal, { TranscriptReadyCard } from '@/Components/Public/TranscriptModal';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useT } from '@/i18n';

export default function FullReport({ result = {} }) {
    const t = useT();
    const [transcriptOpen, setTranscriptOpen] = useState(false);
    const report = result.report;
    const status = result.status;

    return (
        <PublicLayout>
            <Head title={t('report.fullTitle')} />
            <div className="mx-auto max-w-5xl px-6 py-12">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{t('report.fullTitle')}</h1>
                {status !== 'completed' || !report ? (
                    <p className="mt-4 text-sm text-slate-600">{result.message || result.error || t('report.fullUnavailable')}</p>
                ) : (
                    <div className="mt-8 space-y-6">
                        {result.transcript ? (
                            <TranscriptReadyCard transcript={result.transcript} onOpen={() => setTranscriptOpen(true)} />
                        ) : null}
                        <FullAnalysisReport
                            report={report}
                            analysisMode={result.analysis_mode}
                            companyName={result.company_name}
                            employeeName={result.employee_name}
                            transcriptAvailable={Boolean(result.transcript)}
                            onOpenTranscript={() => setTranscriptOpen(true)}
                        />
                    </div>
                )}
            </div>
            <TranscriptModal
                open={transcriptOpen}
                onOpenChange={setTranscriptOpen}
                transcript={result.transcript}
            />
        </PublicLayout>
    );
}
