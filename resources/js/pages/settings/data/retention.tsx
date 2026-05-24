import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import { Button, Card, CardContent, CardHeader, CardTitle, Checkbox, Input, Label, PageHeading, useToast } from '@ads';

type Breadcrumb = { title: string; href: string };
type Retention = {
    events_days: number;
    attendee_pii_days: number;
    tickets_days: number;
    audit_log_days: number;
    backups_days: number;
    apply_to_existing: boolean;
};

const FIELDS: Array<{ key: keyof Retention; label: string; hint: string; min: number; max: number }> = [
    { key: 'events_days', label: 'Events', hint: 'Past events deleted after this many days.', min: 30, max: 3650 },
    { key: 'attendee_pii_days', label: 'Attendee PII', hint: 'Personal data scrubbed after this many days.', min: 30, max: 3650 },
    { key: 'tickets_days', label: 'Ticket records', hint: 'Required for legal/finance — typically 7 years.', min: 30, max: 3650 },
    { key: 'audit_log_days', label: 'Audit log', hint: 'Audit rows trimmed after this many days.', min: 90, max: 3650 },
    { key: 'backups_days', label: 'Backups', hint: 'Encrypted backups rotated.', min: 7, max: 365 },
];

export default function RetentionPage({ retention }: { retention: Retention; breadcrumbs: Breadcrumb[] }) {
    const { toast } = useToast();
    const [apply, setApply] = useState(retention.apply_to_existing);

    return (
        <>
            <Head title="Retention — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Data retention"
                        description="How long each resource is kept before automatic deletion. Defaults follow common legal recommendations."
                    />
                    <Form
                        action="/settings/data/retention"
                        method="post"
                        onError={() => toast({ type: 'error', title: 'Check fields.' })}
                    >
                        {({ processing, errors }) => (
                            <Card>
                                <CardHeader>
                                    <CardTitle size="small">Retention windows (days)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <input type="hidden" name="apply_to_existing" value={apply ? '1' : '0'} />

                                    <Box
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
                                            gap: 'var(--ds-space-200)',
                                        }}
                                    >
                                        {FIELDS.map((f) => (
                                            <Stack key={f.key} space="space.050">
                                                <Label htmlFor={f.key}>{f.label}</Label>
                                                <Input
                                                    id={f.key}
                                                    name={f.key}
                                                    type="number"
                                                    defaultValue={String(retention[f.key])}
                                                    isInvalid={!!errors[f.key]}
                                                />
                                                {errors[f.key] ? (
                                                    <Text size="small" color="color.text.danger">
                                                        {errors[f.key]}
                                                    </Text>
                                                ) : (
                                                    <Text size="small" color="color.text.subtle">
                                                        {f.hint}
                                                    </Text>
                                                )}
                                            </Stack>
                                        ))}
                                    </Box>

                                    <Box
                                        padding="space.150"
                                        style={{
                                            borderRadius: 'var(--ds-border-radius-200)',
                                            border: '1px solid var(--ds-border)',
                                        }}
                                    >
                                        <Inline space="space.150" alignBlock="start">
                                            <Checkbox
                                                name="apply_to_existing_checkbox"
                                                label=""
                                                isChecked={apply}
                                                onChange={(e) => setApply(e.currentTarget.checked)}
                                            />
                                            <Stack space="space.025">
                                                <Text weight="semibold">Apply to existing data</Text>
                                                <Text size="small" color="color.text.subtle">
                                                    If unchecked, the new policy only applies to data added from now on.
                                                </Text>
                                            </Stack>
                                        </Inline>
                                    </Box>

                                    <Inline space="space.100">
                                        <Button variant="primary" type="submit" isLoading={processing} isDisabled={processing}>
                                            Save retention policy
                                        </Button>
                                    </Inline>
                                </CardContent>
                            </Card>
                        )}
                    </Form>
                </Stack>
            </Box>
        </>
    );
}

RetentionPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
