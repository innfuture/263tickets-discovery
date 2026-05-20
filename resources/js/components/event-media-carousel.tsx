import { ImageOff } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

export type MediaItem = {
    id: number;
    type: string; // image | video
    url: string | null;
    caption: string | null;
    is_primary: boolean;
};

function MediaSurface({ item }: { item: MediaItem }) {
    if (!item.url) {
        return null;
    }

    if (item.type === 'video') {
        return (
            <div className="relative size-full bg-black">
                <iframe
                    src={item.url}
                    className="size-full"
                    title={item.caption ?? 'Video'}
                    allowFullScreen
                    loading="lazy"
                />
            </div>
        );
    }

    return (
        <img
            src={item.url}
            alt={item.caption ?? ''}
            className="size-full object-cover"
            onError={(e) => {
                e.currentTarget.style.display = 'none';
            }}
        />
    );
}

/**
 * Builds the carousel slide list with this priority:
 *   1) Banner image (always first when present)
 *   2) All media gallery items (deduped against the banner URL)
 *
 * Falling back to just the banner when no gallery exists.
 */
function buildSlides(
    items: MediaItem[],
    fallbackUrl: string | null | undefined,
): MediaItem[] {
    const slides: MediaItem[] = [];

    if (fallbackUrl) {
        slides.push({
            id: -1,
            type: 'image',
            url: fallbackUrl,
            caption: null,
            is_primary: true,
        });
    }

    for (const item of items) {
        if (item.url && item.url !== fallbackUrl) {
            slides.push(item);
        }
    }

    return slides;
}

export function EventMediaCarousel({
    items,
    fallbackUrl,
    overlay,
}: {
    items: MediaItem[];
    fallbackUrl?: string | null;
    overlay?: React.ReactNode;
}) {
    const slides = buildSlides(items, fallbackUrl);

    if (slides.length === 0) {
        return (
            <div className="relative aspect-5/2 w-full overflow-hidden rounded-xl border bg-muted">
                <div className="size-full bg-linear-to-br from-primary/30 via-primary/10 to-transparent" />
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                    <div className="flex size-12 items-center justify-center rounded-full bg-background/80 shadow-sm">
                        <ImageOff className="size-5" />
                    </div>
                    <p className="text-xs font-medium">No banner or media yet</p>
                </div>
                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
                {overlay}
            </div>
        );
    }

    if (slides.length === 1) {
        const slide = slides[0];

        return (
            <div className="relative aspect-5/2 w-full animate-in overflow-hidden rounded-xl border bg-muted shadow-sm duration-500 zoom-in-95 fade-in">
                <MediaSurface item={slide} />
                {slide.type !== 'video' ? (
                    <div className="pointer-events-none absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
                ) : null}
                {overlay}
            </div>
        );
    }

    return (
        <Carousel
            opts={{ loop: true, align: 'start' }}
            className="relative animate-in duration-500 zoom-in-95 fade-in"
        >
            <CarouselContent className="ml-0">
                {slides.map((slide, index) => (
                    <CarouselItem key={slide.id} className="pl-0">
                        <div className="relative aspect-5/2 w-full overflow-hidden rounded-xl border bg-muted shadow-sm">
                            <MediaSurface item={slide} />
                            {slide.type !== 'video' ? (
                                <div className="pointer-events-none absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
                            ) : null}
                            {slide.caption ? (
                                <Badge
                                    variant="outline"
                                    className="absolute bottom-3 left-3 border-white/40 bg-black/40 text-white backdrop-blur-sm"
                                >
                                    {slide.caption}
                                </Badge>
                            ) : null}
                            {/* Overlay (title/badges) only renders on the first (banner) slide so it
                                doesn't fight with per-slide captions on subsequent images. */}
                            {index === 0 && overlay}
                        </div>
                    </CarouselItem>
                ))}
            </CarouselContent>
            <CarouselPrevious className="top-1/2 left-3 z-20 -translate-y-1/2 bg-white/90 hover:bg-white" />
            <CarouselNext className="top-1/2 right-3 z-20 -translate-y-1/2 bg-white/90 hover:bg-white" />
            <div className="pointer-events-none absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/40 px-2.5 py-1 text-[10px] font-medium text-white backdrop-blur-sm">
                {slides.length} {slides.length === 1 ? 'slide' : 'slides'}
            </div>
        </Carousel>
    );
}
