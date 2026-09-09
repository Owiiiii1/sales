import AdminLayout from '@/Layouts/AdminLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { locale = 'en' } = usePage().props;
    const text = {
        en: {
            title: 'Home',
            body: 'This is a placeholder home page. Login is successful.',
        },
        ru: {
            title: 'Главная',
            body: 'Это заглушка главной страницы. Вход выполнен успешно.',
        },
        uk: {
            title: 'Головна',
            body: 'Це заглушка головної сторінки. Вхід виконано успішно.',
        },
    };
    const t = text[locale] ?? text.en;

    return (
        <AdminLayout title={t.title}>
            <Head title="Dashboard" />
            <div className="app-widget p-4">
                <p className="text-sm text-slate-700">{t.body}</p>
            </div>
        </AdminLayout>
    );
}
