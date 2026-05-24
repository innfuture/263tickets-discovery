import AKAvatar from '@atlaskit/avatar';
import AKAvatarGroup from '@atlaskit/avatar-group';

export type AvatarSize = 'xsmall' | 'small' | 'medium' | 'large' | 'xlarge' | 'xxlarge';
// AvatarGroup has a narrower set than single Avatar.
export type AvatarGroupSize = 'small' | 'medium' | 'large' | 'xlarge';
export type AvatarPresence = 'online' | 'busy' | 'focus' | 'offline';
export type AvatarStatus = 'approved' | 'declined' | 'locked';
export type AvatarAppearance = 'circle' | 'square';

export interface AvatarProps {
    name: string;
    src?: string;
    size?: AvatarSize;
    appearance?: AvatarAppearance;
    presence?: AvatarPresence;
    status?: AvatarStatus;
    onClick?: () => void;
    href?: string;
    testId?: string;
}

export function Avatar({ name, src, size = 'medium', appearance = 'circle', presence, status, onClick, href, testId }: AvatarProps) {
    return (
        <AKAvatar
            name={name}
            src={src}
            size={size}
            appearance={appearance}
            presence={presence}
            status={status}
            onClick={onClick ? () => onClick() : undefined}
            href={href}
            testId={testId}
        />
    );
}

export interface AvatarGroupItem {
    name: string;
    src?: string;
    href?: string;
    appearance?: AvatarAppearance;
}

export interface AvatarGroupProps {
    avatars: AvatarGroupItem[];
    size?: AvatarGroupSize;
    maxCount?: number;
    appearance?: 'stack' | 'grid';
    testId?: string;
}

export function AvatarGroup({ avatars, size = 'medium', maxCount = 5, appearance = 'stack', testId }: AvatarGroupProps) {
    return (
        <AKAvatarGroup
            data={avatars}
            size={size}
            maxCount={maxCount}
            appearance={appearance}
            testId={testId}
        />
    );
}
