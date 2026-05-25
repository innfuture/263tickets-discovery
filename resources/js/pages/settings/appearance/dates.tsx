import { Form, Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Dates = { date_format: string; time_format: '12h' | '24h'; week_start: 'sunday' | 'monday'; number_grouping: 'comma' | 'space' | 'dot'; fiscal_year_start_month: number };

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

export default function DatesPage({ dates, options }: { dates: Dates; options: { date_format: string[]; time_format: string[]; week_start: string[]; number_grouping: string[] }; breadcrumbs: Breadcrumb[] }) {
    const [dateFmt, setDateFmt] = useState(dates.date_format);
    const [timeFmt, setTimeFmt] = useState<Dates['time_format']>(dates.time_format);
    const [weekStart, setWeekStart] = useState<Dates['week_start']>(dates.week_start);
    const [grouping, setGrouping] = useState<Dates['number_grouping']>(dates.number_grouping);

    return (
        <>
            <Head title="Date formats — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Date & number formats" description="Applied in the dashboard and on attendee-facing surfaces." />
                <Form action="/settings/appearance/dates" method="post" onError={() => toast.error('Check fields.')}>
                    {({ processing }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Formats</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" name="date_format" value={dateFmt} />
                                <input type="hidden" name="time_format" value={timeFmt} />
                                <input type="hidden" name="week_start" value={weekStart} />
                                <input type="hidden" name="number_grouping" value={grouping} />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2"><FieldLabel>Date format</FieldLabel><Select value={dateFmt} onValueChange={setDateFmt}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{options.date_format.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel>Time format</FieldLabel><Select value={timeFmt} onValueChange={(v) => setTimeFmt(v as Dates['time_format'])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{options.time_format.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel>Week starts on</FieldLabel><Select value={weekStart} onValueChange={(v) => setWeekStart(v as Dates['week_start'])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{options.week_start.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel>Number grouping</FieldLabel><Select value={grouping} onValueChange={(v) => setGrouping(v as Dates['number_grouping'])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{options.number_grouping.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel htmlFor="fy">Fiscal year starts</FieldLabel>
                                        <select id="fy" name="fiscal_year_start_month" defaultValue={dates.fiscal_year_start_month} className="rounded-md border bg-background px-3 py-1.5 text-sm">
                                            {MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                                        </select>
                                    </div>
                                </div>
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save'}</Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

DatesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
