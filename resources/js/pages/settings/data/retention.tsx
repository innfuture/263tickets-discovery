import { Form, Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

type Breadcrumb = { title: string; href: string };
type Retention = { events_days: number; attendee_pii_days: number; tickets_days: number; audit_log_days: number; backups_days: number; apply_to_existing: boolean };

const FIELDS: Array<{ key: keyof Retention; label: string; hint: string; min: number; max: number }> = [
    { key: 'events_days', label: 'Events', hint: 'Past events deleted after this many days.', min: 30, max: 3650 },
    { key: 'attendee_pii_days', label: 'Attendee PII', hint: 'Personal data scrubbed after this many days.', min: 30, max: 3650 },
    { key: 'tickets_days', label: 'Ticket records', hint: 'Required for legal/finance — typically 7 years.', min: 30, max: 3650 },
    { key: 'audit_log_days', label: 'Audit log', hint: 'Audit rows trimmed after this many days.', min: 90, max: 3650 },
    { key: 'backups_days', label: 'Backups', hint: 'Encrypted backups rotated.', min: 7, max: 365 },
];

export default function RetentionPage({ retention }: { retention: Retention; breadcrumbs: Breadcrumb[] }) {
    const [apply, setApply] = useState(retention.apply_to_existing);
    return (
        <>
            <Head title="Retention — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Data retention" description="How long each resource is kept before automatic deletion. Defaults follow common legal recommendations." />
                <Form action="/settings/data/retention" method="post" onError={() => toast.error('Check fields.')}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Retention windows (days)</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" name="apply_to_existing" value={apply ? '1' : '0'} />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {FIELDS.map((f) => (
                                        <div key={f.key} className="grid gap-1">
                                            <FieldLabel htmlFor={f.key} error={errors[f.key]}>{f.label}</FieldLabel>
                                            <Input id={f.key} name={f.key} type="number" min={f.min} max={f.max} defaultValue={retention[f.key] as number} />
                                            <p className="text-xs text-muted-foreground">{f.hint}</p>
                                        </div>
                                    ))}
                                </div>
                                <label className="flex items-start gap-3 rounded-md border p-3">
                                    <Checkbox checked={apply} onCheckedChange={(v) => setApply(Boolean(v))} />
                                    <span className="text-sm"><span className="font-medium">Apply to existing data</span><br /><span className="text-xs text-muted-foreground">If unchecked, the new policy only applies to data added from now on.</span></span>
                                </label>
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save retention policy'}</Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

RetentionPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
