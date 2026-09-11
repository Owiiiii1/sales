import AdminLayout from '@/Layouts/AdminLayout';
import { AnalyticsFilters, AnalyticsSections } from '@/Components/Analytics/Board';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useT } from '@/i18n';

const TABS = ['overview', 'analytics', 'knowledge', 'offerings', 'objections', 'scripts', 'scorecard', 'employees', 'calls'];

const PROFILE_FIELDS = [
    'short_description',
    'target_audience',
    'ideal_customer_profile',
    'customer_pains',
    'value_proposition',
    'usp',
    'competitors',
    'pricing_context',
    'sales_goals',
    'desired_next_steps',
    'mandatory_questions',
    'forbidden_claims',
    'sales_context',
    'notes',
];

export default function CompanyShow({
    company,
    tab = 'overview',
    profile = {},
    completeness = { percent: 0, items: {} },
    offerings = [],
    objections = [],
    scripts = [],
    scorecards = [],
    employees = [],
    calls = [],
    filters = { period: 'last_30' },
    analytics = null,
}) {
    const t = useT();

    const go = (nextTab) => {
        const params = { tab: nextTab };
        if (nextTab === 'analytics') {
            params.period = filters?.period || 'last_30';
            if (filters?.period === 'custom') {
                params.from = filters.from_date;
                params.to = filters.to_date;
            }
        }
        router.get(route('companies.show', company.id), params, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout title={company.name}>
            <Head title={company.name} />
            <div className="space-y-6">
                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('common.company')}</p>
                            <h2 className="mt-1 text-xl font-semibold text-slate-900">{company.name}</h2>
                            <p className="mt-2 text-sm text-slate-600">{t('companies.completeness', { percent: completeness.percent })}</p>
                        </div>
                        <Link href={route('companies.index')} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">
                            {t('companies.back')}
                        </Link>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {TABS.map((id) => (
                            <button
                                key={id}
                                type="button"
                                onClick={() => go(id)}
                                className={`rounded-full px-3 py-1.5 text-sm font-medium ${tab === id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700'}`}
                            >
                                {t(`companies.${id}`)}
                            </button>
                        ))}
                    </div>
                </section>

                {tab === 'overview' && <Overview company={company} completeness={completeness} />}
                {tab === 'analytics' && (
                    <div className="space-y-6">
                        <AnalyticsFilters
                            filters={filters}
                            extra={{ tab: 'analytics' }}
                            action={(params) => router.get(route('companies.show', company.id), params, { preserveState: true, preserveScroll: true })}
                        />
                        <AnalyticsSections analytics={{ ...(analytics || {}), hide_public: true }} variant="company" />
                    </div>
                )}
                {tab === 'knowledge' && <Knowledge company={company} profile={profile} />}
                {tab === 'offerings' && <Offerings company={company} offerings={offerings} />}
                {tab === 'objections' && <Objections company={company} objections={objections} />}
                {tab === 'scripts' && <Scripts company={company} scripts={scripts} />}
                {tab === 'scorecard' && <Scorecards company={company} scorecards={scorecards} />}
                {tab === 'employees' && <EmployeesList company={company} employees={employees} />}
                {tab === 'calls' && <CallsList calls={calls} />}
            </div>
        </AdminLayout>
    );
}

