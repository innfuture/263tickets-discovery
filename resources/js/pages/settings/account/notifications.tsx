import { Form, Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

type TriggerItem = {
    key: string;
    label: string;
    hint: string;
};

type TriggerGroup = {
    group: string;
    items: TriggerItem[];
};

type Breadcrumb = { title: string; href: string };

/**
 * Per-user notification toggles. The form posts a flat
 * `preferences[<key>]` boolean map; the server persists to the
 * `users.notification_preferences` JSON column, merging over defaults
 * defined in `User::notificationDefaults()` so newly added triggers
 * always have a sensible value.
 */
export default function NotificationsPage({
    preferences,
    triggers,
}: {
    preferences: Record<string, boolean>;
    triggers: TriggerGroup[];
    breadcrumbs: Breadcrumb[];
}) {
    const [state, setState] = useState<Record<string, boolean>>(preferences);
    const [dirty, setDirty] = useState(false);

    const toggle = (key: string, value: boolean) => {
        setState((prev) => ({ ...prev, [key]: value }));
        setDirty(true);
    };

    return (
        <>
            <Head title="Notifications — Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notifications"
                    description="Choose which emails you receive from the platform. These settings are personal — they only affect your own inbox."
                />

                <Form
                    action="/settings/notifications"
                    method="patch"
                    className="space-y-6"
                    onSuccess={() => setDirty(false)}
                    onError={() =>
                        toast.error(
                            "Couldn't save your notification preferences. Try again in a moment.",
                        )
                    }
                >
                    {({ processing }) => (
                        <>
                            {triggers.map((group) => (
                                <Card key={group.group}>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {group.group}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {group.items.map((item) => (
                                            <div
                                                key={item.key}
                                                className="flex items-start justify-between gap-4 rounded-md border p-3"
                                            >
                                                <div className="space-y-0.5">
                                                    <p className="text-sm font-medium">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.hint}
                                                    </p>
                                                </div>
                                                <Checkbox
                                                    checked={!!state[item.key]}
                                                    onCheckedChange={(v) =>
                                                        toggle(
                                                            item.key,
                                                            v === true,
                                                        )
                                                    }
                                                />
                                                {/* Hidden input the server reads on submit. */}
                                                <input
                                                    type="hidden"
                                                    name={`preferences[${item.key}]`}
                                                    value={
                                                        state[item.key]
                                                            ? '1'
                                                            : '0'
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            ))}

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={processing || !dirty}
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="size-4 animate-spin" />
                                            Saving…
                                        </>
                                    ) : (
                                        'Save preferences'
                                    )}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardDescription>
                            More notification channels (SMS, push, Slack)
                            are on the roadmap. The toggles above only
                            affect email delivery for now.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}

NotificationsPage.layout = ({
    breadcrumbs,
}: {
    breadcrumbs: Breadcrumb[];
}) => ({
    breadcrumbs,
});
