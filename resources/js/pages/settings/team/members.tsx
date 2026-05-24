import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import PeopleGroupIcon from '@atlaskit/icon/core/people-group';
import Lozenge from '@atlaskit/lozenge';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Checkbox,
    Input,
    PageHeading,
    Select,
    useConfirm,
    useToast,
    type SelectOption,
} from '@ads';

type Breadcrumb = { title: string; href: string };
type Member = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: string;
    teams: string[];
    last_seen_at: string | null;
};
type Role = { value: string; label: string };

export default function MembersPage({
    members,
    roles,
}: {
    members: Member[];
    roles: Role[];
    breadcrumbs: Breadcrumb[];
}) {
    const confirm = useConfirm();
    const { toast } = useToast();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkRole, setBulkRole] = useState(roles[roles.length - 1].value);

    const filtered = members.filter(
        (m) => m.name.toLowerCase().includes(search.toLowerCase()) || m.email.toLowerCase().includes(search.toLowerCase()),
    );
    const toggle = (id: number) => setSelected((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));

    const roleOptions: SelectOption[] = roles.map((r) => ({ label: r.label, value: r.value }));

    const submitBulk = async () => {
        if (!selected.length) {
            toast({ type: 'error', title: 'Select members first.' });
            return;
        }
        if (!(await confirm({ title: `Change role for ${selected.length} member(s) to ${bulkRole}?` }))) return;
        router.post(
            '/settings/team/members/bulk-role',
            { user_ids: selected, role: bulkRole },
            { onSuccess: () => setSelected([]) },
        );
    };

    return (
        <>
            <Head title="Members — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Organization members"
                        description="Every person with access to this organization."
                    />

                    <Card>
                        <CardHeader
                            actions={
                                <Box style={{ width: 280 }}>
                                    <Input
                                        placeholder="Search…"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </Box>
                            }
                        >
                            <CardTitle size="small">
                                <Inline space="space.100" alignBlock="center">
                                    <PeopleGroupIcon label="" />
                                    <Text weight="semibold">{members.length} members</Text>
                                </Inline>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {selected.length > 0 ? (
                                <Box
                                    padding="space.100"
                                    style={{
                                        borderRadius: 'var(--ds-border-radius-200)',
                                        border: '1px solid var(--ds-border)',
                                        backgroundColor: 'var(--ds-background-neutral-subtle)',
                                    }}
                                >
                                    <Inline space="space.100" alignBlock="center">
                                        <Text size="small">{selected.length} selected</Text>
                                        <Box style={{ width: 200 }}>
                                            <Select
                                                name="bulk_role"
                                                label=""
                                                options={roleOptions}
                                                value={roleOptions.find((o) => o.value === bulkRole) ?? null}
                                                onChange={(opt) => opt && setBulkRole(String((opt as SelectOption).value))}
                                            />
                                        </Box>
                                        <Button variant="primary" size="compact" onClick={submitBulk}>
                                            Apply
                                        </Button>
                                        <Button variant="subtle" size="compact" onClick={() => setSelected([])}>
                                            Clear
                                        </Button>
                                    </Inline>
                                </Box>
                            ) : null}

                            <Box style={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', fontSize: 'var(--ds-font-size-100)', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr style={{ textAlign: 'left', color: 'var(--ds-text-subtle)', fontSize: 'var(--ds-font-size-075)' }}>
                                            <th style={{ width: 32, padding: 'var(--ds-space-100)' }}></th>
                                            <th style={{ padding: 'var(--ds-space-100)' }}>Name</th>
                                            <th style={{ padding: 'var(--ds-space-100)' }}>Role</th>
                                            <th style={{ padding: 'var(--ds-space-100)' }}>Teams</th>
                                            <th style={{ padding: 'var(--ds-space-100)' }}>Last seen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filtered.map((m) => (
                                            <tr key={m.id} style={{ borderTop: '1px solid var(--ds-border)' }}>
                                                <td style={{ padding: 'var(--ds-space-100)' }}>
                                                    <Checkbox
                                                        name={`select-${m.id}`}
                                                        label=""
                                                        isChecked={selected.includes(m.id)}
                                                        onChange={() => toggle(m.id)}
                                                    />
                                                </td>
                                                <td style={{ padding: 'var(--ds-space-100)' }}>
                                                    <Stack space="space.025">
                                                        <Text weight="semibold">{m.name}</Text>
                                                        <Text size="small" color="color.text.subtle">
                                                            {m.email}
                                                        </Text>
                                                    </Stack>
                                                </td>
                                                <td style={{ padding: 'var(--ds-space-100)' }}>
                                                    <Lozenge>{m.role}</Lozenge>
                                                </td>
                                                <td style={{ padding: 'var(--ds-space-100)' }}>
                                                    <Text size="small" color="color.text.subtle">
                                                        {m.teams.join(', ') || '—'}
                                                    </Text>
                                                </td>
                                                <td style={{ padding: 'var(--ds-space-100)' }}>
                                                    <Text size="small" color="color.text.subtle">
                                                        {m.last_seen_at ? new Date(m.last_seen_at).toLocaleDateString() : '—'}
                                                    </Text>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Box>
                        </CardContent>
                    </Card>
                </Stack>
            </Box>
        </>
    );
}

MembersPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
