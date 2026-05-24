import DynamicTable from '@atlaskit/dynamic-table';
import type { ReactNode } from 'react';
import { EmptyState } from './EmptyState';

export interface Column<T> {
    key: string;
    header: ReactNode;
    isSortable?: boolean;
    /** Width as a column percentage. */
    width?: number;
    render: (row: T) => ReactNode;
}

export interface DataTableProps<T extends { id: string | number }> {
    columns: Column<T>[];
    rows: T[];
    isLoading?: boolean;
    rowsPerPage?: number;
    onSort?: (key: string, order: 'ASC' | 'DESC') => void;
    defaultSortKey?: string;
    defaultSortOrder?: 'ASC' | 'DESC';
    highlightedRowIndex?: number | number[];
    isFixedSize?: boolean;
    caption?: string;
    emptyStateHeading?: string;
    emptyStateDescription?: string;
    emptyStateAction?: ReactNode;
    onRowClick?: (row: T) => void;
    testId?: string;
}

/**
 * @atlaskit/dynamic-table wrapper that takes a typed `columns` +
 * `rows` shape and renders an accessible, sortable, paginated table.
 *
 * Empty state is required-by-default — pass overrides to customise.
 */
export function DataTable<T extends { id: string | number }>({
    columns,
    rows,
    isLoading,
    rowsPerPage = 20,
    onSort,
    defaultSortKey,
    defaultSortOrder = 'ASC',
    highlightedRowIndex,
    isFixedSize = true,
    caption,
    emptyStateHeading = 'No results',
    emptyStateDescription,
    emptyStateAction,
    onRowClick,
    testId,
}: DataTableProps<T>) {
    const head = {
        cells: columns.map((col) => ({
            key: col.key,
            content: col.header,
            isSortable: col.isSortable ?? false,
            width: col.width,
        })),
    };

    const tableRows = rows.map((row) => ({
        key: String(row.id),
        cells: columns.map((col) => ({
            key: col.key,
            content: col.render(row),
        })),
        onClick: onRowClick ? () => onRowClick(row) : undefined,
    }));

    const emptyView = (
        <EmptyState heading={emptyStateHeading} description={emptyStateDescription} primaryAction={emptyStateAction} />
    );

    return (
        <DynamicTable
            head={head}
            rows={tableRows}
            isLoading={isLoading}
            isFixedSize={isFixedSize}
            rowsPerPage={rowsPerPage}
            loadingSpinnerSize="large"
            emptyView={emptyView}
            defaultSortKey={defaultSortKey}
            defaultSortOrder={defaultSortOrder}
            onSort={onSort ? ({ key, sortOrder }: { key: string; sortOrder: 'ASC' | 'DESC' }) => onSort(key, sortOrder) : undefined}
            highlightedRowIndex={highlightedRowIndex}
            testId={testId}
            caption={caption}
        />
    );
}
