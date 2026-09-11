import { router, usePage } from '@inertiajs/react';
import { Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

export default function AnalysisBehaviorPanel() {
    const t = useT();
    const { analysis_settings: settings = {}, errors = {} } = usePage().props;
    const [tokens, setTokens] = useState(settings.max_output_tokens ?? 16384);
    const [busy, setBusy] = useState(false);
    const min = settings.min_output_tokens ?? 4096;
    const max = settings.max_output_tokens_limit ?? 32768;

    return (
        <section className="app-widget p-4 space-y-4">
            <div>
                <h2 className="text-base font-semibold text-slate-900">{t('settings.analysisBehavior')}</h2>
                <p className="mt-1 text-sm text-slate-600">
                    {t('settings.analysisBehaviorHint', { version: settings.schema_version ?? 3 })}
                </p>
            </div>

            <div>
                <label className="block text-sm font-medium text-slate-700">{t('settings.outputLanguage')}</label>
                <input
                    type="text"
                    value={t('settings.sameAsCall')}
                    disabled
                    className="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600"
                />
                <p className="mt-1 text-xs text-slate-500">{t('settings.outputLanguageHint')}</p>
            </div>

            <div>
                <label className="block text-sm font-medium text-slate-700">{t('settings.maxTokens')}</label>
                <input
                    type="number"
                    min={min}
                    max={max}
                    value={tokens}
                    onChange={(event) => setTokens(event.target.value)}
                    className="mt-1 block h-10 w-full max-w-xs rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                />
                <p className="mt-1 text-xs text-slate-500">
                    {t('settings.maxTokensHint', { min, max })}
                </p>
            </div>

            <button
                type="button"
                disabled={busy}
                onClick={() => {
                    setBusy(true);
                    router.patch(
                        route('settings.analysis.update'),
                        { max_output_tokens: Number(tokens) },
                        {
                            preserveScroll: true,
                            onFinish: () => setBusy(false),
                        },
                    );
                }}
                className="inline-flex h-9 items-center gap-2 rounded-lg bg-indigo-600 px-3 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
            >
                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                {t('settings.saveAnalysis')}
            </button>

            {errors.max_output_tokens ? <p className="text-sm text-red-600">{errors.max_output_tokens}</p> : null}
        </section>
    );
}
