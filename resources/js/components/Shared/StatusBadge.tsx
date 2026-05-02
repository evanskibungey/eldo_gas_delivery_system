import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/models';

const config: Record<OrderStatus, { label: string; className: string }> = {
    pending:        { label: 'Pending',         className: 'bg-yellow-100 text-yellow-800 border-yellow-300' },
    rider_assigned: { label: 'Rider Assigned',  className: 'bg-blue-100 text-blue-800 border-blue-300' },
    picked_up:      { label: 'Picked Up',       className: 'bg-indigo-100 text-indigo-800 border-indigo-300' },
    on_the_way:     { label: 'On the Way',      className: 'bg-purple-100 text-purple-800 border-purple-300' },
    delivered:      { label: 'Delivered',       className: 'bg-green-100 text-green-800 border-green-300' },
    cancelled:      { label: 'Cancelled',       className: 'bg-red-100 text-red-800 border-red-300' },
};

interface Props {
    status: OrderStatus;
    className?: string;
}

export default function StatusBadge({ status, className }: Props) {
    const { label, className: statusClass } = config[status] ?? {
        label: status,
        className: 'bg-gray-100 text-gray-700',
    };

    return (
        <Badge variant="outline" className={cn('font-medium', statusClass, className)}>
            {label}
        </Badge>
    );
}
