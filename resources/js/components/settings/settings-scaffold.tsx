import { Head } from '@inertiajs/react';
import { ArrowUpRight, Sparkles } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Shared renderer for settings pages that have a routed home and a
 * permission gate but no real backing UI yet. Each scaffolded page
 * passes the same `scaffold` payload from its controller — title,
 * description, the list of fields that will live there, and an
 * optional CTA — and this component handles the visual chrome.
 *
 * As individual pages get their real implementations, they swap from
 * `<SettingsScaffold {...scaffold} />` to a hand-written component
 * one at a time. The nav structure + RBAC gate stay the same.
 */
export type ScaffoldCta = {
    label: string;
    href: string;
};

export type ScaffoldProps = {
    title: string;
    description: string;
    futureFields?: string[];
    cta?: ScaffoldCta | null;
};

export function SettingsScaffold({
    title,
    description,
    futureFields = [],
    cta = null,
}: ScaffoldProps) {
    return (
        <>
            <Head title={`${title} — Settings`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={title}
                        description={description}
                    />
                    {cta ? (
                        <Button asChild size="sm" variant="outline">
                            <a href={cta.href}>
                                {cta.label}
                                <ArrowUpRight className="size-4" />
                            </a>
                        </Button>
                    ) : null}
                </div>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Sparkles className="size-4 text-muted-foreground" />
                            Coming soon
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p className="text-muted-foreground">
                            This area is reserved and permission-gated.
                            The fields below describe what will live here
                            once it ships.
                        </p>
                        {futureFields.length > 0 ? (
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {futureFields.map((field) => (
                                    <li
                                        key={field}
                                        className="flex items-center gap-2 rounded-md border border-dashed px-3 py-2 text-muted-foreground"
                                    >
                                        <span
                                            aria-hidden
                                            className="inline-block size-1.5 rounded-full bg-muted-foreground/40"
                                        />
                                        {field}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
