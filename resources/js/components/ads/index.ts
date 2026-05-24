/**
 * ADS wrapper barrel. Import from here (`@ads/...` alias) — never from
 * @atlaskit/* directly in pages or app components. That gives us one
 * place to flip if an SDK rename arrives.
 *
 *   import { Button, TextField, Modal, useToast } from '@ads';
 */

export { Button, IconButton } from './Button';
export type { ButtonProps, ButtonVariant, ButtonSize, IconButtonProps, IconButtonVariant } from './Button';

export { TextField } from './TextField';
export type { TextFieldProps } from './TextField';

export { Textarea } from './Textarea';
export type { TextareaProps } from './Textarea';

export { Select } from './Select';
export type { SelectOption, SelectOptionGroup } from './Select';

export { Checkbox } from './Checkbox';
export type { CheckboxProps } from './Checkbox';

export { RadioGroup } from './RadioGroup';
export type { RadioGroupProps, RadioOption } from './RadioGroup';

export { Toggle } from './Toggle';
export type { ToggleProps } from './Toggle';

export { DatePicker, DateTimePicker } from './DatePicker';

export { Modal } from './Modal';
export type { ModalProps, ModalAction, ModalWidth, ModalAppearance } from './Modal';

export { ConfirmDialog } from './ConfirmDialog';
export type { ConfirmDialogProps } from './ConfirmDialog';

export { Drawer } from './Drawer';
export type { DrawerProps, DrawerWidth } from './Drawer';

export { Tooltip } from './Tooltip';
export type { TooltipProps, TooltipPosition } from './Tooltip';

export { Popup } from './Popup';
export type { PopupProps, PopupPlacement } from './Popup';

export { DropdownMenu } from './DropdownMenu';
export type { DropdownMenuProps, DropdownMenuItem, DropdownItemAction } from './DropdownMenu';

export { StatusBadge } from './StatusBadge';
export type { StatusBadgeProps, TicketStatus } from './StatusBadge';

export { Badge } from './Badge';
export type { BadgeProps, BadgeAppearance } from './Badge';

export { Tag } from './Tag';
export type { TagProps, TagColor } from './Tag';

export { TagGroup } from './TagGroup';
export type { TagGroupProps } from './TagGroup';

export { Avatar, AvatarGroup } from './Avatar';
export type { AvatarProps, AvatarGroupProps, AvatarSize, AvatarPresence, AvatarStatus } from './Avatar';

export { SectionMessage } from './SectionMessage';
export type { SectionMessageProps, SectionMessageAppearance } from './SectionMessage';

export { InlineMessage } from './InlineMessage';
export type { InlineMessageProps, InlineMessageAppearance } from './InlineMessage';

export { Banner } from './Banner';
export type { BannerProps, BannerAppearance } from './Banner';

export { Spinner } from './Spinner';
export type { SpinnerProps, SpinnerSize, SpinnerAppearance } from './Spinner';

export { Skeleton } from './Skeleton';
export type { SkeletonProps } from './Skeleton';

export { ProgressBar } from './ProgressBar';
export type { ProgressBarProps, ProgressBarAppearance } from './ProgressBar';

export { EmptyState } from './EmptyState';
export type { EmptyStateProps } from './EmptyState';

export { Tabs } from './Tabs';
export type { TabsProps, TabDef } from './Tabs';

export { Breadcrumbs } from './Breadcrumbs';
export type { BreadcrumbsProps, BreadcrumbItem } from './Breadcrumbs';

export { Pagination } from './Pagination';
export type { PaginationProps } from './Pagination';

export { InlineEdit } from './InlineEdit';
export type { InlineEditProps } from './InlineEdit';

export { Code, CodeBlock } from './Code';
export type { CodeProps, CodeBlockProps } from './Code';

export { ToastProvider, useToast } from './Toast';
export type { ToastOptions, ToastAction, ToastType } from './Toast';

export { DataTable } from './DataTable';
export type { DataTableProps, Column } from './DataTable';
