import { ExternalLink, Globe, Handshake } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';

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
 * Space-aware sponsor renderer. Top tiers (rank ≤ 3 — Title, Presenting,
 * Platinum) keep their per-tier divider + larger tiles because they're paid
 * to be prominent. Everything else (Gold → Media partner) collapses into a
 * single dense "Supporters & partners" grid — tier hierarchy is preserved
 * via a small pill overlay on each tile instead of a full per-tier section.
 *
 * Reasoning: in real events the tail can have 6+ tiers populated. Giving
 * each its own divider + grid creates a quarter-page of repeating headers
 * for what is, visually, "more logos." This component renders the tail in
 * ~1/3 the vertical space without losing tier attribution.
 */
const PROMINENT_TIER_RANK = 3;

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

    // Partition once, preserving the controller's existing rank → sort_order
    // ordering. Prominent tiers keep their own labelled sections.
    const prominentGroups: Record<string, SponsorView[]> = {};
    const prominentOrder: string[] = [];
    const supporters: SponsorView[] = [];

    for (const s of sponsors) {
        if (s.tier.rank <= PROMINENT_TIER_RANK) {
            const key = s.tier.value;
            if (!prominentGroups[key]) {
                prominentGroups[key] = [];
                prominentOrder.push(key);
            }
            prominentGroups[key].push(s);
        } else {
            supporters.push(s);
        }
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
                {prominentOrder.map((tierKey) => {
                    const list = prominentGroups[tierKey];

                    return (
                        <div key={tierKey} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    {list[0].tier.label}
                                </p>
                                <div className="h-px flex-1 bg-border" />
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {list.map((s) => (
                                    <SponsorTile
                                        key={s.id}
                                        sponsor={s}
                                        variant="prominent"
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}

                {supporters.length > 0 ? (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Supporters &amp; partners
                            </p>
                            <Badge variant="secondary" className="text-[10px]">
                                {supporters.length}
                            </Badge>
                            <div className="h-px flex-1 bg-border" />
                        </div>
                        {/*
                         * Single dense grid for the long tail. 3 → 6 columns
                         * across breakpoints keeps each tile small enough that
                         * 12 sponsors fit in roughly the height of 3 prominent
                         * tiles. Each tile carries a tier pill in the corner
                         * so hierarchy stays visible without per-tier headers.
                         */}
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            {supporters.map((s) => (
                                <SponsorTile
                                    key={s.id}
                                    sponsor={s}
                                    variant="compact"
                                />
                            ))}
                        </div>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

type SponsorTileVariant = 'prominent' | 'compact';

function SponsorTile({
    sponsor,
    variant,
}: {
    sponsor: SponsorView;
    variant: SponsorTileVariant;
}) {
    const linkHref = sponsor.website_url ?? sponsor.social_url ?? null;
    const isCompact = variant === 'compact';

    const Wrapper = linkHref
        ? ({ children }: { children: React.ReactNode }) => (
              <a
                  href={linkHref}
                  target="_blank"
                  rel="noreferrer noopener sponsored"
                  className="group"
                  title={
                      isCompact
                          ? `${sponsor.name} · ${sponsor.tier.label}`
                          : sponsor.name
                  }
              >
                  {children}
              </a>
          )
        : ({ children }: { children: React.ReactNode }) => (
              <div
                  title={
                      isCompact
                          ? `${sponsor.name} · ${sponsor.tier.label}`
                          : sponsor.name
                  }
              >
                  {children}
              </div>
          );

    if (isCompact) {
        // Dense uniform tile for the long-tail "Supporters & partners" grid.
        // Logo dominates; name is a one-liner; tier is a corner pill.
        return (
            <Wrapper>
                <div className="group relative flex flex-col items-center gap-1 rounded-md border bg-card p-2 transition hover:border-primary/40 hover:shadow-sm">
                    <span className="absolute top-1 right-1 rounded-full bg-muted/80 px-1.5 text-[8px] font-semibold tracking-wide text-muted-foreground uppercase backdrop-blur-sm">
                        {sponsor.tier.label.replace(/\s+sponsor$/i, '').slice(0, 12)}
                    </span>
                    <div className="relative flex aspect-square w-full items-center justify-center overflow-hidden rounded bg-muted/30">
                        {sponsor.logo_url ? (
                            <img
                                src={sponsor.logo_url}
                                alt={sponsor.name}
                                className="size-full object-contain p-1.5"
                                loading="lazy"
                                onError={(e) => {
                                    e.currentTarget.style.display = 'none';
                                }}
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center bg-primary/5 text-xs font-semibold text-primary">
                                {sponsor.name
                                    .split(/\s+/)
                                    .slice(0, 2)
                                    .map((w) => w[0]?.toUpperCase() ?? '')
                                    .join('')}
                            </div>
                        )}
                    </div>
                    <p className="line-clamp-1 w-full text-center text-[11px] font-medium">
                        {sponsor.name}
                    </p>
                </div>
            </Wrapper>
        );
    }

    // Prominent tile — Title / Presenting / Platinum tiers only.
    return (
        <Wrapper>
            <div className="flex flex-col items-center gap-2 rounded-lg border bg-card p-4 text-center transition group-hover:border-primary/40 group-hover:shadow-sm">
                <div className="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-md bg-muted/40">
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
                    <p className="line-clamp-1 text-sm font-semibold">
                        {sponsor.name}
                    </p>
                    {sponsor.description ? (
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
