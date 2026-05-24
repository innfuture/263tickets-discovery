import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Box, Inline, Stack } from '@atlaskit/primitives';
import { Button, Card, CardContent, CardHeader, CardTitle, Label, PageHeading, Select, useToast, type SelectOption } from '@ads';

type Breadcrumb = { title: string; href: string };
type Dates = {
    date_format: string;
    time_format: '12h' | '24h';
    week_start: 'sunday' | 'monday';
    number_grouping: 'comma' | 'space' | 'dot';
    fiscal_year_start_month: number;
};

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const toOpt = (v: string): SelectOption => ({ label: v, value: v });

export default function DatesPage({
    dates,
    options,
}: {
    dates: Dates;
    options: { date_format: string[]; time_format: string[]; week_start: string[]; number_grouping: string[] };
    breadcrumbs: Breadcrumb[];
}) {
    const { toast } = useToast();
    const [dateFmt, setDateFmt] = useState(dates.date_format);
    const [timeFmt, setTimeFmt] = useState<Dates['time_format']>(dates.time_format);
    const [weekStart, setWeekStart] = useState<Dates['week_start']>(dates.week_start);
    const [grouping, setGrouping] = useState<Dates['number_grouping']>(dates.number_grouping);

    return (
        <>
            <Head title="Date formats — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Date & number formats"
                        description="Applied in the dashboard and on attendee-facing surfaces."
                    />
                    <Form
                        action="/settings/appearance/dates"
                        method="post"
                        onError={() => toast({ type: 'error', title: 'Check fields.' })}
                    >
                        {({ processing }) => (
                            <Card>
                                <CardHeader>
                                    <CardTitle size="small">Formats</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <input type="hidden" name="date_format" value={dateFmt} />
                                    <input type="hidden" name="time_format" value={timeFmt} />
                                    <input type="hidden" name="week_start" value={weekStart} />
                                    <input type="hidden" name="number_grouping" value={grouping} />

                                    <Box
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
                                            gap: 'var(--ds-space-200)',
                                        }}
                                    >
                                        <Select
                                            name="date_format_display"
                                            label="Date format"
                                            options={options.date_format.map(toOpt)}
                                            value={toOpt(dateFmt)}
                                            onChange={(opt) => opt && setDateFmt(String((opt as SelectOption).value))}
                                        />
                                        <Select
                                            name="time_format_display"
                                            label="Time format"
                                            options={options.time_format.map(toOpt)}
                                            value={toOpt(timeFmt)}
                                            onChange={(opt) => opt && setTimeFmt(String((opt as SelectOption).value) as Dates['time_format'])}
                                        />
                                        <Select
                                            name="week_start_display"
                                            label="Week starts on"
                                            options={options.week_start.map(toOpt)}
                                            value={toOpt(weekStart)}
                                            onChange={(opt) => opt && setWeekStart(String((opt as SelectOption).value) as Dates['week_start'])}
                                        />
                                        <Select
                                            name="number_grouping_display"
                                            label="Number grouping"
                                            options={options.number_grouping.map(toOpt)}
                                            value={toOpt(grouping)}
                                            onChange={(opt) => opt && setGrouping(String((opt as SelectOption).value) as Dates['number_grouping'])}
                                        />
                                        <Stack space="space.075">
                                            <Label htmlFor="fy">Fiscal year starts</Label>
                                            <select
                                                id="fy"
                                                name="fiscal_year_start_month"
                                                defaultValue={dates.fiscal_year_start_month}
                                                style={{
                                                    borderRadius: 'var(--ds-border-radius)',
                                                    border: '1px solid var(--ds-border-input)',
                                                    backgroundColor: 'var(--ds-background-input)',
                                                    color: 'var(--ds-text)',
                                                    padding: '6px 12px',
                                                    fontSize: 'var(--ds-font-size-100)',
                                                }}
                                            >
                                                {MONTHS.map((m, i) => (
                                                    <option key={m} value={i + 1}>
                                                        {m}
                                                    </option>
                                                ))}
                                            </select>
                                        </Stack>
                                    </Box>

                                    <Inline space="space.100">
                                        <Button variant="primary" type="submit" isLoading={processing} isDisabled={processing}>
                                            Save
                                        </Button>
                                    </Inline>
                                </CardContent>
                            </Card>
                        )}
                    </Form>
                </Stack>
            </Box>
        </>
    );
}

DatesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
