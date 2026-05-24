import AKTabs, { Tab, TabList, TabPanel } from '@atlaskit/tabs';
import type { ReactNode } from 'react';

export interface TabDef {
    label: ReactNode;
    content: ReactNode;
    testId?: string;
}

export interface TabsProps {
    tabs: TabDef[];
    selected?: number;
    defaultSelected?: number;
    onChange?: (index: number) => void;
    id?: string;
    shouldUnmountTabPanelOnChange?: boolean;
    testId?: string;
}

export function Tabs({
    tabs,
    selected,
    defaultSelected = 0,
    onChange,
    id = 'ads-tabs',
    shouldUnmountTabPanelOnChange,
    testId,
}: TabsProps) {
    return (
        <AKTabs
            id={id}
            selected={selected}
            defaultSelected={defaultSelected}
            onChange={onChange}
            shouldUnmountTabPanelOnChange={shouldUnmountTabPanelOnChange}
            testId={testId}
        >
            <TabList>
                {tabs.map((tab, i) => (
                    <Tab key={`tab-${i}`} testId={tab.testId}>
                        {tab.label}
                    </Tab>
                ))}
            </TabList>
            {tabs.map((tab, i) => (
                <TabPanel key={`panel-${i}`}>{tab.content}</TabPanel>
            ))}
        </AKTabs>
    );
}
