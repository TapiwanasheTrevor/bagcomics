import { Link } from '@inertiajs/react';
import { User, Settings, LogOut, Library } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

interface UserMobileMenuProps {
    user: {
        name: string;
        email: string;
        avatar?: string;
    };
    onMenuClose: () => void;
    className?: string;
}

export default function UserMobileMenu({ user, onMenuClose, className = "" }: UserMobileMenuProps) {
    const getInitials = useInitials();

    return (
        <div className={`mx-4 space-y-2 ${className}`}>
            <div className="flex items-center space-x-3 px-4 py-3 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg">
                <Avatar className="h-8 w-8">
                    <AvatarImage src={user.avatar} alt={user.name} />
                    <AvatarFallback className="bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold text-sm">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                <div>
                    <p className="font-semibold">{user.name || 'User'}</p>
                    <p className="text-xs text-emerald-300">{user.email}</p>
                </div>
            </div>
            <div className="space-y-1">
                <Link
                    href="/dashboard"
                    className="flex items-center space-x-2 px-4 py-2 text-muted-foreground hover:text-foreground hover:bg-accent rounded-lg transition-colors"
                    onClick={onMenuClose}
                >
                    <User className="w-4 h-4" />
                    <span>Profile</span>
                </Link>
                <Link
                    href="/settings/profile"
                    className="flex items-center space-x-2 px-4 py-2 text-muted-foreground hover:text-foreground hover:bg-accent rounded-lg transition-colors"
                    onClick={onMenuClose}
                >
                    <Settings className="w-4 h-4" />
                    <span>Settings</span>
                </Link>
                <Link
                    href="/library"
                    className="flex items-center space-x-2 px-4 py-2 text-muted-foreground hover:text-foreground hover:bg-accent rounded-lg transition-colors"
                    onClick={onMenuClose}
                >
                    <Library className="w-4 h-4" />
                    <span>My Library</span>
                </Link>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex items-center space-x-2 px-4 py-2 text-destructive hover:text-destructive-foreground hover:bg-destructive/10 rounded-lg transition-colors w-full text-left"
                    onClick={onMenuClose}
                >
                    <LogOut className="w-4 h-4" />
                    <span>Log out</span>
                </Link>
            </div>
        </div>
    );
}
