import { Form, Head, router, usePage } from '@inertiajs/react';
import { Loader2, ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };
type TwoFactor = { enrolled: boolean; confirmed: boolean; otpauthUri: string | null; recoveryCodes: string[] };

export default function SecurityPage({ twoFactor, allowlist, lastLogin }: { twoFactor: TwoFactor; allowlist: string[]; lastLogin: { at: string | null; ip: string | null }; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const flash = usePage<{ flash?: { newRecoveryCodes?: string[] } }>().props.flash;
    const [ips, setIps] = useState<string[]>(allowlist);
    const [newIp, setNewIp] = useState('');

    return (
        <>
            <Head title="Security — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Security" description="Two-factor, recovery codes, and a trusted-IP allowlist for your account." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-4" /> Two-factor auth</CardTitle><CardDescription>{twoFactor.confirmed ? 'On — codes from your authenticator app are required at sign-in.' : 'Off — add a second factor.'}</CardDescription></CardHeader>
                    <CardContent className="space-y-3">
                        {!twoFactor.enrolled && (
                            <Button onClick={() => router.post('/settings/security/two-factor')}>Enable 2FA</Button>
                        )}
                        {twoFactor.enrolled && !twoFactor.confirmed && (
                            <>
                                <p className="text-sm">Scan this URI in your authenticator app, then enter the 6-digit code:</p>
                                <pre className="overflow-x-auto rounded-md border bg-muted/30 p-3 font-mono text-xs">{twoFactor.otpauthUri}</pre>
                                <Form action="/settings/security/two-factor/confirm" method="post" className="flex items-end gap-2">
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <FieldLabel htmlFor="code" error={errors.code}>Code</FieldLabel>
                                                <Input id="code" name="code" inputMode="numeric" maxLength={6} className="w-32 font-mono tracking-widest" />
                                            </div>
                                            <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Confirm'}</Button>
                                        </>
                                    )}
                                </Form>
                                {twoFactor.recoveryCodes.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-medium">Recovery codes (save now!)</p>
                                        <ul className="grid grid-cols-2 gap-1 rounded-md border bg-muted/30 p-3 font-mono text-xs">
                                            {twoFactor.recoveryCodes.map((c) => <li key={c}>{c}</li>)}
                                        </ul>
                                    </div>
                                )}
                            </>
                        )}
                        {twoFactor.confirmed && (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" onClick={() => router.post('/settings/security/two-factor/recovery-codes')}>Regenerate recovery codes</Button>
                                <Button variant="ghost" onClick={async () => { if (await confirm({ title: 'Disable 2FA?', description: 'You\'ll be able to sign in with your password alone.', tone: 'destructive' })) router.delete('/settings/security/two-factor'); }}><ShieldOff className="size-3.5" /> Disable</Button>
                            </div>
                        )}
                        {flash?.newRecoveryCodes && (
                            <ul className="grid grid-cols-2 gap-1 rounded-md border border-primary bg-muted/30 p-3 font-mono text-xs">
                                {flash.newRecoveryCodes.map((c) => <li key={c}>{c}</li>)}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Trusted IP allowlist</CardTitle><CardDescription>If non-empty, sign-in is restricted to these IPs.</CardDescription></CardHeader>
                    <CardContent className="space-y-3">
                        <Form action="/settings/security/allowlist" method="post" onSuccess={() => toast.success('Allowlist saved.')}>
                            {({ processing }) => (
                                <>
                                    {ips.map((ip) => <input key={ip} type="hidden" name="ips[]" value={ip} />)}
                                    <ul className="space-y-2">
                                        {ips.map((ip) => (
                                            <li key={ip} className="flex items-center justify-between rounded-md border px-3 py-2 font-mono text-sm">
                                                <span>{ip}</span>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => setIps((p) => p.filter((x) => x !== ip))}>Remove</Button>
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="flex gap-2">
                                        <Input value={newIp} onChange={(e) => setNewIp(e.target.value)} placeholder="203.0.113.4" />
                                        <Button type="button" variant="outline" onClick={() => { if (newIp) { setIps((p) => Array.from(new Set([...p, newIp]))); setNewIp(''); } }}>Add</Button>
                                    </div>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save allowlist'}</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Last sign-in</CardTitle></CardHeader>
                    <CardContent>
                        <p className="text-sm">{lastLogin.at ? new Date(lastLogin.at).toLocaleString() : 'No recent sessions'}</p>
                        {lastLogin.ip && <p className="text-xs text-muted-foreground">from {lastLogin.ip}</p>}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SecurityPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