function Overview({ company, completeness }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.overview')}</h3>
            <dl className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                <Item label={t('common.name')} value={company.name} />
                <Item label={t('companies.legalName')} value={company.legal_name} />
                <Item label={t('common.website')} value={company.website} />
                <Item label={t('common.industry')} value={company.industry} />
                <Item label={t('common.phone')} value={company.phone} />
                <Item label={t('common.email')} value={company.email} />
                <Item label={t('companies.location')} value={[company.city, company.country].filter(Boolean).join(', ')} />
                <Item label={t('common.status')} value={company.is_active ? t('common.active') : t('common.inactive')} />
            </dl>
            {company.description && <p className="mt-4 text-sm leading-6 text-slate-700">{company.description}</p>}
            <div className="mt-6">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('companies.checklist')}</p>
                <ul className="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2 text-sm">
                    {Object.entries(completeness.items || {}).map(([key, filled]) => (
                        <li key={key} className={filled ? 'text-emerald-700' : 'text-slate-500'}>
                            {filled ? '✓' : '○'} {t(`companies.checklistItems.${key}`)}
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

function Knowledge({ company, profile }) {
    const t = useT();
    const form = useForm({
        short_description: profile.short_description ?? '',
        sales_context: profile.sales_context ?? '',
        target_audience: profile.target_audience ?? '',
        ideal_customer_profile: profile.ideal_customer_profile ?? '',
        value_proposition: profile.value_proposition ?? '',
        usp: profile.usp ?? '',
        pricing_context: profile.pricing_context ?? '',
        competitors: profile.competitors ?? '',
        customer_pains: profile.customer_pains ?? '',
        sales_goals: profile.sales_goals ?? '',
        desired_next_steps: profile.desired_next_steps ?? '',
        forbidden_claims: profile.forbidden_claims ?? '',
        mandatory_questions: profile.mandatory_questions ?? '',
        notes: profile.notes ?? '',
    });

    return (
        <section className="app-widget p-4">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.knowledgeTitle')}</h3>
            <p className="mt-1 text-sm text-slate-500">{t('companies.knowledgeHint')}</p>
            <form
                className="mt-4 space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(route('companies.profile.update', company.id), { preserveScroll: true });
                }}
            >
                {PROFILE_FIELDS.map((key) => (
                    <div key={key}>
                        <label className="mb-1 block text-sm font-medium text-slate-600">{t(`companies.profile.${key}`)}</label>
                        <textarea
                            rows={3}
                            value={form.data[key] ?? ''}
                            onChange={(e) => form.setData(key, e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>
                ))}
                <div className="flex justify-end">
                    <button type="submit" disabled={form.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.saveKnowledge')}</button>
                </div>
            </form>
        </section>
    );
}

function Offerings({ company, offerings }) {
    const t = useT();
    const create = useForm({
        type: 'service',
        name: '',
        description: '',
        target_customer: '',
        value_proposition: '',
        pricing: '',
        differentiators: '',
        common_use_cases: '',
        is_active: true,
    });

    return (
        <section className="app-widget p-4 space-y-6">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.offerings')}</h3>
            <form
                className="grid grid-cols-1 gap-3 md:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    create.post(route('companies.offerings.store', company.id), {
                        preserveScroll: true,
                        onSuccess: () => create.reset(),
                    });
                }}
            >
                <Select
                    label={t('common.type')}
                    value={create.data.type}
                    onChange={(v) => create.setData('type', v)}
                    options={['product', 'service', 'other'].map((type) => ({
                        value: type,
                        label: t(`companies.offeringTypes.${type}`),
                    }))}
                />
                <Field label={t('common.name')} value={create.data.name} onChange={(v) => create.setData('name', v)} error={create.errors.name} />
                <div className="md:col-span-2">
                    <Textarea label={t('common.description')} value={create.data.description} onChange={(v) => create.setData('description', v)} />
                </div>
                <Field label={t('companies.targetCustomer')} value={create.data.target_customer} onChange={(v) => create.setData('target_customer', v)} />
                <Field label={t('companies.pricing')} value={create.data.pricing} onChange={(v) => create.setData('pricing', v)} />
                <div className="md:col-span-2 flex justify-end">
                    <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.addOffering')}</button>
                </div>
            </form>
            <div className="space-y-3">
                {offerings.map((item) => (
                    <CrudCard
                        key={item.id}
                        title={`${item.name} (${t(`companies.offeringTypes.${item.type}`)})`}
                        active={item.is_active}
                        onDelete={() => router.delete(route('companies.offerings.destroy', [company.id, item.id]), { preserveScroll: true })}
                    >
                        <p className="text-sm text-slate-700">{item.description || t('companies.noDescription')}</p>
                    </CrudCard>
                ))}
            </div>
        </section>
    );
}

function Objections({ company, objections }) {
    const t = useT();
    const create = useForm({ objection: '', recommended_response: '', notes: '', priority: '', is_active: true });

    return (
        <section className="app-widget p-4 space-y-6">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.objections')}</h3>
            <form
                className="grid grid-cols-1 gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    create.post(route('companies.objections.store', company.id), { preserveScroll: true, onSuccess: () => create.reset() });
                }}
            >
                <Field label={t('companies.objection')} value={create.data.objection} onChange={(v) => create.setData('objection', v)} error={create.errors.objection} />
                <Textarea label={t('companies.recommendedResponse')} value={create.data.recommended_response} onChange={(v) => create.setData('recommended_response', v)} />
                <Field label={t('companies.priority')} value={create.data.priority} onChange={(v) => create.setData('priority', v)} />
                <div className="flex justify-end">
                    <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.addObjection')}</button>
                </div>
            </form>
            {objections.map((item) => (
                <CrudCard
                    key={item.id}
                    title={item.objection}
                    active={item.is_active}
                    onDelete={() => router.delete(route('companies.objections.destroy', [company.id, item.id]), { preserveScroll: true })}
                >
                    <p className="text-sm text-slate-700">{item.recommended_response || t('companies.noResponse')}</p>
                </CrudCard>
            ))}
        </section>
    );
}

