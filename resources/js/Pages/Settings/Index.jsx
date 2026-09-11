import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useT } from '@/i18n';
import AiPanel from './AiPanel';
import AnalysisBehaviorPanel from './AnalysisBehaviorPanel';
import PipelineStatus from './PipelineStatus';
import TelegramPanel from './TelegramPanel';
import TranscriptionPanel from './TranscriptionPanel';
import UsersPanel from './UsersPanel';

export default function SettingsIndex() {
    const t = useT();
    const { tab: initialTab = 'users', pipeline = {} } = usePage().props;
    const [activeTab, setActiveTab] = useState(initialTab);

    useEffect(() => {
        setActiveTab(initialTab);
    }, [initialTab]);

    const tabs = useMemo(
        () => [
            { id: 'users', label: t('settings.users') },
            { id: 'transcription', label: t('settings.transcription') },
            { id: 'ai', label: t('settings.ai') },
        ],
        [t],
    );

    const switchTab = (nextTab) => {
        if (nextTab === activeTab) {
            return;
        }

        setActiveTab(nextTab);
        router.get(
            route('settings.index'),
            { tab: nextTab },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const showPipeline = activeTab === 'transcription' || activeTab === 'ai';

    return (
        <AdminLayout title={t('settings.title')}>
            <Head title={t('settings.title')} />

            <div className="space-y-6">
                <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                    {tabs.map((tab) => {
                        const active = activeTab === tab.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => switchTab(tab.id)}
                                className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
                                    active
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {showPipeline && <PipelineStatus pipeline={pipeline} />}
                {activeTab === 'users' && <UsersPanel />}
                {activeTab === 'transcription' && <TranscriptionPanel />}
                {activeTab === 'ai' && (
                    <div className="space-y-6">
                        <AiPanel />
                        <AnalysisBehaviorPanel />
                    </div>
                )}
                {activeTab === 'telegram' && <TelegramPanel />}
            </div>
        </AdminLayout>
    );
}
