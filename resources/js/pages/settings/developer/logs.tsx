import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Log = { id: number; endpoint: string; method: string; status_code: number; duration_ms: number; ip_address: string | null; api_key: { id: number; name: string; prefix: string } | null; request_preview: unknown; response_preview: unknown; created_at: string };

export default function ApiLogsPage({ logs, filters, keys }: { logs: { data: Log[]; meta: { current_page: number; last_page: number; total: number } }; filters: { q?: string; method?: string; status?: string; api_key_id?: number }; keys: Array<{ id: number; name: string }>; breadcrumbs: Breadcrumb[] }) {
    // Radix Select treats "" as "no value" and forbids it on SelectItem,
    // so use ANY as the sentinel and translate at submit time.
    const ANY = '__any';
    const [q, setQ] = useState(filters.q ?? '');
    const [method, setMethod] = useState(filters.method ?? ANY);
    const [status, setStatus] = useState(filters.status ?? ANY);
    const [apiKeyId, setApiKeyId] = useState<string>(filters.api_key_id ? String(filters.api_key_id) : ANY);
    const [expanded, setExpanded] = useState<number | null>(null);

    const apply = () => router.get('/settings/developer/logs', {
        q: q || undefined,
        method: method === ANY ? undefined : method,
        status: status === ANY ? undefined : status,
        api_key_id: apiKeyId === ANY ? undefined : apiKeyId,
    }, { preserveState: true, replace: true });

    const statusVariant = (code: number): 'default' | 'destructive' | 'secondary' => code < 300 ? 'default' : code < 500 ? 'secondary' : 'destructive';

    return (
        <>
            <Head title="API logs — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="API logs" description="Recent API calls against this organization." />

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-2 pt-6">
                        <Input className="max-w-xs" placeholder="Endpoint…" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply()} />
                        <Select value={method} onValueChange={setMethod}><SelectTrigger className="w-28"><SelectValue placeholder="Method" /></SelectTrigger><SelectContent><SelectItem value={ANY}>Any</SelectItem><SelectItem value="GET">GET</SelectItem><SelectItem value="POST">POST</SelectItem><SelectItem value="PATCH">PATCH</SelectItem><SelectItem value="PUT">PUT</SelectItem><SelectItem value="DELETE">DELETE</SelectItem></SelectContent></Select>
                        <Select value={status} onValueChange={setStatus}><SelectTrigger className="w-28"><SelectValue placeholder="Status" /></SelectTrigger><SelectContent><SelectItem value={ANY}>Any</SelectItem><SelectItem value="2xx">2xx</SelectItem><SelectItem value="3xx">3xx</SelectItem><SelectItem value="4xx">4xx</SelectItem><SelectItem value="5xx">5xx</SelectItem></SelectContent></Select>
                        <Select value={apiKeyId} onValueChange={setApiKeyId}><SelectTrigger className="w-48"><SelectValue placeholder="Any key" /></SelectTrigger><SelectContent><SelectItem value={ANY}>Any key</SelectItem>{keys.map((k) => <SelectItem key={k.id} value={String(k.id)}>{k.name}</SelectItem>)}</SelectContent></Select>
                        <Button size="sm" onClick={apply}><Search className="size-3.5" /> Filter</Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">{logs.meta.total} calls</CardTitle></CardHeader>
                    <CardContent>
                        <table className="w-full text-xs">
                            <thead><tr className="text-left text-muted-foreground"><th className="p-2">When</th><th>Method</th><th>Endpoint</th><th>Status</th><th>Lat.</th><th>Key</th><th>IP</th></tr></thead>
                            <tbody>
                                {logs.data.map((l) => (
                                    <Fragment key={l.id}>
                                        <tr className="cursor-pointer border-t hover:bg-muted/30" onClick={() => setExpanded(expanded === l.id ? null : l.id)}>
                                            <td className="p-2">{new Date(l.created_at).toLocaleString()}</td>
                                            <td className="font-mono">{l.method}</td>
                                            <td className="font-mono">{l.endpoint}</td>
                                            <td><Badge variant={statusVariant(l.status_code)}>{l.status_code}</Badge></td>
                                            <td>{l.duration_ms}ms</td>
                                            <td className="text-muted-foreground">{l.api_key?.prefix ?? '—'}</td>
                                            <td className="text-muted-foreground">{l.ip_address ?? '—'}</td>
                                        </tr>
                                        {expanded === l.id && (
                                            <tr><td colSpan={7} className="bg-muted/30 p-3">
                                                <div className="grid gap-2 md:grid-cols-2">
                                                    <pre className="overflow-x-auto rounded bg-background p-2 text-[10px]">request: {JSON.stringify(l.request_preview, null, 2)}</pre>
                                                    <pre className="overflow-x-auto rounded bg-background p-2 text-[10px]">response: {JSON.stringify(l.response_preview, null, 2)}</pre>
                                                </div>
                                            </td></tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                        {logs.meta.last_page > 1 && (
                            <div className="mt-3 flex items-center gap-2">
                                <Button size="sm" variant="outline" disabled={logs.meta.current_page === 1} onClick={() => router.get('/settings/developer/logs', { ...filters, page: logs.meta.current_page - 1 }, { preserveState: true })}>Prev</Button>
                                <span className="text-xs text-muted-foreground">{logs.meta.current_page} / {logs.meta.last_page}</span>
                                <Button size="sm" variant="outline" disabled={logs.meta.current_page === logs.meta.last_page} onClick={() => router.get('/settings/developer/logs', { ...filters, page: logs.meta.current_page + 1 }, { preserveState: true })}>Next</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ApiLogsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
