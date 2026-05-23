import { Head, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };
type Entry = { id: number; action: string; resource_type: string | null; resource_id: string | null; actor_type: string; actor_name: string; actor_email: string | null; ip_address: string | null; created_at: string; before: Record<string, unknown> | null; after: Record<string, unknown> | null };

export default function AuditLogPage({ entries, filters }: { entries: { data: Entry[]; meta: { current_page: number; last_page: number; total: number } }; filters: { q?: string; actor_type?: string; resource_type?: string; from?: string; to?: string }; breadcrumbs: Breadcrumb[] }) {
    const [q, setQ] = useState(filters.q ?? '');
    const [actorType, setActorType] = useState(filters.actor_type ?? '');
    const [resourceType, setResourceType] = useState(filters.resource_type ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const apply = () => router.get('/settings/data/audit-log', { q: q || undefined, actor_type: actorType || undefined, resource_type: resourceType || undefined }, { preserveState: true, replace: true });

    return (
        <>
            <Head title="Audit log — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Audit log" description="Every change made inside this organization." />

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-2 pt-6">
                        <Input className="max-w-xs" placeholder="Search…" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply()} />
                        <Input className="w-32" placeholder="actor" value={actorType} onChange={(e) => setActorType(e.target.value)} />
                        <Input className="w-40" placeholder="resource" value={resourceType} onChange={(e) => setResourceType(e.target.value)} />
                        <Button onClick={apply} variant="outline" size="sm"><Search className="size-3.5" /> Filter</Button>
                        <Button asChild variant="ghost" size="sm" className="ml-auto"><a href="/settings/data/audit-log/export"><Download className="size-3.5" /> Export CSV</a></Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">{entries.meta.total} events</CardTitle></CardHeader>
                    <CardContent>
                        <table className="w-full text-xs">
                            <thead><tr className="text-left text-muted-foreground"><th className="p-2">When</th><th>Actor</th><th>Action</th><th>Resource</th><th>IP</th></tr></thead>
                            <tbody>
                                {entries.data.map((e) => (
                                    <Fragment key={e.id}>
                                        <tr className="cursor-pointer border-t hover:bg-muted/30" onClick={() => setExpanded(expanded === e.id ? null : e.id)}>
                                            <td className="p-2">{new Date(e.created_at).toLocaleString()}</td>
                                            <td>{e.actor_name} <Badge variant="outline" className="ml-1 text-[10px]">{e.actor_type}</Badge></td>
                                            <td className="font-mono">{e.action}</td>
                                            <td className="text-muted-foreground">{e.resource_type ?? '—'}{e.resource_id ? `:${e.resource_id}` : ''}</td>
                                            <td className="text-muted-foreground">{e.ip_address ?? '—'}</td>
                                        </tr>
                                        {expanded === e.id && (e.before || e.after) && (
                                            <tr><td colSpan={5} className="bg-muted/30 p-3">
                                                <div className="grid gap-2 md:grid-cols-2">
                                                    <pre className="overflow-x-auto rounded bg-background p-2 text-[11px]">before: {JSON.stringify(e.before, null, 2)}</pre>
                                                    <pre className="overflow-x-auto rounded bg-background p-2 text-[11px]">after: {JSON.stringify(e.after, null, 2)}</pre>
                                                </div>
                                            </td></tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                        {entries.meta.last_page > 1 && (
                            <div className="mt-3 flex items-center gap-2">
                                <Button size="sm" variant="outline" disabled={entries.meta.current_page === 1} onClick={() => router.get('/settings/data/audit-log', { ...filters, page: entries.meta.current_page - 1 }, { preserveState: true })}>Prev</Button>
                                <span className="text-xs text-muted-foreground">{entries.meta.current_page} / {entries.meta.last_page}</span>
                                <Button size="sm" variant="outline" disabled={entries.meta.current_page === entries.meta.last_page} onClick={() => router.get('/settings/data/audit-log', { ...filters, page: entries.meta.current_page + 1 }, { preserveState: true })}>Next</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AuditLogPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