function Scripts({ company, scripts }) {
    const t = useT();
    const create = useForm({ name: '', description: '', script_text: '', is_active: true });

    return (
        <section className="app-widget p-4 space-y-6">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.salesScripts')}</h3>
            <form
                className="grid grid-cols-1 gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    create.post(route('companies.scripts.store', company.id), { preserveScroll: true, onSuccess: () => create.reset() });
                }}
            >
                <Field label={t('common.name')} value={create.data.name} onChange={(v) => create.setData('name', v)} error={create.errors.name} />
                <Textarea label={t('companies.script')} value={create.data.script_text} onChange={(v) => create.setData('script_text', v)} error={create.errors.script_text} />
                <div className="flex justify-end">
                    <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.addScript')}</button>
                </div>
            </form>
            {scripts.map((item) => (
                <CrudCard
                    key={item.id}
                    title={item.name}
                    active={item.is_active}
                    onDelete={() => router.delete(route('companies.scripts.destroy', [company.id, item.id]), { preserveScroll: true })}
                >
                    <pre className="whitespace-pre-wrap text-sm text-slate-700">{item.script_text}</pre>
                </CrudCard>
            ))}
        </section>
    );
}

function Scorecards({ company, scorecards }) {
    const t = useT();
    const create = useForm({ name: '', description: '', is_default: false, is_active: true });
    const criterion = useForm({
        scorecard_id: scorecards[0]?.id ?? '',
        key: '',
        name: '',
        description: '',
        weight: 20,
        max_score: 100,
        is_critical: false,
        ai_instructions: '',
        is_active: true,
    });

    return (
        <section className="app-widget p-4 space-y-6">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.scorecards')}</h3>
            <form
                className="grid grid-cols-1 gap-3 md:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    create.post(route('companies.scorecards.store', company.id), { preserveScroll: true, onSuccess: () => create.reset('name', 'description') });
                }}
            >
                <Field label={t('common.name')} value={create.data.name} onChange={(v) => create.setData('name', v)} error={create.errors.name} />
                <Field label={t('common.description')} value={create.data.description} onChange={(v) => create.setData('description', v)} />
                <div className="md:col-span-2 flex justify-end">
                    <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.addScorecard')}</button>
                </div>
            </form>

            {scorecards.map((scorecard) => (
                <div key={scorecard.id} className="rounded-xl border border-slate-200 p-4">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p className="font-semibold text-slate-900">{scorecard.name}</p>
                            <p className="text-sm text-slate-500">
                                {t('companies.totalWeight', { weight: scorecard.total_weight })}
                                {scorecard.weight_warning ? ` ${t('companies.weightWarning')}` : ''}
                                {scorecard.is_default ? ` · ${t('common.default')}` : ''}
                                {scorecard.is_active ? '' : ` · ${t('common.inactive')}`}
                            </p>
                        </div>
                        <div className="flex gap-3">
                            {!scorecard.is_default && (
                                <button
                                    type="button"
                                    className="text-sm text-indigo-700"
                                    onClick={() => router.patch(route('companies.scorecards.update', [company.id, scorecard.id]), {
                                        name: scorecard.name,
                                        description: scorecard.description ?? '',
                                        is_default: true,
                                        is_active: scorecard.is_active,
                                    }, { preserveScroll: true })}
                                >
                                    {t('companies.makeDefault')}
                                </button>
                            )}
                            <button type="button" className="text-sm text-red-700" onClick={() => router.delete(route('companies.scorecards.destroy', [company.id, scorecard.id]), { preserveScroll: true })}>{t('common.delete')}</button>
                        </div>
                    </div>
                    <ul className="mt-3 space-y-2 text-sm">
                        {scorecard.criteria.map((item) => (
                            <li key={item.id} className="flex justify-between gap-3">
                                <span>{t('companies.criterionWeight', { name: item.name, key: item.key, weight: item.weight })}</span>
                                <button
                                    type="button"
                                    className="text-red-700"
                                    onClick={() => router.delete(route('companies.scorecards.criteria.destroy', [company.id, scorecard.id, item.id]), { preserveScroll: true })}
                                >
                                    {t('common.remove')}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}

            {scorecards.length > 0 && (
                <form
                    className="grid grid-cols-1 gap-3 md:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const scorecardId = criterion.data.scorecard_id || scorecards[0]?.id;
                        if (!scorecardId) {
                            return;
                        }
                        criterion.post(route('companies.scorecards.criteria.store', [company.id, scorecardId]), {
                            preserveScroll: true,
                            onSuccess: () => criterion.reset('key', 'name', 'description', 'ai_instructions'),
                        });
                    }}
                >
                    <h4 className="md:col-span-2 text-sm font-semibold text-slate-900">{t('companies.addCriterion')}</h4>
                    <Select
                        label={t('companies.scorecard')}
                        value={String(criterion.data.scorecard_id || scorecards[0]?.id || '')}
                        onChange={(v) => criterion.setData('scorecard_id', v)}
                        options={scorecards.map((item) => ({ value: item.id, label: item.name }))}
                    />
                    <Field label={t('common.name')} value={criterion.data.name} onChange={(v) => criterion.setData('name', v)} error={criterion.errors.name} />
                    <Field label={t('companies.criterionKey')} value={criterion.data.key} onChange={(v) => criterion.setData('key', v)} error={criterion.errors.key} />
                    <Field label={t('companies.criterionWeightField')} value={criterion.data.weight} onChange={(v) => criterion.setData('weight', v)} />
                    <div className="md:col-span-2">
                        <Textarea label={t('companies.aiJudge')} value={criterion.data.ai_instructions} onChange={(v) => criterion.setData('ai_instructions', v)} />
                    </div>
                    <div className="md:col-span-2 flex justify-end">
                        <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('companies.addCriterion')}</button>
                    </div>
                </form>
            )}
        </section>
    );
}

