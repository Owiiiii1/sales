import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

export default function CalendarIndex({ orders = [] }) {
    const groupedOrders = useMemo(() => {
        const grouped = {};

        for (const order of orders) {
            if (!order.scheduled_at) {
                continue;
            }

            const dateKey = order.scheduled_at.slice(0, 10);
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(order);
        }

        return Object.entries(grouped)
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([date, items]) => ({
                date,
                items: [...items].sort((left, right) => left.scheduled_at.localeCompare(right.scheduled_at)),
            }));
    }, [orders]);

    return (
        <AdminLayout title="Calendar">
            <Head title="Calendar" />

            <div className="space-y-4">
                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Calendar timeline</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Orders are grouped by scheduled date.
                    </p>
                </section>

                {groupedOrders.length === 0 ? (
                    <section className="app-widget p-4">
                        <p className="text-sm text-slate-600">No scheduled orders yet.</p>
                    </section>
                ) : (
                    groupedOrders.map((group) => (
                        <section key={group.date} className="app-widget p-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                {group.date}
                            </h3>
                            <div className="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <Th>Time</Th>
                                            <Th>Title</Th>
                                            <Th>Customer</Th>
                                            <Th>Service</Th>
                                            <Th>Status</Th>
                                            <Th>Staff</Th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {group.items.map((order) => (
                                            <tr key={order.id}>
                                                <Td>{formatTime(order.scheduled_at)}</Td>
                                                <Td>{order.title}</Td>
                                                <Td>{order.customer_name || '—'}</Td>
                                                <Td>{order.service_name || '—'}</Td>
                                                <Td>{order.status}</Td>
                                                <Td>{(order.staff_names ?? []).join(', ') || '—'}</Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AdminLayout>
    );
}

function formatTime(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch {
        return value;
    }
}

function Th({ children }) {
    return <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</th>;
}

function Td({ children }) {
    return <td className="px-4 py-3 text-slate-700">{children}</td>;
}
