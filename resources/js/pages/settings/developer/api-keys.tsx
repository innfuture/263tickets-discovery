import { Form, Head, router, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Loader2, Plus, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Key = { id: number; name: string; prefix: string; scopes: string[]; ip_allowlist: string[]; last_used_at: string | null; expires_at: string | null; revoked_at: string | null; created_by: string | null; created_at: string; is_active: boolean };

export default function ApiKeysPage({ keys, scopeOptions }: { keys: Key[]; scopeOptions: string[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const flash = usePage<{ flash?: { newApiKey?: string } }>().props.flash;
    const [scopes, setScopes] = useState<string[]>([scopeOptions[0]]);
    const [ipText, setIpText] = useState('');
    const toggle = (s: string) => setScopes((p) => p.includes(s) ? p.filter((x) => x !== s) : [...p, s]);

    return (
        <>
            <Head title="API keys — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="API keys" description="Server-to-server keys scoped to this organization." />

                {flash?.newApiKey && (
                    <Card className="border-primary">
                        <CardHeader><CardTitle className="text-base">Your new key</CardTitle></CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">Copy now — only the hash is stored.</p>
                            <div className="mt-2 flex items-center gap-2 rounded-md border bg-muted/30 p-2"><code className="flex-1 break-all font-mono text-sm">{flash.newApiKey}</code><Button size="sm" variant="ghost" onClick={() => navigator.clipboard.writeText(flash.newApiKey!).then(() => toast.success('Copied.'))}><Copy className="size-3.5" /></Button></div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Create key</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/developer/api-keys" method="post" onError={() => toast.error('Check fields.')}>
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    {scopes.map((s) => <input key={s} type="hidden" name="scopes[]" value={s} />)}
                                    {ipText.split(/\s+/).filter(Boolean).map((ip, i) => <input key={i} type="hidden" name="ip_allowlist[]" value={ip} />)}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2"><FieldLabel htmlFor="name" error={errors.name}>Name</FieldLabel><Input id="name" name="name" placeholder="Production server" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="expires_in_days" error={errors.expires_in_days}>Expires in (days)</FieldLabel><Input id="expires_in_days" name="expires_in_days" type="number" min={1} max={365} placeholder="never" /></div>
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel error={errors.scopes}>Scopes</FieldLabel>
                                        <div className="grid gap-1 sm:grid-cols-2">
                                            {scopeOptions.map((s) => (
                                                <label key={s} className="flex items-center gap-2 rounded-md border p-2 text-sm">
                                                    <Checkbox checked={scopes.includes(s)} onCheckedChange={() => toggle(s)} />
                                                    <code className="font-mono text-xs">{s}</code>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="grid gap-2"><FieldLabel htmlFor="ip">IP allowlist (one per line)</FieldLabel><Textarea id="ip" value={ipText} onChange={(e) => setIpText(e.target.value)} rows={3} placeholder="203.0.113.4" /></div>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Create key'}</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><KeyRound className="size-4" /> Keys ({keys.length})</CardTitle></CardHeader>
                    <CardContent>
                        {keys.length === 0 ? <p className="text-sm text-muted-foreground">No keys yet.</p> : (
                            <ul className="space-y-2">
                                {keys.map((k) => (
                                    <li key={k.id} className="rounded-md border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium">{k.name} <code className="ml-2 rounded bg-muted px-1 font-mono text-xs">{k.prefix}…</code></p>
                                                <div className="mt-1 flex flex-wrap gap-1">{k.scopes.map((s) => <Badge key={s} variant="secondary" className="text-[10px]">{s}</Badge>)}</div>
                                                <p className="mt-1 text-xs text-muted-foreground">By {k.created_by ?? '—'} · Created {new Date(k.created_at).toLocaleDateString()} · {k.last_used_at ? `Last used ${new Date(k.last_used_at).toLocaleDateString()}` : 'Never used'}</p>
                                                {k.ip_allowlist.length > 0 && <p className="text-xs text-muted-foreground">IPs: {k.ip_allowlist.join(', ')}</p>}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {!k.is_active && <Badge variant="destructive">revoked</Badge>}
                                                {k.is_active && (
                                                    <>
                                                        <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/developer/api-keys/${k.id}/rotate`)}><RotateCw className="size-3.5" /></Button>
                                                        <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: `Revoke "${k.name}"?`, tone: 'destructive' })) router.delete(`/settings/developer/api-keys/${k.id}`); }}><Trash2 className="size-3.5" /></Button>
                                                    </>
                                                )}
                                            </div>
                                        </div>
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

ApiKeysPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