function EmployeesList({ company, employees }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <div className="flex justify-between">
                <h3 className="text-base font-semibold text-slate-900">{t('companies.employees')}</h3>
                <Link href={`${route('employees.index')}?company_id=${company.id}`} className="text-sm text-indigo-700">{t('companies.openEmployees')}</Link>
            </div>
            <ul className="mt-4 space-y-2 text-sm">
                {employees.map((employee) => (
                    <li key={employee.id}>
                        <Link href={route('employees.show', employee.id)} className="text-indigo-700">{employee.full_name}</Link>
                        {employee.position ? ` · ${employee.position}` : ''}
                    </li>
                ))}
                {employees.length === 0 && <p className="text-slate-500">{t('companies.noEmployees')}</p>}
            </ul>
        </section>
    );
}

function CallsList({ calls }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-base font-semibold text-slate-900">{t('companies.recentCalls')}</h3>
            <ul className="mt-4 space-y-2 text-sm">
                {calls.map((call) => (
                    <li key={call.id}>
                        <Link href={call.show_url} className="text-indigo-700">#{call.id}</Link>
                        {' '}{call.original_filename || t('companies.callFallback')} · {t.status(call.status)}
                    </li>
                ))}
                {calls.length === 0 && <p className="mt-3 text-slate-500">{t('companies.noCalls')}</p>}
            </ul>
        </section>
    );
}

function CrudCard({ title, active, onDelete, children }) {
    const t = useT();

    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-slate-900">{title}</p>
                    <p className="text-xs text-slate-500">{active ? t('common.active') : t('common.inactive')}</p>
                </div>
                <button type="button" className="text-sm text-red-700" onClick={onDelete}>{t('common.delete')}</button>
            </div>
            <div className="mt-2">{children}</div>
        </div>
    );
}

function Item({ label, value }) {
    const t = useT();
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-800">{value || t('common.dash')}</dd>
        </div>
    );
}

function Field({ label, value, onChange, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <input value={value} onChange={(e) => onChange(e.target.value)} className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Textarea({ label, value, onChange, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <textarea rows={4} value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Select({ label, value, onChange, options }) {
    const items = options.map((option) => (typeof option === 'string' ? { value: option, label: option } : option));
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <select value={value} onChange={(e) => onChange(e.target.value)} className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                {items.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
        </div>
    );
}
