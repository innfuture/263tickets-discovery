import { Form, Head, router } from '@inertiajs/react';
import { CheckCircle2, Copy, Loader2, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };
type Identity = { domain: string | null; from_name: string; reply_to: string | null; verification_status: string; dkim_selector: string | null; dkim_value: string | null; spf_value: string | null; verified_at: string | null; bounce_rate_30d: number };

export default function EmailIdentityPage({ identity }: { identity: Identity; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const copy = (v: string) => navigator.clipboard.writeText(v).then(() => toast.success('Copied.'));
    const variant: 'default' | 'destructive' | 'secondary' = identity.verification_status === 'verified' ? 'default' : identity.verification_status === 'failed' ? 'destructive' : 'secondary';

    return (
        <>
            <Head title="Email sender identity — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Email sender identity" description="Verify a domain so attendee emails arrive from your own brand with DKIM + SPF passing." />

                <Form action="/settings/operations/email-identity" method="post" onError={() => toast.error("Couldn't save.")}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Domain</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="domain" error={errors.domain}>Sending domain</FieldLabel>
                                        <Input id="domain" name="domain" defaultValue={identity.domain ?? ''} placeholder="your-org.com" />
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="from_name" error={errors.from_name}>From name</FieldLabel>
                                        <Input id="from_name" name="from_name" defaultValue={identity.from_name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="reply_to" error={errors.reply_to}>Reply-to (optional)</FieldLabel>
                                        <Input id="reply_to" name="reply_to" type="email" defaultValue={identity.reply_to ?? ''} />
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save'}</Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {identity.domain && identity.dkim_value && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">DNS records</CardTitle>
                            <CardDescription>Add the records below at your DNS host, then click Verify.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Record label={`TXT ${identity.dkim_selector}._domainkey.${identity.domain}`} value={identity.dkim_value!} onCopy={copy} />
                            <Record label={`TXT ${identity.domain}`} value={identity.spf_value ?? ''} onCopy={copy} />
                            <div className="flex items-center gap-3">
                                <Badge variant={variant}>{identity.verification_status}</Badge>
                                {identity.verified_at && <span className="text-xs text-muted-foreground">verified {new Date(identity.verified_at).toLocaleString()}</span>}
                                <span className="ml-auto text-xs text-muted-foreground">Bounce rate (30d): {identity.bounce_rate_30d}%</span>
                            </div>
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" onClick={() => router.post('/settings/operations/email-identity/verify')}><CheckCircle2 className="size-3.5" /> Verify now</Button>
                                <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: 'Remove email identity?' })) router.delete('/settings/operations/email-identity'); }}><Trash2 className="size-3.5" /> Remove</Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Record({ label, value, onCopy }: { label: string; value: string; onCopy: (v: string) => void }) {
    return (
        <div className="rounded-md border bg-muted/30 p-3 font-mono text-xs">
            <p className="text-muted-foreground">{label}</p>
            <div className="mt-1 flex items-start gap-2">
                <code className="flex-1 break-all">{value}</code>
                <Button size="sm" variant="ghost" type="button" onClick={() => onCopy(value)}><Copy className="size-3.5" /></Button>
            </div>
        </div>
    );
}

EmailIdentityPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
