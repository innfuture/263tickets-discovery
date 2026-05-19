import { MapPin } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

const VenueMapImpl = lazy(() => import('./venue-map-impl'));

export function VenueMap({
    lat,
    lng,
    title,
    className,
}: {
    lat: number | null;
    lng: number | null;
    title?: string;
    className?: string;
}) {
    if (lat == null || lng == null) {
        return (
            <div
                className={`flex flex-col items-center justify-center gap-2 rounded-md bg-muted p-8 text-sm text-muted-foreground ${className ?? ''}`}
            >
                <MapPin className="size-6 opacity-40" />
                Map unavailable — coordinates not set
            </div>
        );
    }

    if (typeof window === 'undefined') {
        return <Skeleton className={`w-full ${className ?? 'aspect-video'}`} />;
    }

    return (
        <Suspense
            fallback={
                <Skeleton className={`w-full ${className ?? 'aspect-video'}`} />
            }
        >
            <div
                className={`relative overflow-hidden rounded-md ${className ?? 'aspect-video'}`}
            >
                <VenueMapImpl lat={lat} lng={lng} title={title} />
            </div>
        </Suspense>
    );
}
