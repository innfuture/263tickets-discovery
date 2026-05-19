import useEmblaCarousel, {
    type UseEmblaCarouselType,
} from 'embla-carousel-react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type CarouselApi = UseEmblaCarouselType[1];
type UseCarouselParameters = Parameters<typeof useEmblaCarousel>;
type CarouselOptions = UseCarouselParameters[0];
type CarouselPlugin = UseCarouselParameters[1];

type CarouselContextProps = {
    carouselRef: ReturnType<typeof useEmblaCarousel>[0];
    api: CarouselApi;
    scrollPrev: () => void;
    scrollNext: () => void;
    canScrollPrev: boolean;
    canScrollNext: boolean;
};

const CarouselContext = React.createContext<CarouselContextProps | null>(null);

function useCarousel() {
    const ctx = React.useContext(CarouselContext);
    if (!ctx) {
        throw new Error('useCarousel must be used within a <Carousel />');
    }
    return ctx;
}

function Carousel({
    opts,
    plugins,
    setApi,
    className,
    children,
    ...props
}: React.ComponentProps<'div'> & {
    opts?: CarouselOptions;
    plugins?: CarouselPlugin;
    setApi?: (api: CarouselApi) => void;
}) {
    const [carouselRef, api] = useEmblaCarousel(
        { ...opts, axis: 'x' },
        plugins,
    );
    const [canScrollPrev, setCanScrollPrev] = React.useState(false);
    const [canScrollNext, setCanScrollNext] = React.useState(false);

    const onSelect = React.useCallback((api: CarouselApi) => {
        if (!api) return;
        setCanScrollPrev(api.canScrollPrev());
        setCanScrollNext(api.canScrollNext());
    }, []);

    const scrollPrev = React.useCallback(() => {
        api?.scrollPrev();
    }, [api]);

    const scrollNext = React.useCallback(() => {
        api?.scrollNext();
    }, [api]);

    React.useEffect(() => {
        if (!api || !setApi) return;
        setApi(api);
    }, [api, setApi]);

    React.useEffect(() => {
        if (!api) return;
        onSelect(api);
        api.on('reInit', onSelect);
        api.on('select', onSelect);
        return () => {
            api.off('select', onSelect);
        };
    }, [api, onSelect]);

    return (
        <CarouselContext.Provider
            value={{
                carouselRef,
                api,
                scrollPrev,
                scrollNext,
                canScrollPrev,
                canScrollNext,
            }}
        >
            <div
                className={cn('relative', className)}
                role="region"
                aria-roledescription="carousel"
                {...props}
            >
                {children}
            </div>
        </CarouselContext.Provider>
    );
}

function CarouselContent({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    const { carouselRef } = useCarousel();
    return (
        <div ref={carouselRef} className="overflow-hidden">
            <div className={cn('flex', className)} {...props} />
        </div>
    );
}

function CarouselItem({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            role="group"
            aria-roledescription="slide"
            className={cn('min-w-0 shrink-0 grow-0 basis-full', className)}
            {...props}
        />
    );
}

function CarouselPrevious({
    className,
    variant = 'outline',
    size = 'icon',
    ...props
}: React.ComponentProps<typeof Button>) {
    const { scrollPrev, canScrollPrev } = useCarousel();
    return (
        <Button
            variant={variant}
            size={size}
            className={cn(
                'absolute top-1/2 left-3 size-8 -translate-y-1/2 rounded-full bg-white/90 shadow-md hover:bg-white',
                className,
            )}
            disabled={!canScrollPrev}
            onClick={scrollPrev}
            {...props}
        >
            <ChevronLeft className="size-4" />
            <span className="sr-only">Previous slide</span>
        </Button>
    );
}

function CarouselNext({
    className,
    variant = 'outline',
    size = 'icon',
    ...props
}: React.ComponentProps<typeof Button>) {
    const { scrollNext, canScrollNext } = useCarousel();
    return (
        <Button
            variant={variant}
            size={size}
            className={cn(
                'absolute top-1/2 right-3 size-8 -translate-y-1/2 rounded-full bg-white/90 shadow-md hover:bg-white',
                className,
            )}
            disabled={!canScrollNext}
            onClick={scrollNext}
            {...props}
        >
            <ChevronRight className="size-4" />
            <span className="sr-only">Next slide</span>
        </Button>
    );
}

export {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
    type CarouselApi,
};
