import { Link } from '@inertiajs/react';
import { User, Settings, LogOut, ChevronDown, Library } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { useLogout } from '@/hooks/use-logout';

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
    const handleLogout = useLogout();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className={`flex items-center space-x-2 px-3 py-2 bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg transition-all duration-300 hover:bg-red-500/30 focus:outline-none focus:ring-2 focus:ring-red-500/50 ${className}`}>
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="bg-gradient-to-r from-red-500 to-purple-500 text-white font-semibold text-sm">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <ChevronDown className="h-4 w-4 opacity-70" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 shadow-lg" align="end">
                <DropdownMenuLabel>
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium text-gray-900 dark:text-white">{user.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{user.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href="/dashboard" className="flex items-center cursor-pointer text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white">
                            <User className="mr-2 h-4 w-4" />
                            <span>Profile</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile" className="flex items-center cursor-pointer text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white">
                            <Settings className="mr-2 h-4 w-4" />
                            <span>Settings</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/library" className="flex items-center cursor-pointer text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white">
                            <Library className="mr-2 h-4 w-4" />
                            <span>My Library</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild variant="destructive">
                    <button
                        onClick={handleLogout}
                        className="flex items-center cursor-pointer w-full text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        <span>Log out</span>
                    </button>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
