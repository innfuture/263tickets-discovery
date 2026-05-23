import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Member = { id: number; name: string; email: string; avatar: string | null; role: string; teams: string[]; last_seen_at: string | null };
type Role = { value: string; label: string };

export default function MembersPage({ members, roles }: { members: Member[]; roles: Role[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkRole, setBulkRole] = useState(roles[roles.length - 1].value);

    const filtered = members.filter((m) => m.name.toLowerCase().includes(search.toLowerCase()) || m.email.toLowerCase().includes(search.toLowerCase()));
    const toggle = (id: number) => setSelected((p) => p.includes(id) ? p.filter((x) => x !== id) : [...p, id]);

    const submitBulk = async () => {
        if (!selected.length) return toast.error('Select members first.');
        if (!await confirm({ title: `Change role for ${selected.length} member(s) to ${bulkRole}?` })) return;
        router.post('/settings/team/members/bulk-role', { user_ids: selected, role: bulkRole }, { onSuccess: () => setSelected([]) });
    };

    return (
        <>
            <Head title="Members — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Organization members" description="Every person with access to this organization." />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle className="flex items-center gap-2 text-base"><Users className="size-4" /> {members.length} members</CardTitle>
                        <Input placeholder="Search…" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
                    </CardHeader>
                    <CardContent>
                        {selected.length > 0 && (
                            <div className="mb-3 flex items-center gap-2 rounded-md border bg-muted/30 p-2">
                                <span className="text-sm">{selected.length} selected</span>
                                <Select value={bulkRole} onValueChange={setBulkRole}><SelectTrigger className="w-40"><SelectValue /></SelectTrigger><SelectContent>{roles.map((r) => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}</SelectContent></Select>
                                <Button size="sm" onClick={submitBulk}>Apply</Button>
                                <Button size="sm" variant="ghost" onClick={() => setSelected([])}>Clear</Button>
                            </div>
                        )}
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-xs text-muted-foreground"><th className="w-8 p-2"></th><th>Name</th><th>Role</th><th>Teams</th><th>Last seen</th></tr></thead>
                            <tbody>
                                {filtered.map((m) => (
                                    <tr key={m.id} className="border-t">
                                        <td className="p-2"><Checkbox checked={selected.includes(m.id)} onCheckedChange={() => toggle(m.id)} /></td>
                                        <td><div><p className="font-medium">{m.name}</p><p className="text-xs text-muted-foreground">{m.email}</p></div></td>
                                        <td><Badge variant="secondary">{m.role}</Badge></td>
                                        <td className="text-xs text-muted-foreground">{m.teams.join(', ') || '—'}</td>
                                        <td className="text-xs text-muted-foreground">{m.last_seen_at ? new Date(m.last_seen_at).toLocaleDateString() : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MembersPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
