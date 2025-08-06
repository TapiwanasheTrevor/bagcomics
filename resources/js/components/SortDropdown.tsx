import React from 'react';
import { ChevronDown, ArrowUpDown, Star, Calendar, BookOpen, Users, TrendingUp, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface SortOption {
    value: string;
    label: string;
    icon: React.ReactNode;
    description?: string;
}

interface SortDropdownProps {
    value: string;
    onChange: (value: string) => void;
    options?: SortOption[];
    className?: string;
}

const defaultSortOptions: SortOption[] = [
    {
        value: 'published_at',
        label: 'Newest First',
        icon: <Calendar className="h-4 w-4" />,
        description: 'Recently published comics'
    },
    {
        value: 'average_rating',
        label: 'Highest Rated',
        icon: <Star className="h-4 w-4" />,
        description: 'Best rated by users'
    },
    {
        value: 'total_readers',
        label: 'Most Popular',
        icon: <Users className="h-4 w-4" />,
        description: 'Most read comics'
    },
    {
        value: 'title',
        label: 'Alphabetical',
        icon: <ArrowUpDown className="h-4 w-4" />,
        description: 'A to Z by title'
    },
    {
        value: 'page_count',
        label: 'Page Count',
        icon: <BookOpen className="h-4 w-4" />,
        description: 'Longest comics first'
    },
    {
        value: 'trending',
        label: 'Trending',
        icon: <TrendingUp className="h-4 w-4" />,
        description: 'Currently popular'
    },
    {
        value: 'recently_added',
        label: 'Recently Added',
        icon: <Clock className="h-4 w-4" />,
        description: 'Latest additions to library'
    }
];

export const SortDropdown: React.FC<SortDropdownProps> = ({
    value,
    onChange,
    options = defaultSortOptions,
    className = ""
}) => {
    const selectedOption = options.find(option => option.value === value) || options[0];

    const handleSortChange = (sortValue: string) => {
        onChange(sortValue);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className={`flex items-center space-x-2 bg-gray-700/50 border-gray-600 hover:bg-gray-600/50 hover:border-gray-500 ${className}`}
                >
                    {selectedOption.icon}
                    <span className="hidden sm:inline">Sort by:</span>
                    <span className="font-medium">{selectedOption.label}</span>
                    <ChevronDown className="h-4 w-4 opacity-70" />
                </Button>
            </DropdownMenuTrigger>
            
            <DropdownMenuContent 
                align="end" 
                className="w-64 bg-gray-800 border-gray-700"
            >
                <DropdownMenuLabel className="text-gray-300">
                    Sort Comics By
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="bg-gray-700" />
                
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onClick={() => handleSortChange(option.value)}
                        className={`flex items-start space-x-3 p-3 cursor-pointer hover:bg-gray-700/50 focus:bg-gray-700/50 ${
                            value === option.value 
                                ? 'bg-emerald-500/10 text-emerald-400 border-l-2 border-emerald-500' 
                                : 'text-gray-300'
                        }`}
                    >
                        <div className="flex-shrink-0 mt-0.5">
                            {option.icon}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="font-medium text-sm">
                                {option.label}
                            </div>
                            {option.description && (
                                <div className="text-xs text-gray-400 mt-0.5">
                                    {option.description}
                                </div>
                            )}
                        </div>
                        {value === option.value && (
                            <div className="flex-shrink-0">
                                <div className="h-2 w-2 bg-emerald-500 rounded-full"></div>
                            </div>
                        )}
                    </DropdownMenuItem>
                ))}
                
                <DropdownMenuSeparator className="bg-gray-700" />
                
                <div className="p-2 text-xs text-gray-400 text-center">
                    {options.length} sorting options available
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

// Preset sort option sets for different contexts
export const discoverySortOptions: SortOption[] = [
    {
        value: 'published_at',
        label: 'Newest',
        icon: <Calendar className="h-4 w-4" />,
        description: 'Recently published'
    },
    {
        value: 'average_rating',
        label: 'Top Rated',
        icon: <Star className="h-4 w-4" />,
        description: 'Highest rated'
    },
    {
        value: 'total_readers',
        label: 'Popular',
        icon: <Users className="h-4 w-4" />,
        description: 'Most read'
    },
    {
        value: 'trending',
        label: 'Trending',
        icon: <TrendingUp className="h-4 w-4" />,
        description: 'Currently hot'
    },
    {
        value: 'title',
        label: 'A-Z',
        icon: <ArrowUpDown className="h-4 w-4" />,
        description: 'Alphabetical'
    }
];

export const librarySortOptions: SortOption[] = [
    {
        value: 'recently_added',
        label: 'Recently Added',
        icon: <Clock className="h-4 w-4" />,
        description: 'Latest in library'
    },
    {
        value: 'last_read',
        label: 'Recently Read',
        icon: <BookOpen className="h-4 w-4" />,
        description: 'Last opened'
    },
    {
        value: 'progress',
        label: 'Reading Progress',
        icon: <TrendingUp className="h-4 w-4" />,
        description: 'By completion'
    },
    {
        value: 'title',
        label: 'Title A-Z',
        icon: <ArrowUpDown className="h-4 w-4" />,
        description: 'Alphabetical'
    },
    {
        value: 'rating',
        label: 'My Rating',
        icon: <Star className="h-4 w-4" />,
        description: 'Your ratings'
    }
];

export default SortDropdown;