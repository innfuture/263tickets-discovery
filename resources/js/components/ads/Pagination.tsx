import AKPagination from '@atlaskit/pagination';

export interface PaginationProps {
    total: number;
    current?: number;
    onChange?: (page: number) => void;
    /** Number of page-number buttons to render. Defaults to 5. */
    max?: number;
    testId?: string;
}

export function Pagination({ total, current = 1, onChange, max = 5, testId }: PaginationProps) {
    const pages = Array.from({ length: total }, (_, i) => i + 1);

    return (
        <AKPagination
            pages={pages}
            selectedIndex={current - 1}
            onChange={(_, page) => onChange?.(page as number)}
            max={max}
            testId={testId}
        />
    );
}
