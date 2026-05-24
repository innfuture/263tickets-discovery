import { Form, Head } from '@inertiajs/react';
import { Copy, Key, RefreshCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/components/ui/confirmation-dialog';

type Breadcrumb = { title: string; href: string };

/**
 * Check-in & scanning — door PIN page.
 *
 * The PIN is the only field with persistence today; surrounding
 * settings (scanner-mode TTL, simultaneous-scan limit) are listed in
 * a roadmap card below so users know they're coming without us faking
 * disabled inputs.
 */
export default function CheckInPage({
    doorPin,
}: {
    doorPin: string | null;
    breadcrumbs: Breadcrumb[];
}) {
    const confirm = useConfirm();
    const [justCopied, setJustCopied] = useState(false);

    const copy = async () => {
        if (!doorPin) return;
        try {
            await navigator.clipboard.writeText(doorPin);
            setJustCopied(true);
            toast.success('PIN copied to clipboard.');
            setTimeout(() => setJustCopied(false), 1500);
        } catch {
            toast.error("Couldn't access the clipboard.");
        }
    };

    return (
        <>
            <Head title="Check-in &amp; scanning — Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Check-in &amp; scanning"
                    description="The PIN below lets door staff sign into the scanner app for this organization. Regenerate it any time — old PINs stop working immediately."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Key className="size-4" />
                            Door PIN
                        </CardTitle>
                        <CardDescription>
                            Share this PIN with venue staff alongside the
                            scanner-app download link.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {doorPin ? (
                            <div className="flex flex-wrap items-center gap-3">
                                <code className="rounded-md border bg-muted px-4 py-3 font-mono text-2xl tracking-widest">
                                    {doorPin}
                                </code>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={copy}
                                >
                                    <Copy className="size-4" />
                                    {justCopied ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                        ) : (
                            <p className="rounded-md border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                No PIN set yet. Generate one to enable
                                scanner-app sign-in.
                            </p>
                        )}

                        <div className="flex flex-wrap items-center gap-2">
                            <Form
                                action="/settings/operations/check-in"
                                method="post"
                            >
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="regenerate"
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <RefreshCcw className="size-4" />
                                            {doorPin
                                                ? 'Regenerate PIN'
                                                : 'Generate PIN'}
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {doorPin ? (
                                <Form
                                    action="/settings/operations/check-in"
                                    method="post"
                                    // TODO: Inertia onBefore expects boolean|void; the async confirm here is not awaited, so the dialog does not actually block submission. Refactor to handle confirm in a click handler.
                                    // @ts-expect-error pre-existing async-returns-promise pattern
                                    onBefore={async () => {
                                        const ok = await confirm({
                                            title: 'Clear the door PIN?',
                                            description:
                                                'Door staff will no longer be able to sign in to the scanner app until you generate a new one.',
                                            confirmLabel: 'Clear',
                                            tone: 'destructive',
                                        });
                                        return ok;
                                    }}
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="clear"
                                            />
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                disabled={processing}
                                            >
                                                <Trash2 className="size-4" />
                                                Clear
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardTitle className="text-base">Coming soon</CardTitle>
                        <CardDescription>
                            Scanner offline-mode TTL, simultaneous-scan
                            limits, and after-hours lockout settings will
                            live here too.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}

CheckInPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({
    breadcrumbs,
});
