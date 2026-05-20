import { Sparkles, Stars } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';
import {
    AmenityIcon,
    amenityCategoryLabel,
} from '@/lib/amenity-icons';
import { cn } from '@/lib/utils';

export type AmenityView = {
    id: number;
    name: string;
    icon: string | null;
    description: string | null;
    category: string | null;
    is_highlighted: boolean;
};

export function EventAmenitiesSection({
    amenities,
    canEdit = false,
}: {
    amenities: AmenityView[];
    canEdit?: boolean;
}) {
    if (amenities.length === 0) {
        if (!canEdit) {
            return null;
        }

        return (
            <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
                <CardHeader>
                    <SectionTitle icon={<Sparkles className="size-4" />}>
                        Amenities
                    </SectionTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        tone="primary"
                        icon={<Sparkles className="size-6" />}
                        title="No amenities listed yet"
                        description="Help attendees plan their visit by listing what the venue offers."
                    />
                </CardContent>
            </Card>
        );
    }

    const highlighted = amenities.filter((a) => a.is_highlighted);
    const rest = amenities.filter((a) => !a.is_highlighted);

    return (
        <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
            <CardHeader>
                <SectionTitle
                    icon={<Sparkles className="size-4" />}
                    count={amenities.length}
                >
                    Amenities
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-5">
                {highlighted.length > 0 ? (
                    <div className="space-y-2">
                        <div className="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            <Stars className="size-3.5 text-amber-500" />
                            Highlights
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {highlighted.map((a) => (
                                <AmenityTile
                                    key={a.id}
                                    amenity={a}
                                    prominent
                                />
                            ))}
                        </div>
                    </div>
                ) : null}

                {rest.length > 0 ? (
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {rest.map((a) => (
                            <AmenityTile key={a.id} amenity={a} />
                        ))}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function AmenityTile({
    amenity,
    prominent = false,
}: {
    amenity: AmenityView;
    prominent?: boolean;
}) {
    const category = amenityCategoryLabel(amenity.category);

    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-lg border bg-card p-3 transition hover:border-primary/30 hover:bg-primary/3',
                prominent && 'border-amber-400/30 bg-amber-50/30 dark:bg-amber-950/10',
            )}
        >
            <div
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-md',
                    prominent
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                        : 'bg-primary/10 text-primary',
                )}
            >
                <AmenityIcon name={amenity.icon} className="size-4" />
            </div>
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="line-clamp-1 text-sm font-semibold">
                    {amenity.name}
                </p>
                {amenity.description ? (
                    <p className="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                        {amenity.description}
                    </p>
                ) : null}
                {category && !amenity.description ? (
                    <p className="text-[10px] tracking-wide text-muted-foreground uppercase">
                        {category}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
