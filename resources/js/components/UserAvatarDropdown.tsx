import { Link } from '@inertiajs/react';
import { User, Settings, LogOut, ChevronDown, Library } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';

interface UserAvatarDropdownProps {
    user: {
        name: string;
        email: string;
        avatar?: string;
    };
    className?: string;
}

export default function UserAvatarDropdown({ user, className = "" }: UserAvatarDropdownProps) {
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className={`flex items-center space-x-2 px-3 py-2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg transition-all duration-300 hover:bg-emerald-500/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 ${className}`}>
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold text-sm">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <ChevronDown className="h-4 w-4 opacity-70" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end">
                <DropdownMenuLabel>
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium">{user.name}</p>
                        <p className="text-xs text-muted-foreground">{user.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href="/dashboard" className="flex items-center cursor-pointer">
                            <User className="mr-2 h-4 w-4" />
                            <span>Profile</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile" className="flex items-center cursor-pointer">
                            <Settings className="mr-2 h-4 w-4" />
                            <span>Settings</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/library" className="flex items-center cursor-pointer">
                            <Library className="mr-2 h-4 w-4" />
                            <span>My Library</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild variant="destructive">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center cursor-pointer w-full"
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        <span>Log out</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
