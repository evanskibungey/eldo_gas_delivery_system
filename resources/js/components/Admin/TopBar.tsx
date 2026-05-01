import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    ChevronDown,
    LogOut,
    Settings,
    User,
    Search,
    Menu,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface Props {
    title?: string;
    subtitle?: string;
}

export default function TopBar({ title, subtitle }: Props) {
    const { auth } = usePage().props as any;
    const admin    = auth?.admin;

    const initials = admin?.name
        ? admin.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2)
        : 'AD';

    function logout() {
        router.post('/admin/logout');
    }

    return (
        <header className="flex h-16 shrink-0 items-center justify-between border-b border-slate-100 bg-white px-6 gap-4">

            {/* Left â€” page title */}
            <div className="flex items-center gap-3 min-w-0">
                <div className="min-w-0">
                    {title && (
                        <h1 className="text-base font-semibold text-slate-900 truncate leading-none">
                            {title}
                        </h1>
                    )}
                    {subtitle && (
                        <p className="text-xs text-slate-400 mt-0.5 truncate">{subtitle}</p>
                    )}
                </div>
            </div>

            {/* Right â€” actions */}
            <div className="flex items-center gap-1.5 shrink-0">

                {/* Notifications */}
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 text-slate-500 hover:text-slate-700 hover:bg-slate-100"
                >
                    <Bell className="h-[18px] w-[18px]" />
                    {/* Unread dot */}
                    <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-orange-500 ring-2 ring-white" />
                </Button>

                {/* Divider */}
                <div className="mx-1 h-6 w-px bg-slate-200" />

                {/* User menu */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className={cn(
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5',
                            'border border-transparent hover:border-slate-200 hover:bg-slate-50',
                            'transition-all duration-150 outline-none',
                        )}>
                            {/* Avatar */}
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white text-xs font-bold shadow-sm">
                                {initials}
                            </div>
                            <div className="hidden sm:block text-left leading-none">
                                <p className="text-sm font-medium text-slate-800 leading-none">
                                    {admin?.name ?? 'Admin'}
                                </p>
                                <p className="text-[11px] text-slate-400 mt-0.5">
                                    {admin?.email ?? ''}
                                </p>
                            </div>
                            <ChevronDown className="hidden sm:block h-3.5 w-3.5 text-slate-400 ml-0.5" />
                        </button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" className="w-56 shadow-lg">
                        <DropdownMenuLabel className="font-normal">
                            <div className="flex flex-col">
                                <p className="text-sm font-semibold text-slate-900">{admin?.name}</p>
                                <p className="text-xs text-slate-400 mt-0.5">{admin?.email}</p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/admin/users" className="flex items-center gap-2 cursor-pointer">
                                <User className="h-4 w-4 text-slate-400" />
                                <span>My Profile</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href="/admin/users" className="flex items-center gap-2 cursor-pointer">
                                <Settings className="h-4 w-4 text-slate-400" />
                                <span>Settings</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={logout}
                            className="flex items-center gap-2 text-red-500 focus:text-red-600 focus:bg-red-50 cursor-pointer"
                        >
                            <LogOut className="h-4 w-4" />
                            <span>Sign out</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
