import { ExternalLink, Mic, Star } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { SectionTitle } from '@/components/ui/section-title';
import { cn } from '@/lib/utils';

export type LineupArtist = {
    id: number;
    name: string;
    role: string | null;
    bio: string | null;
    image_url: string | null;
    social_url: string | null;
    is_headliner: boolean;
};

export function EventLineupSection({ artists }: { artists: LineupArtist[] }) {
    if (artists.length === 0) {
        return null;
    }

    const headliners = artists.filter((a) => a.is_headliner);
    const rest = artists.filter((a) => !a.is_headliner);

    return (
        <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
            <CardHeader>
                <SectionTitle
                    icon={<Mic className="size-4" />}
                    count={artists.length}
                >
                    Lineup
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {headliners.length > 0 ? (
                    <div className="space-y-3">
                        <div className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <Star className="size-3.5 fill-yellow-400 text-yellow-400" />
                            Headlining
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {headliners.map((a) => (
                                <ArtistCard key={a.id} artist={a} prominent />
                            ))}
                        </div>
                    </div>
                ) : null}

                {rest.length > 0 ? (
                    <div className="space-y-3">
                        {headliners.length > 0 ? (
                            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Supporting
                            </div>
                        ) : null}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {rest.map((a) => (
                                <ArtistCard key={a.id} artist={a} />
                            ))}
                        </div>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function ArtistCard({
    artist,
    prominent = false,
}: {
    artist: LineupArtist;
    prominent?: boolean;
}) {
    const content = (
        <div
            className={cn(
                'flex items-start gap-3 rounded-md border bg-card p-3 transition hover:shadow-md',
                prominent && 'ring-1 ring-yellow-400/40',
            )}
        >
            <div
                className={cn(
                    'relative shrink-0 overflow-hidden rounded-full bg-muted',
                    prominent ? 'size-16' : 'size-12',
                )}
            >
                {artist.image_url ? (
                    <img
                        src={artist.image_url}
                        alt=""
                        className="size-full object-cover"
                        onError={(e) => {
                            e.currentTarget.style.display = 'none';
                        }}
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-primary/20 text-sm font-semibold text-primary">
                        {artist.name.slice(0, 2).toUpperCase()}
                    </div>
                )}
            </div>

            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex items-center gap-1.5">
                    <p
                        className={cn(
                            'truncate font-semibold',
                            prominent ? 'text-base' : 'text-sm',
                        )}
                    >
                        {artist.name}
                    </p>
                    {artist.is_headliner ? (
                        <Star className="size-3 shrink-0 fill-yellow-400 text-yellow-400" />
                    ) : null}
                </div>
                {artist.role ? (
                    <Badge variant="secondary" className="font-normal">
                        {artist.role}
                    </Badge>
                ) : null}
                {prominent && artist.bio ? (
                    <p className="line-clamp-2 pt-1 text-xs leading-relaxed text-muted-foreground">
                        {artist.bio}
                    </p>
                ) : null}
                {artist.social_url ? (
                    <a
                        href={artist.social_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 pt-1 text-xs text-primary hover:underline"
                    >
                        <ExternalLink className="size-3" />
                        Profile
                    </a>
                ) : null}
            </div>
        </div>
    );

    return content;
}
