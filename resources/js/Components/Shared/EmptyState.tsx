import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

export default function EmptyState({ icon: Icon, title, description, action, className }: Props) {
    return (
        <div className={cn('flex flex-col items-center justify-center py-16 text-center', className)}>
            {Icon && (
                <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <Icon className="h-7 w-7 text-muted-foreground" />
                </div>
            )}
            <h3 className="text-base font-semibold text-foreground">{title}</h3>
            {description && (
                <p className="mt-1 text-sm text-muted-foreground max-w-sm">{description}</p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
