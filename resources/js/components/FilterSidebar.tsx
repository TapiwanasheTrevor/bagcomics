import React, { useState, useEffect } from 'react';
import { Filter, X, ChevronDown, ChevronUp, Star, DollarSign, Calendar, Tag, Globe, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

export interface FilterOptions {
    genres: string[];
    tags: string[];
    languages: string[];
    priceRange: 'all' | 'free' | 'paid';
    rating: number;
    matureContent: boolean;
    newReleases: boolean;
    selectedGenres: string[];
    selectedTags: string[];
    selectedLanguages: string[];
}

interface FilterSidebarProps {
    filters: FilterOptions;
    onFiltersChange: (filters: Partial<FilterOptions>) => void;
    onClearFilters: () => void;
    loading?: boolean;
    isMobile?: boolean;
}

const FilterSection: React.FC<{
    title: string;
    icon: React.ReactNode;
    children: React.ReactNode;
    defaultOpen?: boolean;
}> = ({ title, icon, children, defaultOpen = true }) => {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
            <CollapsibleTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-full justify-between p-3 h-auto hover:bg-gray-700/50"
                >
                    <div className="flex items-center space-x-2">
                        {icon}
                        <span className="font-medium">{title}</span>
                    </div>
                    {isOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="px-3 pb-3">
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
};

const FilterContent: React.FC<{
    filters: FilterOptions;
    onFiltersChange: (filters: Partial<FilterOptions>) => void;
    onClearFilters: () => void;
    loading?: boolean;
}> = ({ filters, onFiltersChange, onClearFilters, loading }) => {
    const [availableGenres, setAvailableGenres] = useState<string[]>([]);
    const [availableTags, setAvailableTags] = useState<string[]>([]);
    const [availableLanguages] = useState<string[]>(['English', 'Spanish', 'French', 'German', 'Japanese']);

    useEffect(() => {
        fetchGenres();
        fetchTags();
    }, []);

    const fetchGenres = async () => {
        try {
            const response = await fetch('/api/comics/genres');
            const genres = await response.json();
            setAvailableGenres(genres);
        } catch (error) {
            console.error('Error fetching genres:', error);
        }
    };

    const fetchTags = async () => {
        try {
            const response = await fetch('/api/comics/tags');
            const tags = await response.json();
            setAvailableTags(tags);
        } catch (error) {
            console.error('Error fetching tags:', error);
        }
    };

    const handleGenreChange = (genre: string, checked: boolean) => {
        const selectedGenres = checked
            ? [...filters.selectedGenres, genre]
            : filters.selectedGenres.filter(g => g !== genre);
        onFiltersChange({ selectedGenres });
    };

    const handleTagChange = (tag: string, checked: boolean) => {
        const selectedTags = checked
            ? [...filters.selectedTags, tag]
            : filters.selectedTags.filter(t => t !== tag);
        onFiltersChange({ selectedTags });
    };

    const handleLanguageChange = (language: string, checked: boolean) => {
        const selectedLanguages = checked
            ? [...filters.selectedLanguages, language]
            : filters.selectedLanguages.filter(l => l !== language);
        onFiltersChange({ selectedLanguages });
    };

    const getActiveFiltersCount = () => {
        let count = 0;
        if (filters.selectedGenres.length > 0) count += filters.selectedGenres.length;
        if (filters.selectedTags.length > 0) count += filters.selectedTags.length;
        if (filters.selectedLanguages.length > 0) count += filters.selectedLanguages.length;
        if (filters.priceRange !== 'all') count += 1;
        if (filters.rating > 0) count += 1;
        if (filters.matureContent) count += 1;
        if (filters.newReleases) count += 1;
        return count;
    };

    const activeFiltersCount = getActiveFiltersCount();

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                    <Filter className="h-5 w-5 text-emerald-400" />
                    <h3 className="font-semibold text-lg">Filters</h3>
                    {activeFiltersCount > 0 && (
                        <Badge variant="secondary" className="bg-emerald-500/20 text-emerald-400">
                            {activeFiltersCount}
                        </Badge>
                    )}
                </div>
                {activeFiltersCount > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onClearFilters}
                        className="text-gray-400 hover:text-white"
                    >
                        Clear All
                    </Button>
                )}
            </div>

            <Separator />

            {/* Quick Filters */}
            <FilterSection
                title="Quick Filters"
                icon={<Star className="h-4 w-4" />}
                defaultOpen={true}
            >
                <div className="space-y-3">
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="new-releases"
                            checked={filters.newReleases}
                            onCheckedChange={(checked) => 
                                onFiltersChange({ newReleases: checked as boolean })
                            }
                        />
                        <Label htmlFor="new-releases" className="text-sm">
                            New Releases (Last 30 days)
                        </Label>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="mature-content"
                            checked={filters.matureContent}
                            onCheckedChange={(checked) => 
                                onFiltersChange({ matureContent: checked as boolean })
                            }
                        />
                        <Label htmlFor="mature-content" className="text-sm">
                            Include Mature Content (18+)
                        </Label>
                    </div>
                </div>
            </FilterSection>

            <Separator />

            {/* Price Filter */}
            <FilterSection
                title="Price"
                icon={<DollarSign className="h-4 w-4" />}
                defaultOpen={true}
            >
                <div className="space-y-2">
                    {[
                        { value: 'all', label: 'All Comics' },
                        { value: 'free', label: 'Free Only' },
                        { value: 'paid', label: 'Paid Only' }
                    ].map((option) => (
                        <div key={option.value} className="flex items-center space-x-2">
                            <Checkbox
                                id={`price-${option.value}`}
                                checked={filters.priceRange === option.value}
                                onCheckedChange={() => 
                                    onFiltersChange({ priceRange: option.value as any })
                                }
                            />
                            <Label htmlFor={`price-${option.value}`} className="text-sm">
                                {option.label}
                            </Label>
                        </div>
                    ))}
                </div>
            </FilterSection>

            <Separator />

            {/* Rating Filter */}
            <FilterSection
                title="Minimum Rating"
                icon={<Star className="h-4 w-4" />}
                defaultOpen={false}
            >
                <div className="space-y-2">
                    {[0, 1, 2, 3, 4].map((rating) => (
                        <div key={rating} className="flex items-center space-x-2">
                            <Checkbox
                                id={`rating-${rating}`}
                                checked={filters.rating === rating}
                                onCheckedChange={() => 
                                    onFiltersChange({ rating: filters.rating === rating ? 0 : rating })
                                }
                            />
                            <Label htmlFor={`rating-${rating}`} className="text-sm flex items-center space-x-1">
                                <div className="flex">
                                    {Array.from({ length: 5 }).map((_, i) => (
                                        <Star
                                            key={i}
                                            className={`h-3 w-3 ${
                                                i < rating 
                                                    ? 'text-yellow-400 fill-current' 
                                                    : 'text-gray-400'
                                            }`}
                                        />
                                    ))}
                                </div>
                                <span>{rating === 0 ? 'Any Rating' : `${rating}+ Stars`}</span>
                            </Label>
                        </div>
                    ))}
                </div>
            </FilterSection>

            <Separator />

            {/* Genres */}
            <FilterSection
                title="Genres"
                icon={<BookOpen className="h-4 w-4" />}
                defaultOpen={true}
            >
                <div className="space-y-2 max-h-48 overflow-y-auto">
                    {loading ? (
                        <div className="text-sm text-gray-400">Loading genres...</div>
                    ) : (
                        availableGenres.map((genre) => (
                            <div key={genre} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`genre-${genre}`}
                                    checked={filters.selectedGenres.includes(genre)}
                                    onCheckedChange={(checked) => 
                                        handleGenreChange(genre, checked as boolean)
                                    }
                                />
                                <Label htmlFor={`genre-${genre}`} className="text-sm">
                                    {genre}
                                </Label>
                            </div>
                        ))
                    )}
                </div>
            </FilterSection>

            <Separator />

            {/* Tags */}
            <FilterSection
                title="Tags"
                icon={<Tag className="h-4 w-4" />}
                defaultOpen={false}
            >
                <div className="space-y-2 max-h-48 overflow-y-auto">
                    {loading ? (
                        <div className="text-sm text-gray-400">Loading tags...</div>
                    ) : (
                        availableTags.slice(0, 20).map((tag) => (
                            <div key={tag} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`tag-${tag}`}
                                    checked={filters.selectedTags.includes(tag)}
                                    onCheckedChange={(checked) => 
                                        handleTagChange(tag, checked as boolean)
                                    }
                                />
                                <Label htmlFor={`tag-${tag}`} className="text-sm">
                                    {tag}
                                </Label>
                            </div>
                        ))
                    )}
                </div>
            </FilterSection>

            <Separator />

            {/* Languages */}
            <FilterSection
                title="Languages"
                icon={<Globe className="h-4 w-4" />}
                defaultOpen={false}
            >
                <div className="space-y-2">
                    {availableLanguages.map((language) => (
                        <div key={language} className="flex items-center space-x-2">
                            <Checkbox
                                id={`language-${language}`}
                                checked={filters.selectedLanguages.includes(language)}
                                onCheckedChange={(checked) => 
                                    handleLanguageChange(language, checked as boolean)
                                }
                            />
                            <Label htmlFor={`language-${language}`} className="text-sm">
                                {language}
                            </Label>
                        </div>
                    ))}
                </div>
            </FilterSection>

            {/* Active Filters Summary */}
            {activeFiltersCount > 0 && (
                <>
                    <Separator />
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm text-gray-300">Active Filters:</h4>
                        <div className="flex flex-wrap gap-1">
                            {filters.selectedGenres.map((genre) => (
                                <Badge
                                    key={`genre-${genre}`}
                                    variant="secondary"
                                    className="text-xs bg-emerald-500/20 text-emerald-400"
                                >
                                    {genre}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="ml-1 h-3 w-3 p-0 hover:bg-transparent"
                                        onClick={() => handleGenreChange(genre, false)}
                                    >
                                        <X className="h-2 w-2" />
                                    </Button>
                                </Badge>
                            ))}
                            {filters.selectedTags.map((tag) => (
                                <Badge
                                    key={`tag-${tag}`}
                                    variant="secondary"
                                    className="text-xs bg-purple-500/20 text-purple-400"
                                >
                                    {tag}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="ml-1 h-3 w-3 p-0 hover:bg-transparent"
                                        onClick={() => handleTagChange(tag, false)}
                                    >
                                        <X className="h-2 w-2" />
                                    </Button>
                                </Badge>
                            ))}
                            {filters.priceRange !== 'all' && (
                                <Badge
                                    variant="secondary"
                                    className="text-xs bg-blue-500/20 text-blue-400"
                                >
                                    {filters.priceRange === 'free' ? 'Free' : 'Paid'}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="ml-1 h-3 w-3 p-0 hover:bg-transparent"
                                        onClick={() => onFiltersChange({ priceRange: 'all' })}
                                    >
                                        <X className="h-2 w-2" />
                                    </Button>
                                </Badge>
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};

export const FilterSidebar: React.FC<FilterSidebarProps> = ({
    filters,
    onFiltersChange,
    onClearFilters,
    loading,
    isMobile = false
}) => {
    if (isMobile) {
        return (
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="outline" className="flex items-center space-x-2">
                        <Filter className="h-4 w-4" />
                        <span>Filters</span>
                        {getActiveFiltersCount(filters) > 0 && (
                            <Badge variant="secondary" className="bg-emerald-500/20 text-emerald-400">
                                {getActiveFiltersCount(filters)}
                            </Badge>
                        )}
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" className="w-80 bg-gray-900 border-gray-700">
                    <SheetHeader>
                        <SheetTitle className="text-white">Filter Comics</SheetTitle>
                    </SheetHeader>
                    <div className="mt-6 overflow-y-auto max-h-[calc(100vh-120px)]">
                        <FilterContent
                            filters={filters}
                            onFiltersChange={onFiltersChange}
                            onClearFilters={onClearFilters}
                            loading={loading}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <div className="w-80 bg-gray-800/50 rounded-xl border border-gray-700/50 p-4 h-fit sticky top-24">
            <FilterContent
                filters={filters}
                onFiltersChange={onFiltersChange}
                onClearFilters={onClearFilters}
                loading={loading}
            />
        </div>
    );
};

// Helper function to count active filters
const getActiveFiltersCount = (filters: FilterOptions) => {
    let count = 0;
    if (filters.selectedGenres.length > 0) count += filters.selectedGenres.length;
    if (filters.selectedTags.length > 0) count += filters.selectedTags.length;
    if (filters.selectedLanguages.length > 0) count += filters.selectedLanguages.length;
    if (filters.priceRange !== 'all') count += 1;
    if (filters.rating > 0) count += 1;
    if (filters.matureContent) count += 1;
    if (filters.newReleases) count += 1;
    return count;
};

export default FilterSidebar;