import { Form, Head, router } from '@inertiajs/react';
import { CheckCircle2, Copy, Loader2, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };

type Domain = {
    hostname: string | null;
    mode: 'subdomain' | 'apex';
    verification_token: string | null;
    verification_status: 'unconfigured' | 'pending' | 'verified' | 'failed';
    ssl_status: 'unconfigured' | 'pending' | 'active' | 'failed';
    verified_at: string | null;
};

export default function DomainPage({
    domain,
}: {
    domain: Domain;
    breadcrumbs: Breadcrumb[];
}) {
    const confirm = useConfirm();
    const [mode, setMode] = useState(domain.mode);
    const copy = async (v: string) => {
        try {
            await navigator.clipboard.writeText(v);
            toast.success('Copied.');
        } catch {
            toast.error("Couldn't copy.");
        }
    };

    return (
        <>
            <Head title="Custom domain — Settings" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Custom domain"
                    description="Host event pages on a domain you own. SSL provisions automatically once your CNAME record is visible."
                />

                <Form action="/settings/organization/domain" method="post" onError={() => toast.error("Couldn't save.")}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Domain</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="hostname" error={errors.hostname}>Hostname</FieldLabel>
                                        <Input id="hostname" name="hostname" defaultValue={domain.hostname ?? ''} placeholder="events.your-org.com" />
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="mode" error={errors.mode}>Mode</FieldLabel>
                                        <input type="hidden" name="mode" value={mode} />
                                        <Select value={mode} onValueChange={(v) => setMode(v as 'subdomain' | 'apex')}>
                                            <SelectTrigger id="mode"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="subdomain">Subdomain (events.your-org.com)</SelectItem>
                                                <SelectItem value="apex">Apex (your-org.com)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save & generate token'}</Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {domain.hostname && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-4" /> Verification</CardTitle>
                            <CardDescription>Add this CNAME at your DNS provider, then click Verify.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-md border bg-muted/30 p-3 font-mono text-xs">
                                <div className="flex items-center justify-between gap-2">
                                    <span>{domain.hostname} CNAME → platform-edge.example.com</span>
                                    <Button size="sm" variant="ghost" onClick={() => copy('platform-edge.example.com')}><Copy className="size-3.5" /></Button>
                                </div>
                                {domain.verification_token && (
                                    <div className="mt-2 flex items-center justify-between gap-2">
                                        <span>TXT _verify.{domain.hostname} → "{domain.verification_token}"</span>
                                        <Button size="sm" variant="ghost" onClick={() => copy(domain.verification_token!)}><Copy className="size-3.5" /></Button>
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <StatusPill label="Verification" status={domain.verification_status} />
                                <StatusPill label="SSL" status={domain.ssl_status} />
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => router.post('/settings/organization/domain/verify')} variant="outline" size="sm">
                                    <CheckCircle2 className="size-3.5" /> Verify now
                                </Button>
                                <Button
                                    onClick={async () => {
                                        const ok = await confirm({ title: 'Remove custom domain?', description: 'Event pages revert to the default platform domain.' });
                                        if (ok) router.delete('/settings/organization/domain');
                                    }}
                                    variant="ghost"
                                    size="sm"
                                >
                                    <Trash2 className="size-3.5" /> Remove
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function StatusPill({ label, status }: { label: string; status: string }) {
    const variant: 'default' | 'destructive' | 'secondary' = status === 'verified' || status === 'active' ? 'default' : status === 'failed' ? 'destructive' : 'secondary';
    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">{label}:</span>
            <Badge variant={variant}>{status}</Badge>
        </div>
    );
}

DomainPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
