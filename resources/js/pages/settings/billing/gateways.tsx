import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, Loader2, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };

type Gateway = {
    id: string;
    label: string;
    configured: boolean;
    enabled_globally: boolean;
    supported_currencies: string[];
};

type Props = {
    gateways: Gateway[];
    selection: { default: string; enabled: string[] };
    breadcrumbs: Breadcrumb[];
};

export default function GatewaysPage({ gateways, selection }: Props) {
    const [defaultGateway, setDefaultGateway] = useState<string>(selection.default);
    const [enabled, setEnabled] = useState<string[]>(selection.enabled);

    const toggle = (id: string, on: boolean) => {
        setEnabled((prev) => (on ? Array.from(new Set([...prev, id])) : prev.filter((g) => g !== id)));
    };

    return (
        <>
            <Head title="Payment gateways — Settings" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Payment gateways"
                    description="Pick which payment providers accept charges for this organization."
                />

                <Form
                    action="/settings/billing/gateways"
                    method="post"
                    onError={() => toast.error('Could not save gateway selection.')}
                    onSuccess={() => toast.success('Gateway selection saved.')}
                >
                    {({ processing }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Active providers</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <input type="hidden" name="default" value={defaultGateway} />
                                {enabled.map((g) => (
                                    <input key={g} type="hidden" name="enabled[]" value={g} />
                                ))}

                                <ul className="space-y-2">
                                    {gateways.map((g) => {
                                        const isOn = enabled.includes(g.id);
                                        const blocked = !g.configured;
                                        return (
                                            <li
                                                key={g.id}
                                                className="flex items-center justify-between rounded-md border p-3"
                                            >
                                                <div className="flex items-center gap-3">
                                                    {g.configured ? (
                                                        <CheckCircle2 className="size-4 text-emerald-500" />
                                                    ) : (
                                                        <ShieldAlert className="size-4 text-amber-500" />
                                                    )}
                                                    <div>
                                                        <p className="text-sm font-medium">{g.label}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {g.configured
                                                                ? `Currencies: ${g.supported_currencies.join(', ')}`
                                                                : 'Missing credentials in .env — set them to enable.'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <label
                                                    className={`flex items-center gap-2 ${blocked ? 'opacity-50' : ''}`}
                                                >
                                                    <Checkbox
                                                        checked={isOn}
                                                        disabled={blocked}
                                                        onCheckedChange={(v) => toggle(g.id, Boolean(v))}
                                                    />
                                                    <span className="text-sm">{isOn ? 'On' : 'Off'}</span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="default">Default gateway</FieldLabel>
                                    <Select value={defaultGateway} onValueChange={setDefaultGateway}>
                                        <SelectTrigger className="max-w-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {gateways.map((g) => (
                                                <SelectItem key={g.id} value={g.id}>
                                                    {g.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Used when a checkout flow does not specify a gateway.
                                    </p>
                                </div>

                                <Button type="submit" disabled={processing}>
                                    {processing ? <Loader2 className="size-4 animate-spin" /> : 'Save selection'}
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CircleDashed className="size-4 text-muted-foreground" /> Adding a new gateway
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Drop a new driver into <code>app/Services/Payments/Drivers</code>, list it in{' '}
                            <code>config/payments.php</code>, and it will appear in this list automatically. The driver
                            contract is intentionally small — implement <code>charge()</code> first, then opt into the
                            capability interfaces (<code>RefundsTransactions</code>, <code>PollsTransactionStatus</code>,{' '}
                            <code>HandlesWebhooks</code>) as the provider supports.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GatewaysPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
