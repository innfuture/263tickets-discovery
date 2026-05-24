import { Head, Link } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, Bell, Inbox, Info } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };

type NotifItem = {
    id: string;
    severity: 'info' | 'warning' | 'critical';
    category: 'events' | 'sales' | 'payments' | 'operations';
    title: string;
    body: string;
    link: string;
    occurred_at: string;
};

type Props = {
    items: NotifItem[];
    counts: { total: number; critical: number; warning: number; info: number };
    breadcrumbs: Breadcrumb[];
};

const SEVERITY_ICON: Record<NotifItem['severity'], React.ReactNode> = {
    critical: <AlertCircle className="size-4 text-rose-600" />,
    warning: <AlertTriangle className="size-4 text-amber-600" />,
    info: <Info className="size-4 text-blue-600" />,
};

const SEVERITY_BG: Record<NotifItem['severity'], string> = {
    critical: 'border-l-rose-500 bg-rose-50/40',
    warning: 'border-l-amber-500 bg-amber-50/40',
    info: 'border-l-blue-500 bg-blue-50/30',
};

const STORAGE_KEY = 'organizer.notifications.read';

function loadRead(): Set<string> {
    if (typeof window === 'undefined') return new Set();
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return new Set<string>(raw ? JSON.parse(raw) : []);
    } catch {
        return new Set();
    }
}

function saveRead(s: Set<string>) {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(s)));
}

export default function Notifications({ items, counts }: Props) {
    const [filter, setFilter] = useState<'all' | NotifItem['severity']>('all');
    const [read, setRead] = useState<Set<string>>(loadRead);

    const toggle = (id: string) => {
        const next = new Set(read);
        next.has(id) ? next.delete(id) : next.add(id);
        setRead(next);
        saveRead(next);
    };

    const markAll = () => {
        const next = new Set([...read, ...items.map((i) => i.id)]);
        setRead(next);
        saveRead(next);
    };

    const visible = items.filter((i) => filter === 'all' || i.severity === filter);

    return (
        <>
            <Head title="Notifications" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Notifications"
                        description="Action items derived from your events, sales, payments, and operations."
                    />
                    {items.length > 0 && (
                        <Button size="sm" variant="secondary" onClick={markAll}>
                            Mark all read
                        </Button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <FilterTile
                        icon={<Inbox className="size-4" />}
                        label="All"
                        value={counts.total}
                        active={filter === 'all'}
                        onClick={() => setFilter('all')}
                    />
                    <FilterTile
                        icon={<AlertCircle className="size-4 text-rose-600" />}
                        label="Critical"
                        value={counts.critical}
                        active={filter === 'critical'}
                        onClick={() => setFilter('critical')}
                    />
                    <FilterTile
                        icon={<AlertTriangle className="size-4 text-amber-600" />}
                        label="Warning"
                        value={counts.warning}
                        active={filter === 'warning'}
                        onClick={() => setFilter('warning')}
                    />
                    <FilterTile
                        icon={<Info className="size-4 text-blue-600" />}
                        label="Info"
                        value={counts.info}
                        active={filter === 'info'}
                        onClick={() => setFilter('info')}
                    />
                </div>

                <Card>
                    <CardContent className="p-0">
                        {visible.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                                <Bell className="size-8 opacity-40" />
                                <p>No notifications. Everything looks healthy.</p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {visible.map((n) => {
                                    const isRead = read.has(n.id);
                                    return (
                                        <li
                                            key={n.id}
                                            className={`border-l-2 p-4 ${SEVERITY_BG[n.severity]} ${
                                                isRead ? 'opacity-60' : ''
                                            }`}
                                        >
                                            <div className="flex items-start gap-3">
                                                {SEVERITY_ICON[n.severity]}
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <Link href={n.link} className="font-medium hover:underline">
                                                            {n.title}
                                                        </Link>
                                                        <span className="text-[11px] uppercase text-muted-foreground">
                                                            {n.category}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">{n.body}</p>
                                                    <div className="mt-1 flex items-center justify-between">
                                                        <span className="text-xs text-muted-foreground">
                                                            {n.occurred_at}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => toggle(n.id)}
                                                            className="text-xs text-muted-foreground hover:underline"
                                                        >
                                                            Mark as {isRead ? 'unread' : 'read'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function FilterTile({
    icon,
    label,
    value,
    active,
    onClick,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-3 rounded-md border bg-card p-3 text-left transition ${
                active ? 'ring-2 ring-primary' : 'hover:bg-muted/40'
            }`}
        >
            <span className="rounded bg-muted p-2 text-muted-foreground">{icon}</span>
            <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="text-lg font-semibold">{value}</p>
            </div>
        </button>
    );
}

Notifications.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
