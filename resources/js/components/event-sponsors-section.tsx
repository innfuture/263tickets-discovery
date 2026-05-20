import { ExternalLink, Globe, Handshake } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';
import { cn } from '@/lib/utils';

export type SponsorView = {
    id: number;
    name: string;
    tier: { value: string; label: string; rank: number };
    logo_url: string | null;
    website_url: string | null;
    social_url: string | null;
    description: string | null;
};

/**
 * Renders sponsors grouped by tier. Top-tier sponsors (Title / Presenting /
 * Platinum) get larger logo tiles; everything else falls into a more compact
 * grid. The wrapper card returns null when there are no sponsors so the
 * Details page doesn't render an empty section.
 */
export function EventSponsorsSection({
    sponsors,
    canEdit = false,
}: {
    sponsors: SponsorView[];
    canEdit?: boolean;
}) {
    if (sponsors.length === 0) {
        if (!canEdit) {
            return null;
        }

        return (
            <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
                <CardHeader>
                    <SectionTitle icon={<Handshake className="size-4" />}>
                        Sponsors
                    </SectionTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        tone="accent"
                        icon={<Handshake className="size-6" />}
                        title="No sponsors yet"
                        description="Add brands backing this event to give them visibility on the public page."
                    />
                </CardContent>
            </Card>
        );
    }

    // Group sponsors by tier in their already-ordered sequence
    const grouped: Record<string, SponsorView[]> = {};
    const tierLabels: Record<string, string> = {};
    const tierOrder: string[] = [];

    for (const s of sponsors) {
        const key = s.tier.value;

        if (!grouped[key]) {
            grouped[key] = [];
            tierLabels[key] = s.tier.label;
            tierOrder.push(key);
        }

        grouped[key].push(s);
    }

    return (
        <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
            <CardHeader>
                <SectionTitle
                    icon={<Handshake className="size-4" />}
                    count={sponsors.length}
                >
                    Sponsors
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {tierOrder.map((tierKey) => {
                    const list = grouped[tierKey];
                    const prominent = list[0].tier.rank <= 3;

                    return (
                        <div key={tierKey} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    {tierLabels[tierKey]}
                                </p>
                                <div className="h-px flex-1 bg-border" />
                            </div>
                            <div
                                className={cn(
                                    'grid gap-3',
                                    prominent
                                        ? 'sm:grid-cols-2 lg:grid-cols-3'
                                        : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
                                )}
                            >
                                {list.map((s) => (
                                    <SponsorTile
                                        key={s.id}
                                        sponsor={s}
                                        prominent={prominent}
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

function SponsorTile({
    sponsor,
    prominent,
}: {
    sponsor: SponsorView;
    prominent: boolean;
}) {
    const linkHref = sponsor.website_url ?? sponsor.social_url ?? null;

    const Wrapper = linkHref
        ? ({ children }: { children: React.ReactNode }) => (
              <a
                  href={linkHref}
                  target="_blank"
                  rel="noreferrer noopener sponsored"
                  className="group"
              >
                  {children}
              </a>
          )
        : ({ children }: { children: React.ReactNode }) => (
              <div>{children}</div>
          );

    return (
        <Wrapper>
            <div
                className={cn(
                    'flex flex-col items-center gap-2 rounded-lg border bg-card p-3 text-center transition group-hover:border-primary/40 group-hover:shadow-sm',
                    prominent && 'p-4',
                )}
            >
                <div
                    className={cn(
                        'relative flex w-full items-center justify-center overflow-hidden rounded-md bg-muted/40',
                        prominent ? 'aspect-video' : 'aspect-square',
                    )}
                >
                    {sponsor.logo_url ? (
                        <img
                            src={sponsor.logo_url}
                            alt={sponsor.name}
                            className="size-full object-contain p-2"
                            loading="lazy"
                            onError={(e) => {
                                e.currentTarget.style.display = 'none';
                            }}
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center bg-primary/5 text-base font-semibold text-primary">
                            {sponsor.name
                                .split(/\s+/)
                                .slice(0, 2)
                                .map((w) => w[0]?.toUpperCase() ?? '')
                                .join('')}
                        </div>
                    )}
                </div>
                <div className="space-y-0.5">
                    <p
                        className={cn(
                            'line-clamp-1 font-semibold',
                            prominent ? 'text-sm' : 'text-xs',
                        )}
                    >
                        {sponsor.name}
                    </p>
                    {prominent && sponsor.description ? (
                        <p className="line-clamp-2 text-[11px] leading-relaxed text-muted-foreground">
                            {sponsor.description}
                        </p>
                    ) : null}
                    {linkHref ? (
                        <span className="inline-flex items-center gap-1 text-[10px] text-muted-foreground group-hover:text-primary">
                            {sponsor.website_url ? (
                                <Globe className="size-3" />
                            ) : (
                                <ExternalLink className="size-3" />
                            )}
                            Visit
                        </span>
                    ) : null}
                </div>
            </div>
        </Wrapper>
    );
}
