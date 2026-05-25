import { Head, router, usePage } from '@inertiajs/react';
import { Mail, Send, X } from 'lucide-react';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };
type Invitation = { id: number; email: string; role: string; invited_by: string | null; sent_at: string; expires_at: string | null; is_expired: boolean };

export default function InvitationsPage({ invitations }: { invitations: Invitation[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const currentOrg = usePage<{ currentOrganization?: { slug: string } | null }>().props.currentOrganization;

    const cancel = async (id: number) => {
        if (!currentOrg) return;
        if (!await confirm({ title: 'Cancel invitation?', tone: 'destructive' })) return;
        router.delete(`/settings/organizations/${currentOrg.slug}/invitations/${id}`);
    };

    return (
        <>
            <Head title="Invitations — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Invitations" description="Pending invitations to join this organization." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Mail className="size-4" /> Pending ({invitations.length})</CardTitle></CardHeader>
                    <CardContent>
                        {invitations.length === 0 ? <p className="text-sm text-muted-foreground">No invitations.</p> : (
                            <ul className="space-y-2">
                                {invitations.map((i) => (
                                    <li key={i.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div>
                                            <p className="text-sm font-medium">{i.email} {i.is_expired && <Badge variant="destructive" className="ml-2">expired</Badge>}</p>
                                            <p className="text-xs text-muted-foreground">Role: {i.role} · Invited by {i.invited_by ?? '—'} · {new Date(i.sent_at).toLocaleDateString()}</p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/team/invitations/${i.id}/resend`)}><Send className="size-3.5" /> Resend</Button>
                                            <Button size="sm" variant="ghost" onClick={() => cancel(i.id)}><X className="size-3.5" /></Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InvitationsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
