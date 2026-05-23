import { Head, router } from '@inertiajs/react';
import { Plug, Trash2 } from 'lucide-react';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };
type Connection = { id: number; provider: string; provider_name: string; category: string; account_label: string | null; scopes: string[]; connected_by: string | null; connected_at: string | null };

export default function ConnectedPage({ connections }: { connections: Connection[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    return (
        <>
            <Head title="Connected apps — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Connected apps" description="Apps already connected to this organization." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plug className="size-4" /> Connections ({connections.length})</CardTitle></CardHeader>
                    <CardContent>
                        {connections.length === 0 ? <p className="text-sm text-muted-foreground">No connected apps.</p> : (
                            <ul className="space-y-2">
                                {connections.map((c) => (
                                    <li key={c.id} className="flex items-start justify-between rounded-md border p-3">
                                        <div className="flex items-start gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-background">
                                                <BrandIcon provider={c.provider} size={20} />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">{c.provider_name} <Badge variant="secondary" className="ml-2">{c.category}</Badge></p>
                                                <p className="text-xs text-muted-foreground">{c.account_label ?? '—'} · Connected by {c.connected_by ?? '—'}{c.connected_at ? ` on ${new Date(c.connected_at).toLocaleDateString()}` : ''}</p>
                                                <div className="mt-1 flex flex-wrap gap-1">{c.scopes.map((s) => <Badge key={s} variant="outline" className="text-[10px]">{s}</Badge>)}</div>
                                            </div>
                                        </div>
                                        <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: `Disconnect ${c.provider_name}?`, tone: 'destructive' })) router.delete(`/settings/integrations/connected/${c.id}`); }}><Trash2 className="size-3.5" /></Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ConnectedPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
