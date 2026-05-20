import {
    Accessibility,
    Baby,
    BatteryCharging,
    Bed,
    Bike,
    Bus,
    Camera,
    Check,
    Coffee,
    CreditCard,
    Drumstick,
    Globe2,
    HeartPulse,
    Lightbulb,
    Mic,
    Monitor,
    Music,
    PawPrint,
    ParkingSquare,
    ShieldCheck,
    ShowerHead,
    Sofa,
    Speaker,
    Trees,
    Tv,
    Umbrella,
    UtensilsCrossed,
    Volume2,
    Wifi,
    Wind,
    Wine,
} from 'lucide-react';

export type AmenityPreset = {
    name: string;
    icon: string;
    category: AmenityCategory;
    description?: string;
};

export type AmenityCategory =
    | 'facility'
    | 'accessibility'
    | 'food'
    | 'tech'
    | 'parking'
    | 'services'
    | 'comfort'
    | 'safety';

/**
 * Maps the stored `icon` string (a kebab-cased lucide name) back to the icon
 * component. Returning `Check` for unknown strings keeps the UI safe rather
 * than crashing when an icon is later renamed or removed upstream.
 */
const ICON_MAP: Record<string, React.ComponentType<{ className?: string }>> = {
    wifi: Wifi,
    'parking-square': ParkingSquare,
    accessibility: Accessibility,
    baby: Baby,
    'battery-charging': BatteryCharging,
    bed: Bed,
    bike: Bike,
    bus: Bus,
    camera: Camera,
    check: Check,
    coffee: Coffee,
    'credit-card': CreditCard,
    drumstick: Drumstick,
    globe: Globe2,
    'heart-pulse': HeartPulse,
    lightbulb: Lightbulb,
    mic: Mic,
    monitor: Monitor,
    music: Music,
    'paw-print': PawPrint,
    'shield-check': ShieldCheck,
    'shower-head': ShowerHead,
    sofa: Sofa,
    speaker: Speaker,
    trees: Trees,
    tv: Tv,
    umbrella: Umbrella,
    'utensils-crossed': UtensilsCrossed,
    'volume-2': Volume2,
    wind: Wind,
    wine: Wine,
};

export function AmenityIcon({
    name,
    className,
}: {
    name: string | null | undefined;
    className?: string;
}) {
    const key = (name ?? '').toLowerCase();
    const Cmp = ICON_MAP[key] ?? Check;

    return <Cmp className={className} />;
}

export function listAvailableIcons(): string[] {
    return Object.keys(ICON_MAP).sort();
}

/**
 * Curated list of common event amenities. The manager UI offers these as
 * one-click presets; users can still add free-form entries afterwards.
 */
export const AMENITY_PRESETS: AmenityPreset[] = [
    { name: 'Free Wi-Fi', icon: 'wifi', category: 'tech' },
    { name: 'On-site parking', icon: 'parking-square', category: 'parking' },
    { name: 'Wheelchair accessible', icon: 'accessibility', category: 'accessibility' },
    { name: 'Restrooms', icon: 'shower-head', category: 'facility' },
    { name: 'Food vendors', icon: 'utensils-crossed', category: 'food' },
    { name: 'Bar / drinks', icon: 'wine', category: 'food' },
    { name: 'Coffee available', icon: 'coffee', category: 'food' },
    { name: 'ATM / cashless payments', icon: 'credit-card', category: 'services' },
    { name: 'Security on-site', icon: 'shield-check', category: 'safety' },
    { name: 'First aid station', icon: 'heart-pulse', category: 'safety' },
    { name: 'Coat check', icon: 'sofa', category: 'comfort' },
    { name: 'Photo booth', icon: 'camera', category: 'comfort' },
    { name: 'Live PA / sound system', icon: 'speaker', category: 'tech' },
    { name: 'Stage lighting', icon: 'lightbulb', category: 'tech' },
    { name: 'Live streaming', icon: 'tv', category: 'tech' },
    { name: 'Charging stations', icon: 'battery-charging', category: 'tech' },
    { name: 'Bike parking', icon: 'bike', category: 'parking' },
    { name: 'Shuttle service', icon: 'bus', category: 'services' },
    { name: 'Outdoor area', icon: 'trees', category: 'comfort' },
    { name: 'Covered seating', icon: 'umbrella', category: 'comfort' },
    { name: 'Kid-friendly', icon: 'baby', category: 'comfort' },
    { name: 'Pet-friendly', icon: 'paw-print', category: 'comfort' },
    { name: 'Air conditioning', icon: 'wind', category: 'comfort' },
    { name: 'AV / projector', icon: 'monitor', category: 'tech' },
];

const CATEGORY_LABELS: Record<AmenityCategory, string> = {
    facility: 'Facility',
    accessibility: 'Accessibility',
    food: 'Food & drink',
    tech: 'Tech & AV',
    parking: 'Parking',
    services: 'Services',
    comfort: 'Comfort',
    safety: 'Safety',
};

export function amenityCategoryLabel(
    key: string | null | undefined,
): string | null {
    if (!key) {
        return null;
    }

    return CATEGORY_LABELS[key as AmenityCategory] ?? null;
}

export const AMENITY_CATEGORIES: { value: AmenityCategory; label: string }[] =
    (Object.entries(CATEGORY_LABELS) as Array<[AmenityCategory, string]>).map(
        ([value, label]) => ({ value, label }),
    );
