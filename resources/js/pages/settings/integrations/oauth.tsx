import { Form, Head, router, usePage } from '@inertiajs/react';
import { Copy, Loader2, Plus, RotateCw, Trash2 } from 'lucide-react';
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
type App = { id: number; name: string; client_id: string; redirect_uris: string[]; scopes: string[]; homepage_url: string | null; description: string | null; created_at: string };

export default function OAuthPage({ apps, scopeOptions }: { apps: App[]; scopeOptions: string[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const flash = usePage<{ flash?: { newClientSecret?: string } }>().props.flash;
    const [scopes, setScopes] = useState<string[]>([scopeOptions[0]]);
    const [redirects, setRedirects] = useState('');
    const toggle = (s: string) => setScopes((p) => p.includes(s) ? p.filter((x) => x !== s) : [...p, s]);

    return (
        <>
            <Head title="OAuth apps — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="OAuth apps" description="Third-party developer apps that other users can authorise into this org's data." />

                {flash?.newClientSecret && (
                    <Card className="border-primary">
                        <CardHeader><CardTitle className="text-base">Client secret</CardTitle></CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">Save it now — only the hash is stored.</p>
                            <div className="mt-2 flex items-center gap-2 rounded-md border bg-muted/30 p-2"><code className="flex-1 break-all font-mono text-sm">{flash.newClientSecret}</code><Button size="sm" variant="ghost" onClick={() => navigator.clipboard.writeText(flash.newClientSecret!).then(() => toast.success('Copied.'))}><Copy className="size-3.5" /></Button></div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Create OAuth app</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/integrations/oauth" method="post" onError={() => toast.error('Check fields.')}>
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    {scopes.map((s) => <input key={s} type="hidden" name="scopes[]" value={s} />)}
                                    {redirects.split(/\s+/).filter(Boolean).map((u, i) => <input key={i} type="hidden" name="redirect_uris[]" value={u} />)}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2"><FieldLabel htmlFor="name" error={errors.name}>App name</FieldLabel><Input id="name" name="name" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="homepage_url" error={errors.homepage_url}>Homepage URL</FieldLabel><Input id="homepage_url" name="homepage_url" type="url" /></div>
                                    </div>
                                    <div className="grid gap-2"><FieldLabel htmlFor="description" error={errors.description}>Description</FieldLabel><Textarea id="description" name="description" rows={3} /></div>
                                    <div className="grid gap-2"><FieldLabel error={errors.redirect_uris}>Redirect URIs (whitespace-separated, up to 5)</FieldLabel><Textarea value={redirects} onChange={(e) => setRedirects(e.target.value)} rows={3} placeholder="https://app.example.com/oauth/callback" /></div>
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
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Create'}</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Your apps ({apps.length})</CardTitle></CardHeader>
                    <CardContent>
                        {apps.length === 0 ? <p className="text-sm text-muted-foreground">No OAuth apps yet.</p> : (
                            <ul className="space-y-3">
                                {apps.map((a) => (
                                    <li key={a.id} className="rounded-md border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">{a.name}</p>
                                                <p className="font-mono text-xs text-muted-foreground">client_id: {a.client_id}</p>
                                                {a.homepage_url && <p className="text-xs"><a href={a.homepage_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">{a.homepage_url}</a></p>}
                                                <div className="mt-1 flex flex-wrap gap-1">{a.scopes.map((s) => <Badge key={s} variant="secondary" className="text-[10px]">{s}</Badge>)}</div>
                                                <p className="mt-1 text-xs text-muted-foreground">Redirects: {a.redirect_uris.length}</p>
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/integrations/oauth/${a.id}/rotate`)}><RotateCw className="size-3.5" /> Rotate</Button>
                                                <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: `Revoke "${a.name}"?`, tone: 'destructive' })) router.delete(`/settings/integrations/oauth/${a.id}`); }}><Trash2 className="size-3.5" /></Button>
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

OAuthPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
