import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Policy = { id: number; name: string; description: string | null; cutoff_hours: number; prorated: boolean; restocking_fee_percent: number; is_default: boolean };

export default function RefundsPage({ policies }: { policies: Policy[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Policy | null>(null);

    const openCreate = () => { setEditing({ id: 0, name: '', description: '', cutoff_hours: 48, prorated: false, restocking_fee_percent: 0, is_default: policies.length === 0 }); setOpen(true); };
    const openEdit = (p: Policy) => { setEditing({ ...p }); setOpen(true); };

    const submit = () => {
        if (!editing) return;
        const data = { name: editing.name, description: editing.description ?? '', cutoff_hours: editing.cutoff_hours, prorated: editing.prorated, restocking_fee_percent: editing.restocking_fee_percent, is_default: editing.is_default };
        const opts = { onSuccess: () => { setOpen(false); }, onError: () => toast.error('Check fields.') };
        if (editing.id) router.patch(`/settings/billing/refunds/${editing.id}`, data, opts);
        else router.post('/settings/billing/refunds', data, opts);
    };

    return (
        <>
            <Head title="Refund policies — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Refund policies" description="Named policies you can attach to ticket types. Attendees see the policy text at checkout." />

                <div className="flex justify-end"><Button onClick={openCreate}><Plus className="size-4" /> New policy</Button></div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Policies ({policies.length})</CardTitle></CardHeader>
                    <CardContent>
                        {policies.length === 0 ? <p className="text-sm text-muted-foreground">No policies yet.</p> : (
                            <ul className="space-y-2">
                                {policies.map((p) => (
                                    <li key={p.id} className="flex items-start justify-between rounded-md border p-3">
                                        <div>
                                            <p className="text-sm font-medium">{p.name} {p.is_default && <Badge className="ml-2">default</Badge>}</p>
                                            {p.description && <p className="mt-1 text-xs text-muted-foreground">{p.description}</p>}
                                            <p className="mt-1 text-xs text-muted-foreground">{p.cutoff_hours}h cutoff · {p.prorated ? 'prorated' : 'all-or-nothing'} · {p.restocking_fee_percent}% restocking fee</p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button size="sm" variant="ghost" onClick={() => openEdit(p)}><Pencil className="size-3.5" /></Button>
                                            <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: `Delete "${p.name}"?`, tone: 'destructive' })) router.delete(`/settings/billing/refunds/${p.id}`); }}><Trash2 className="size-3.5" /></Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing?.id ? 'Edit policy' : 'New policy'}</DialogTitle></DialogHeader>
                    {editing && (
                        <div className="space-y-4">
                            <div className="grid gap-2"><FieldLabel htmlFor="name">Name</FieldLabel><Input id="name" value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} /></div>
                            <div className="grid gap-2"><FieldLabel htmlFor="description">Description (shown at checkout)</FieldLabel><Textarea id="description" value={editing.description ?? ''} onChange={(e) => setEditing({ ...editing, description: e.target.value })} rows={3} /></div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2"><FieldLabel htmlFor="cutoff_hours">Cutoff (hours)</FieldLabel><Input id="cutoff_hours" type="number" value={editing.cutoff_hours} onChange={(e) => setEditing({ ...editing, cutoff_hours: Number(e.target.value) })} /></div>
                                <div className="grid gap-2"><FieldLabel htmlFor="restocking">Restocking fee %</FieldLabel><Input id="restocking" type="number" value={editing.restocking_fee_percent} onChange={(e) => setEditing({ ...editing, restocking_fee_percent: Number(e.target.value) })} /></div>
                            </div>
                            <label className="flex items-center gap-2"><Checkbox checked={editing.prorated} onCheckedChange={(v) => setEditing({ ...editing, prorated: Boolean(v) })} /><span className="text-sm">Pro-rate refunds based on time-to-event</span></label>
                            <label className="flex items-center gap-2"><Checkbox checked={editing.is_default} onCheckedChange={(v) => setEditing({ ...editing, is_default: Boolean(v) })} /><span className="text-sm">Make default</span></label>
                            <div className="flex justify-end gap-2"><Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={submit}>{editing.id ? 'Save' : 'Create'}</Button></div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

RefundsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
