import { Form, Head, Link } from '@inertiajs/react';
import { Book, ExternalLink, FileText, LifeBuoy, Loader2, Mail, MessageCircleQuestion } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };

const TOPICS = [
    {
        title: 'Getting started',
        description: 'Create your first event, configure ticket categories, publish.',
        link: '/discover',
        icon: Book,
    },
    {
        title: 'Day-of-event check-in',
        description: 'How to run the scanner, recover from a no-network venue.',
        link: '#',
        icon: FileText,
    },
    {
        title: 'Refunds and payouts',
        description: 'Process refunds, monitor payouts, reconcile end-of-month revenue.',
        link: '#',
        icon: FileText,
    },
    {
        title: 'Marketing and ads',
        description: 'Connect Google/Meta integrations, run paid campaigns.',
        link: '#',
        icon: FileText,
    },
    {
        title: 'Payment gateway setup',
        description: 'EcoCash, Paynow, Pesepay, Zimswitch — credentials and webhooks.',
        link: '/settings/billing/gateways',
        icon: FileText,
    },
];

const FAQ = [
    {
        q: 'How do I refund an attendee?',
        a: 'Open Finance → Recent refunds, click the order, and confirm the refund. The webhook flow will notify the buyer.',
    },
    {
        q: 'Why is my event still in draft past its sales-start time?',
        a: 'You will see a warning in Notifications. Open the event editor and either publish or push the sales window forward.',
    },
    {
        q: 'My scanner is offline at the gate — can I still check people in?',
        a: 'Offline tickets carry a verifiable QR payload. The scanner queues scans locally and reconciles on reconnect.',
    },
    {
        q: 'How do I export my attendee list?',
        a: 'On the Attendees page, set filters then click Export CSV. Output respects current filters.',
    },
];

type Props = { support_email?: string | null };

export default function HelpAndSupport({ support_email }: Props = {}) {
    return (
        <>
            <Head title="Help & support" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Help & support"
                    description="Documentation, common questions, and how to reach us."
                />

                {/* Quick-start topics */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {TOPICS.map(({ title, description, link, icon: Icon }) => (
                        <Link
                            key={title}
                            href={link}
                            className="block rounded-lg border bg-card p-4 transition hover:bg-muted/40"
                        >
                            <Icon className="size-5 text-muted-foreground" />
                            <p className="mt-2 text-sm font-semibold">{title}</p>
                            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
                        </Link>
                    ))}
                </div>

                {/* FAQ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MessageCircleQuestion className="size-4" /> Frequently asked
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-4">
                            {FAQ.map((f) => (
                                <div key={f.q}>
                                    <dt className="text-sm font-medium">{f.q}</dt>
                                    <dd className="mt-1 text-sm text-muted-foreground">{f.a}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                {/* Contact + resources */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <LifeBuoy className="size-4" /> Contact support
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/settings/help"
                                method="post"
                                onError={() => toast.error('Check the fields below.')}
                                onSuccess={() => toast.success('Message sent.')}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <div className="space-y-3">
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="help-subject" error={errors.subject}>
                                                Subject
                                            </FieldLabel>
                                            <Input id="help-subject" name="subject" placeholder="Brief description" />
                                        </div>
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="help-message" error={errors.message}>
                                                Message
                                            </FieldLabel>
                                            <Textarea
                                                id="help-message"
                                                name="message"
                                                rows={5}
                                                placeholder="Tell us what's happening — include event slug + steps if possible."
                                            />
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-fit">
                                            {processing ? (
                                                <Loader2 className="mr-1 size-4 animate-spin" />
                                            ) : (
                                                <Mail className="mr-1 size-4" />
                                            )}
                                            Send
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            {support_email ? (
                                                <>Delivers to <code>{support_email}</code>.</>
                                            ) : (
                                                <>
                                                    Messages are logged when <code>SUPPORT_EMAIL</code> is unset — set it
                                                    in <code>.env</code> to enable delivery.
                                                </>
                                            )}
                                        </p>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">External resources</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <ResourceLink href="https://laravel.com/docs/starter-kits#react" label="Laravel React starter docs" />
                            <ResourceLink href="https://inertiajs.com" label="Inertia.js documentation" />
                            <ResourceLink href="https://spatie.be/docs/laravel-permission" label="Spatie Permission" />
                            <ResourceLink href="https://lucide.dev" label="Lucide icon library" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function ResourceLink({ href, label }: { href: string; label: string }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="flex items-center justify-between rounded-md border bg-card p-2 hover:bg-muted/40"
        >
            <span>{label}</span>
            <ExternalLink className="size-3.5 text-muted-foreground" />
        </a>
    );
}

HelpAndSupport.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
