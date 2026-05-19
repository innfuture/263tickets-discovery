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

export function EventMediaCarousel({
    items,
    fallbackUrl,
    overlay,
}: {
    items: MediaItem[];
    fallbackUrl?: string | null;
    overlay?: React.ReactNode;
}) {
    const slides =
        items.length > 0
            ? items
            : fallbackUrl
              ? [
                    {
                        id: 0,
                        type: 'image',
                        url: fallbackUrl,
                        caption: null,
                        is_primary: true,
                    } as MediaItem,
                ]
              : [];

    if (slides.length === 0) {
        return (
            <div className="relative aspect-5/2 w-full overflow-hidden rounded-xl bg-muted">
                <div className="size-full bg-linear-to-br from-primary/30 via-primary/10 to-transparent" />
                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
                {overlay}
            </div>
        );
    }

    if (slides.length === 1) {
        const slide = slides[0];

        return (
            <div className="relative aspect-5/2 w-full animate-in overflow-hidden rounded-xl bg-muted duration-500 zoom-in-95 fade-in">
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
                {slides.map((slide) => (
                    <CarouselItem key={slide.id} className="pl-0">
                        <div className="relative aspect-5/2 w-full overflow-hidden rounded-xl bg-muted">
                            <MediaSurface item={slide} />
                            {slide.type !== 'video' ? (
                                <div className="pointer-events-none absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
                            ) : null}
                            {slide.caption ? (
                                <Badge
                                    variant="outline"
                                    className="absolute bottom-3 left-3 border-white/40 bg-black/40 text-white"
                                >
                                    {slide.caption}
                                </Badge>
                            ) : null}
                            {slide.is_primary && overlay}
                        </div>
                    </CarouselItem>
                ))}
            </CarouselContent>
            <CarouselPrevious className="top-1/2 left-3 -translate-y-1/2 bg-white/90 hover:bg-white" />
            <CarouselNext className="top-1/2 right-3 -translate-y-1/2 bg-white/90 hover:bg-white" />
        </Carousel>
    );
}
