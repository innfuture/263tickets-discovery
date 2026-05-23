import { Form, Head, router, usePage } from '@inertiajs/react';
import { Copy, Loader2, Plus, Trash2 } from 'lucide-react';
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

type Breadcrumb = { title: string; href: string };
type Scope = { key: string; label: string; hint: string };
type Token = { id: number; name: string; prefix: string; scopes: string[]; last_used_at: string | null; expires_at: string | null; revoked_at: string | null; created_at: string; is_active: boolean };

export default function ApiTokensPage({ tokens, scopeOptions }: { tokens: Token[]; scopeOptions: Scope[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const flash = usePage<{ flash?: { newToken?: string } }>().props.flash;
    const [scopes, setScopes] = useState<string[]>([scopeOptions[0].key]);
    const toggle = (k: string) => setScopes((p) => p.includes(k) ? p.filter((x) => x !== k) : [...p, k]);

    return (
        <>
            <Head title="API tokens — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Personal API tokens" description="Tokens you use to call the platform API as yourself. Inherit your effective permissions." />

                {flash?.newToken && (
                    <Card className="border-primary">
                        <CardHeader><CardTitle className="text-base">Your new token</CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            <p className="text-xs text-muted-foreground">Copy this now — you won't see it again.</p>
                            <div className="flex items-center gap-2 rounded-md border bg-muted/30 p-3">
                                <code className="flex-1 break-all font-mono text-sm">{flash.newToken}</code>
                                <Button size="sm" variant="ghost" onClick={() => navigator.clipboard.writeText(flash.newToken!).then(() => toast.success('Copied.'))}><Copy className="size-3.5" /></Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Create token</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/api-tokens" method="post" onError={() => toast.error('Check fields.')} className="space-y-4">
                            {({ processing, errors }) => (
                                <>
                                    {scopes.map((s) => <input key={s} type="hidden" name="scopes[]" value={s} />)}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <FieldLabel htmlFor="name" error={errors.name}>Name</FieldLabel>
                                            <Input id="name" name="name" placeholder="My CLI" />
                                        </div>
                                        <div className="grid gap-2">
                                            <FieldLabel htmlFor="expires_in_days" error={errors.expires_in_days}>Expires in (days)</FieldLabel>
                                            <Input id="expires_in_days" name="expires_in_days" type="number" min={1} max={365} placeholder="never" />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel error={errors.scopes}>Scopes</FieldLabel>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {scopeOptions.map((s) => (
                                                <label key={s.key} className="flex items-start gap-2 rounded-md border p-2 text-sm">
                                                    <Checkbox checked={scopes.includes(s.key)} onCheckedChange={() => toggle(s.key)} />
                                                    <div><p className="font-medium">{s.label}</p><p className="text-xs text-muted-foreground">{s.hint}</p></div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Create token'}</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Existing tokens ({tokens.length})</CardTitle></CardHeader>
                    <CardContent>
                        {tokens.length === 0 ? <p className="text-sm text-muted-foreground">No tokens yet.</p> : (
                            <ul className="space-y-2">
                                {tokens.map((t) => (
                                    <li key={t.id} className="flex items-start justify-between rounded-md border p-3">
                                        <div>
                                            <p className="text-sm font-medium">{t.name} <code className="ml-2 rounded bg-muted px-1 font-mono text-xs">{t.prefix}…</code></p>
                                            <p className="text-xs text-muted-foreground">Scopes: {t.scopes.join(', ') || '—'}</p>
                                            <p className="text-xs text-muted-foreground">Created {new Date(t.created_at).toLocaleDateString()} · {t.last_used_at ? `Last used ${new Date(t.last_used_at).toLocaleDateString()}` : 'Never used'}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!t.is_active && <Badge variant="destructive">revoked</Badge>}
                                            {t.is_active && (
                                                <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: `Revoke "${t.name}"?`, tone: 'destructive' })) router.delete(`/settings/api-tokens/${t.id}`); }}>
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
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

ApiTokensPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
