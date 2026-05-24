import { Form, Head, usePage } from '@inertiajs/react';
import { Box, Inline, Stack } from '@atlaskit/primitives';
import { Button, Input, Label, PageHeading } from '@ads';
import { update } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profile settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Profile information"
                        description="Update your name and email address"
                    />

                    <Form {...update.form()} options={{ preserveScroll: true }}>
                        {({ processing, errors }) => (
                            <Stack space="space.300">
                                <Stack space="space.075">
                                    <Label htmlFor="name" isRequired>
                                        Name
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={auth.user.name}
                                        autoComplete="name"
                                        placeholder="Full name"
                                        isRequired
                                        isInvalid={!!errors.name}
                                    />
                                    {errors.name ? (
                                        <Box style={{ color: 'var(--ds-text-danger)', fontSize: 'var(--ds-font-size-075)' }}>
                                            {errors.name}
                                        </Box>
                                    ) : null}
                                </Stack>

                                <Stack space="space.075">
                                    <Label htmlFor="email" isRequired>
                                        Email address
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        defaultValue={auth.user.email}
                                        autoComplete="username"
                                        isRequired
                                        isDisabled
                                    />
                                    {errors.email ? (
                                        <Box style={{ color: 'var(--ds-text-danger)', fontSize: 'var(--ds-font-size-075)' }}>
                                            {errors.email}
                                        </Box>
                                    ) : null}
                                </Stack>

                                <Inline space="space.100">
                                    <Button variant="primary" type="submit" isDisabled={processing} isLoading={processing}>
                                        Save
                                    </Button>
                                </Inline>
                            </Stack>
                        )}
                    </Form>
                </Stack>
            </Box>

            <Box padding="space.400" paddingBlockStart="space.0">
                <DeleteUser />
            </Box>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
