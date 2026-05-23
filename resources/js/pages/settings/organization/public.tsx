import { Form, Head } from '@inertiajs/react';
import { Copy, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Event = { uuid: string; name: string; starts_at: string | null };

type Profile = {
    public_visible: boolean;
    hide_past_events: boolean;
    follower_prompt: string | null;
    featured_event_ids: string[];
};

export default function PublicPage({
    profile,
    events,
    embedSnippet,
    publicUrl,
}: {
    profile: Profile;
    events: Event[];
    embedSnippet: string;
    publicUrl: string;
    breadcrumbs: Breadcrumb[];
}) {
    const [featured, setFeatured] = useState<string[]>(profile.featured_event_ids);
    const [publicVisible, setPublicVisible] = useState(profile.public_visible);
    const [hidePast, setHidePast] = useState(profile.hide_past_events);

    const toggleFeatured = (id: string) => {
        setFeatured((p) => p.includes(id) ? p.filter((x) => x !== id) : (p.length < 6 ? [...p, id] : p));
    };

    return (
        <>
            <Head title="Public page — Settings" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Public profile"
                    description="What attendees see when they tap your organizer name on an event page."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Public URL</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-center gap-2 rounded-md border bg-muted/30 p-3 font-mono text-sm">
                            <a href={publicUrl} target="_blank" rel="noreferrer" className="flex-1 truncate text-primary hover:underline">{publicUrl}</a>
                            <Button type="button" size="sm" variant="ghost" onClick={() => navigator.clipboard.writeText(publicUrl).then(() => toast.success('Copied.'))}>
                                <Copy className="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Form action="/settings/organization/public" method="post" onError={() => toast.error("Couldn't save.")}>
                    {({ processing, errors }) => (
                        <>
                            {featured.map((id) => <input key={id} type="hidden" name="featured_event_ids[]" value={id} />)}
                            <input type="hidden" name="public_visible" value={publicVisible ? '1' : '0'} />
                            <input type="hidden" name="hide_past_events" value={hidePast ? '1' : '0'} />

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Visibility</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <label className="flex items-start gap-3 rounded-md border p-3">
                                        <Checkbox checked={publicVisible} onCheckedChange={(v) => setPublicVisible(Boolean(v))} />
                                        <div>
                                            <p className="text-sm font-medium">Profile is public</p>
                                            <p className="text-xs text-muted-foreground">Uncheck to make /o/{`{slug}`} return a 404.</p>
                                        </div>
                                    </label>
                                    <label className="flex items-start gap-3 rounded-md border p-3">
                                        <Checkbox checked={hidePast} onCheckedChange={(v) => setHidePast(Boolean(v))} />
                                        <div>
                                            <p className="text-sm font-medium">Hide past events</p>
                                            <p className="text-xs text-muted-foreground">Only upcoming events are listed on the public profile.</p>
                                        </div>
                                    </label>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Follower prompt</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="follower_prompt" error={errors.follower_prompt}>Prompt</FieldLabel>
                                        <Textarea id="follower_prompt" name="follower_prompt" defaultValue={profile.follower_prompt ?? ''} rows={2} maxLength={240} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Featured events ({featured.length}/6)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {events.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">No events to feature yet.</p>
                                    ) : (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {events.map((e) => {
                                                const on = featured.includes(e.uuid);
                                                return (
                                                    <label key={e.uuid} className={`flex items-center gap-2 rounded-md border p-2 text-sm ${on ? 'border-primary' : ''}`}>
                                                        <Checkbox checked={on} onCheckedChange={() => toggleFeatured(e.uuid)} />
                                                        <span className="flex-1 truncate">{e.name}</span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save'}</Button>
                            </div>
                        </>
                    )}
                </Form>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Embed snippet</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto rounded-md border bg-muted/30 p-3 text-xs">{embedSnippet}</pre>
                        <Button type="button" size="sm" variant="outline" className="mt-2" onClick={() => navigator.clipboard.writeText(embedSnippet).then(() => toast.success('Copied.'))}>
                            <Copy className="size-3.5" /> Copy
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PublicPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
