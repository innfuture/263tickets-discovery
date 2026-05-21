import { Form, Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };

/**
 * Org-level locale + timezone form. The same columns are editable
 * inside the larger Organization Profile form; this page exposes them
 * standalone so a user can flip the timezone without scrolling past
 * 20 unrelated fields.
 */
export default function LocalePage({
    organization,
}: {
    organization: {
        default_currency: string | null;
        default_timezone: string | null;
    };
    breadcrumbs: Breadcrumb[];
}) {
    const [dirty, setDirty] = useState(false);

    return (
        <>
            <Head title="Locale &amp; timezone — Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Locale &amp; timezone"
                    description="Defaults for new events created under this organization. Each event can still override these on its own form."
                />

                <Form
                    action="/settings/appearance/locale"
                    method="post"
                    onChange={() => setDirty(true)}
                    onSuccess={() => setDirty(false)}
                    onError={() =>
                        toast.error(
                            "Couldn't save — review the highlighted fields and try again.",
                        )
                    }
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Defaults
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="locale-currency"
                                            error={errors.default_currency}
                                        >
                                            Default currency
                                        </FieldLabel>
                                        <Input
                                            id="locale-currency"
                                            name="default_currency"
                                            maxLength={3}
                                            defaultValue={
                                                organization.default_currency ??
                                                ''
                                            }
                                            placeholder="USD"
                                            className="uppercase"
                                            aria-invalid={
                                                !!errors.default_currency
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="locale-timezone"
                                            error={errors.default_timezone}
                                        >
                                            Default timezone
                                        </FieldLabel>
                                        <Input
                                            id="locale-timezone"
                                            name="default_timezone"
                                            defaultValue={
                                                organization.default_timezone ??
                                                ''
                                            }
                                            placeholder="Africa/Harare"
                                            aria-invalid={
                                                !!errors.default_timezone
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={processing || !dirty}
                                    >
                                        {processing ? (
                                            <>
                                                <Loader2 className="size-4 animate-spin" />
                                                Saving…
                                            </>
                                        ) : (
                                            'Save defaults'
                                        )}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

LocalePage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({
    breadcrumbs,
});
