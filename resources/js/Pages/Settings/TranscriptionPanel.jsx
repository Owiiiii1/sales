import { router, usePage } from '@inertiajs/react';
import { Loader2, PlugZap, Save } from 'lucide-react';
import { useState } from 'react';

function sourceCopy(source) {
    if (source === 'environment') {
        return 'Configured via environment';
    }
    if (source === 'database') {
        return 'Configured in the database';
    }

    return 'Not configured';
}

export default function TranscriptionPanel() {
    const { transcription = {}, errors = {} } = usePage().props;
    const [apiKey, setApiKey] = useState('');
    const [model, setModel] = useState(transcription.active_model ?? 'scribe_v2');
    const [busy, setBusy] = useState(null);

    const models = transcription.available_models ?? [{ id: 'scribe_v2', name: 'Scribe v2' }];
    const connected = !!transcription.is_connected || transcription.source === 'environment';
    const lastChecked = transcription.last_checked_at
        ? new Date(transcription.last_checked_at).toLocaleString()
        : null;

    const submit = (action, payload = {}, onSuccess) => {
        setBusy(action);
        router.post(route(action === 'check' ? 'settings.transcription.check' : 'settings.transcription.save'), payload, {
            preserveScroll: true,
            onSuccess: () => {
                setApiKey('');
                onSuccess?.();
            },
            onFinish: () => setBusy(null),
        });
    };

    return (
        <div className="space-y-6">
            <section className="app-widget p-4">
                <h2 className="text-base font-semibold text-slate-900">Transcription</h2>
                <p className="mt-1 text-sm text-slate-600">
                    Speech-to-text uses ElevenLabs. Save an API key here so workers pick it up without a restart.
                </p>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-sm font-medium text-slate-700">Provider</p>
                        <p className="mt-1 text-sm text-slate-900">{transcription.label ?? 'ElevenLabs'}</p>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-slate-700">Configuration source</p>
                        <p className="mt-1 text-sm text-slate-900">{sourceCopy(transcription.source)}</p>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-slate-700">Connection</p>
                        <p className="mt-1 text-sm text-slate-900">
                            {connected ? 'Connected' : transcription.last_error ? 'Failed' : 'Not checked'}
                        </p>
                        {lastChecked ? <p className="text-xs text-slate-500">Last checked: {lastChecked}</p> : null}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-slate-700">Status</p>
                        <p className="mt-1 text-sm text-slate-900">
                            {transcription.is_active ? 'Active' : 'Inactive'}
                        </p>
                    </div>
                </div>
            </section>

            <section className="app-widget p-4 space-y-4">
                <div>
                    <label className="block text-sm font-medium text-slate-700">ElevenLabs API Key</label>
                    <input
                        type="password"
                        value={apiKey}
                        onChange={(event) => setApiKey(event.target.value)}
                        placeholder={transcription.has_api_key || transcription.source === 'environment' ? '••••••••' : ''}
                        className="mt-1 block h-10 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        autoComplete="off"
                    />
                    {transcription.api_key_masked ? (
                        <p className="mt-1 text-xs text-slate-500">
                            Saved key: <span className="font-medium">{transcription.api_key_masked}</span>
                        </p>
                    ) : null}
                    <p className="mt-1 text-xs text-slate-500">Leave blank to keep the current key.</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700">Model</label>
                    <select
                        value={model}
                        onChange={(event) => setModel(event.target.value)}
                        className="mt-1 block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    >
                        {models.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name ?? item.id}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        disabled={busy !== null}
                        onClick={() => submit('save', { api_key: apiKey, model })}
                        className="inline-flex h-9 items-center gap-2 rounded-lg bg-indigo-600 px-3 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {busy === 'save' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        Save
                    </button>
                    <button
                        type="button"
                        disabled={busy !== null}
                        onClick={() => submit('check')}
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                    >
                        {busy === 'check' ? <Loader2 className="h-4 w-4 animate-spin" /> : <PlugZap className="h-4 w-4" />}
                        Check connection
                    </button>
                </div>

                {transcription.last_error ? (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {transcription.last_error}
                    </div>
                ) : null}
                {errors.transcription ? <p className="text-sm text-red-600">{errors.transcription}</p> : null}
                {errors.api_key ? <p className="text-sm text-red-600">{errors.api_key}</p> : null}
                {errors.model ? <p className="text-sm text-red-600">{errors.model}</p> : null}
            </section>
        </div>
    );
}
