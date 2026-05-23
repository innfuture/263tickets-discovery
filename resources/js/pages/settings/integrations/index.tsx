import { Head, router } from '@inertiajs/react';
import { Check, Plug } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };
type Provider = { key: string; name: string; category: string; description: string; scopes: string[]; is_connected: boolean };

export default function IntegrationsPage({ catalogue }: { catalogue: Provider[]; breadcrumbs: Breadcrumb[] }) {
    const [active, setActive] = useState<Provider | null>(null);
    const [label, setLabel] = useState('');

    const byCategory = catalogue.reduce<Record<string, Provider[]>>((acc, p) => { (acc[p.category] ||= []).push(p); return acc; }, {});

    const connect = () => {
        if (!active || !label) return;
        router.post(`/settings/integrations/${active.key}/connect`, { account_label: label }, { onSuccess: () => { setActive(null); setLabel(''); }, onError: () => toast.error('Check fields.') });
    };

    return (
        <>
            <Head title="Integrations — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Integrations" description="Connect third-party services to extend the platform." />

                {Object.entries(byCategory).map(([category, providers]) => (
                    <Card key={category}>
                        <CardHeader><CardTitle className="text-base">{category}</CardTitle></CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {providers.map((p) => (
                                <div key={p.key} className="rounded-md border p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-start gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-background">
                                                <BrandIcon
                                                    provider={p.key}
                                                    size={20}
                                                />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">{p.name}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">{p.description}</p>
                                            </div>
                                        </div>
                                        {p.is_connected ? <Badge><Check className="mr-1 size-3" /> connected</Badge> : <Button size="sm" variant="outline" onClick={() => setActive(p)}><Plug className="size-3.5" /> Connect</Button>}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Dialog open={active !== null} onOpenChange={(o) => !o && setActive(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {active ? (
                                <BrandIcon
                                    provider={active.key}
                                    size={18}
                                />
                            ) : null}
                            Connect {active?.name}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <p className="text-xs text-muted-foreground">In a production deploy this kicks off the OAuth handshake. Here we register the connection with the account label you choose.</p>
                        <div className="grid gap-2"><FieldLabel htmlFor="label">Account label</FieldLabel><Input id="label" value={label} onChange={(e) => setLabel(e.target.value)} placeholder="Production account" /></div>
                        <div className="text-xs text-muted-foreground">Granted scopes: {active?.scopes.join(', ')}</div>
                        <div className="flex justify-end gap-2"><Button variant="ghost" onClick={() => setActive(null)}>Cancel</Button><Button onClick={connect}>Connect</Button></div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

IntegrationsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
