import { Head, router } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };
type Session = { id: string; is_current: boolean; device_label: string | null; ip_address: string | null; location: string | null; user_agent: string | null; started_at: string | null; last_active_at: string | null };

export default function SessionsPage({ sessions }: { sessions: Session[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();

    return (
        <>
            <Head title="Sessions — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Active sessions" description="Browsers and devices currently signed in to your account." />
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">Sessions ({sessions.length})</CardTitle>
                        <Button variant="outline" size="sm" onClick={async () => { if (await confirm({ title: 'Revoke all other sessions?', description: 'You\'ll stay signed in on this one.' })) router.delete('/settings/sessions'); }}>Revoke all others</Button>
                    </CardHeader>
                    <CardContent>
                        {sessions.length === 0 ? <p className="text-sm text-muted-foreground">No active sessions.</p> : (
                            <ul className="space-y-2">
                                {sessions.map((s) => (
                                    <li key={s.id} className="flex items-start justify-between rounded-md border p-3">
                                        <div className="flex items-start gap-3">
                                            <Monitor className="mt-1 size-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">{s.device_label || 'Unknown device'} {s.is_current && <Badge className="ml-2">this device</Badge>}</p>
                                                <p className="text-xs text-muted-foreground">{s.ip_address || '—'} · {s.location || 'Location unavailable'}</p>
                                                <p className="text-xs text-muted-foreground">Last active {s.last_active_at ? new Date(s.last_active_at).toLocaleString() : '—'}</p>
                                            </div>
                                        </div>
                                        {!s.is_current && (
                                            <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: 'Revoke session?', tone: 'destructive' })) router.delete(`/settings/sessions/${encodeURIComponent(s.id)}`); }}>Revoke</Button>
                                        )}
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

SessionsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
