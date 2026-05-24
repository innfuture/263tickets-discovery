import AKDropdownMenu, { DropdownItem, DropdownItemGroup } from '@atlaskit/dropdown-menu';
import type { ReactElement, ReactNode } from 'react';

export interface DropdownItemSeparator {
    type: 'separator';
}

export interface DropdownItemAction {
    type?: 'action';
    label: ReactNode;
    onClick?: () => void;
    href?: string;
    isDisabled?: boolean;
    isDestructive?: boolean;
    description?: string;
    iconBefore?: ReactNode;
    testId?: string;
}

export interface DropdownItemGroupDef {
    type: 'group';
    title?: string;
    items: DropdownItemAction[];
}

export type DropdownMenuItem = DropdownItemAction | DropdownItemSeparator | DropdownItemGroupDef;

export interface DropdownMenuProps {
    trigger: ReactElement | ((triggerProps: object) => ReactElement) | string;
    items: DropdownMenuItem[];
    placement?: 'bottom-start' | 'bottom-end' | 'top-start' | 'top-end';
    shouldRenderToParent?: boolean;
    testId?: string;
}

export function DropdownMenu({ trigger, items, placement = 'bottom-start', shouldRenderToParent, testId }: DropdownMenuProps) {
    const groups: { title?: string; items: DropdownItemAction[] }[] = [];
    let currentGroup: { title?: string; items: DropdownItemAction[] } = { items: [] };

    for (const item of items) {
        if ('type' in item && item.type === 'separator') {
            if (currentGroup.items.length > 0) {
                groups.push(currentGroup);
                currentGroup = { items: [] };
            }
            continue;
        }
        if ('type' in item && item.type === 'group') {
            if (currentGroup.items.length > 0) {
                groups.push(currentGroup);
                currentGroup = { items: [] };
            }
            groups.push({ title: item.title, items: item.items });
            continue;
        }
        currentGroup.items.push(item as DropdownItemAction);
    }
    if (currentGroup.items.length > 0) {
        groups.push(currentGroup);
    }

    return (
        <AKDropdownMenu
            trigger={trigger as never}
            placement={placement}
            shouldRenderToParent={shouldRenderToParent}
            testId={testId}
        >
            {groups.map((group, gi) => (
                <DropdownItemGroup key={gi} title={group.title}>
                    {group.items.map((item, ii) => (
                        <DropdownItem
                            key={`${gi}-${ii}`}
                            onClick={item.onClick}
                            href={item.href}
                            isDisabled={item.isDisabled}
                            description={item.description}
                            elemBefore={item.iconBefore}
                            testId={item.testId}
                            {...(item.isDestructive ? { 'data-destructive': true } : {})}
                        >
                            {item.label}
                        </DropdownItem>
                    ))}
                </DropdownItemGroup>
            ))}
        </AKDropdownMenu>
    );
}
