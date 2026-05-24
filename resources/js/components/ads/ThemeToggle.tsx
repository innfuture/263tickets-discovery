import ThemeIcon from '@atlaskit/icon/core/theme';
import { useTheme } from '@ds';
import { DropdownMenu } from './DropdownMenu';
import { IconButton } from './Button';

/**
 * Light / dark / auto toggle. Reads + writes the same state managed
 * by ThemeProvider, which persists to localStorage and applies
 * setGlobalTheme on the @atlaskit/tokens runtime.
 *
 * The single `theme` icon stays stable across modes — the dropdown
 * itself tells the user which mode is active via the "Current"
 * description on the selected item.
 */
export function ThemeToggle() {
    const { colorMode, setColorMode } = useTheme();

    return (
        <DropdownMenu
            trigger={<IconButton icon={<ThemeIcon label="" />} label="Toggle theme" />}
            placement="bottom-end"
            items={[
                {
                    label: 'Light',
                    onClick: () => setColorMode('light'),
                    description: colorMode === 'light' ? 'Current' : undefined,
                },
                {
                    label: 'Dark',
                    onClick: () => setColorMode('dark'),
                    description: colorMode === 'dark' ? 'Current' : undefined,
                },
                {
                    label: 'System',
                    onClick: () => setColorMode('auto'),
                    description: colorMode === 'auto' ? 'Current' : undefined,
                },
            ]}
        />
    );
}
