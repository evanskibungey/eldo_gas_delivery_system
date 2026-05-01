import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    ShoppingBag,
    Package,
    Users,
    Truck,
    BarChart2,
    Settings,
    Flame,
    ChevronDown,
    Database,
    Tag,
    Layers,
    PlusSquare,
    FileText,
    TrendingUp,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface NavChild {
    label: string;
    href: string;
    icon?: React.ElementType;
}

interface NavItem {
    label: string;
    href?: string;
    icon: React.ElementType;
    badge?: string | number;
    children?: NavChild[];
    section?: string;
}

const navSections: { title?: string; items: NavItem[] }[] = [
    {
        items: [
            { label: 'Dashboard',  href: '/admin/dashboard', icon: LayoutDashboard },
        ],
    },
    {
        title: 'Operations',
        items: [
            { label: 'Orders',   href: '/admin/orders',  icon: ShoppingBag },
            { label: 'Riders',   href: '/admin/riders',  icon: Truck },
            { label: 'Stock',    href: '/admin/stock',   icon: Database },
        ],
    },
    {
        title: 'Catalogue',
        items: [
            {
                label: 'Products', icon: Package,
                children: [
                    { label: 'Cylinder Sizes', href: '/admin/catalogue/sizes',        icon: Layers },
                    { label: 'Gas Brands',     href: '/admin/catalogue/brands',       icon: Tag },
                    { label: 'Pricing',        href: '/admin/catalogue/pricing',      icon: TrendingUp },
                    { label: 'Add-ons',        href: '/admin/catalogue/addon-groups', icon: PlusSquare },
                ],
            },
        ],
    },
    {
        title: 'People',
        items: [
            { label: 'Customers', href: '/admin/customers', icon: Users },
        ],
    },
    {
        title: 'Insights',
        items: [
            {
                label: 'Reports', icon: BarChart2,
                children: [
                    { label: 'Revenue',   href: '/admin/reports/revenue', icon: TrendingUp },
                    { label: 'Orders',    href: '/admin/reports/orders',  icon: FileText },
                ],
            },
        ],
    },
    {
        title: 'System',
        items: [
            { label: 'Admin Users', href: '/admin/users', icon: Settings },
        ],
    },
];

function NavLink({ href, icon: Icon, label, active, badge }: {
    href: string;
    icon: React.ElementType;
    label: string;
    active: boolean;
    badge?: number;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                active
                    ? 'bg-orange-500 text-white shadow-md shadow-orange-500/30'
                    : 'text-slate-400 hover:bg-white/5 hover:text-white',
            )}
        >
            <Icon className={cn(
                'h-[17px] w-[17px] shrink-0 transition-colors',
                active ? 'text-white' : 'text-slate-500 group-hover:text-slate-300',
            )} />
            <span className="flex-1">{label}</span>
            {badge != null && badge > 0 && (
                <span className={cn(
                    'ml-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold tabular-nums',
                    active ? 'bg-white/20 text-white' : 'bg-amber-500 text-white',
                )}>
                    {badge > 99 ? '99+' : badge}
                </span>
            )}
            {active && !badge && (
                <span className="ml-auto h-1.5 w-1.5 rounded-full bg-white/60" />
            )}
        </Link>
    );
}

export default function Sidebar() {
    const { url, props } = usePage();
    const lowStockCount      = (props as any).low_stock_count      as number ?? 0;
    const pendingOrdersCount = (props as any).pending_orders_count as number ?? 0;
    const [openGroups, setOpenGroups] = useState<Set<string>>(() => {
        const open = new Set<string>();
        navSections.forEach(section =>
            section.items.forEach(item => {
                if (item.children?.some(c => url.startsWith(c.href))) {
                    open.add(item.label);
                }
            }),
        );
        return open;
    });

    function toggleGroup(label: string) {
        setOpenGroups(prev => {
            const next = new Set(prev);
            next.has(label) ? next.delete(label) : next.add(label);
            return next;
        });
    }

    return (
        <aside className="flex h-full w-64 shrink-0 flex-col bg-slate-950 border-r border-white/5">

            {/* Logo */}
            <div className="flex h-16 items-center gap-3 px-5 border-b border-white/5">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 shadow-md shadow-orange-500/40">
                    <Flame className="h-4 w-4 text-white" />
                </div>
                <div className="leading-none">
                    <p className="text-sm font-bold text-white tracking-wide">EldoGas</p>
                    <p className="text-[10px] text-slate-500 mt-0.5 uppercase tracking-widest">Admin Portal</p>
                </div>
            </div>

            {/* Navigation */}
            <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-5 scrollbar-none">
                {navSections.map((section, si) => (
                    <div key={si}>
                        {section.title && (
                            <p className="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-600">
                                {section.title}
                            </p>
                        )}

                        <div className="space-y-0.5">
                            {section.items.map(item => {
                                if (item.children) {
                                    const isOpen   = openGroups.has(item.label);
                                    const isActive = item.children.some(c => url.startsWith(c.href));

                                    return (
                                        <div key={item.label}>
                                            <button
                                                onClick={() => toggleGroup(item.label)}
                                                className={cn(
                                                    'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                                    isActive || isOpen
                                                        ? 'text-white bg-white/5'
                                                        : 'text-slate-400 hover:bg-white/5 hover:text-white',
                                                )}
                                            >
                                                <item.icon className={cn(
                                                    'h-[17px] w-[17px] shrink-0',
                                                    isActive ? 'text-orange-400' : 'text-slate-500 group-hover:text-slate-300',
                                                )} />
                                                <span className="flex-1 text-left">{item.label}</span>
                                                <ChevronDown className={cn(
                                                    'h-3.5 w-3.5 text-slate-600 transition-transform duration-200',
                                                    isOpen && 'rotate-180',
                                                )} />
                                            </button>

                                            {isOpen && (
                                                <div className="ml-3 mt-0.5 border-l border-white/5 pl-3 space-y-0.5">
                                                    {item.children.map(child => {
                                                        const childActive = url.startsWith(child.href);
                                                        const ChildIcon = child.icon;
                                                        return (
                                                            <Link
                                                                key={child.href}
                                                                href={child.href}
                                                                className={cn(
                                                                    'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-all duration-150',
                                                                    childActive
                                                                        ? 'bg-orange-500/15 text-orange-400 font-medium'
                                                                        : 'text-slate-500 hover:bg-white/5 hover:text-slate-300',
                                                                )}
                                                            >
                                                                {ChildIcon && (
                                                                    <ChildIcon className="h-3.5 w-3.5 shrink-0" />
                                                                )}
                                                                {child.label}
                                                            </Link>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    );
                                }

                                const badge = item.label === 'Stock'
                                    ? lowStockCount
                                    : item.label === 'Orders'
                                        ? pendingOrdersCount
                                        : undefined;
                                return (
                                    <NavLink
                                        key={item.label}
                                        href={item.href!}
                                        icon={item.icon}
                                        label={item.label}
                                        active={url === item.href || url.startsWith(item.href + '/')}
                                        badge={badge}
                                    />
                                );
                            })}
                        </div>
                    </div>
                ))}
            </nav>

            {/* User footer strip */}
            <div className="border-t border-white/5 px-4 py-3">
                <p className="text-[10px] text-slate-600 text-center">
                    EldoGas © {new Date().getFullYear()}
                </p>
            </div>
        </aside>
    );
}
