import { useState, useEffect } from 'react';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Star, Play, Bookmark, TrendingUp, Clock, Gift, Book } from 'lucide-react';
import NavBar from '@/components/NavBar';

interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    cover_image_url?: string;
    genre?: string;
    average_rating: number;
    is_free: boolean;
    is_new_release?: boolean;
    reading_time_estimate?: number;
}


export default function Welcome() {
    const { auth, cms } = usePage<SharedData>().props;
    const [currentSlide, setCurrentSlide] = useState(0);
    const [featuredComics, setFeaturedComics] = useState<Comic[]>([]);
    const [trendingComics, setTrendingComics] = useState<Comic[]>([]);
    const [newComics, setNewComics] = useState<Comic[]>([]);
    const [freeComics, setFreeComics] = useState<Comic[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchComics();
    }, []);

    useEffect(() => {
        if (featuredComics.length > 0) {
            const interval = setInterval(() => {
                setCurrentSlide((prev) => (prev + 1) % featuredComics.length);
            }, 5000);
            return () => clearInterval(interval);
        }
    }, [featuredComics.length]);

    const fetchComics = async () => {
        try {
            const [featuredRes, trendingRes, newRes, freeRes] = await Promise.all([
                fetch('/api/comics?is_featured=1&limit=5'), // Get featured comics marked in backend
                fetch('/api/comics?sort=rating&limit=6'),
                fetch('/api/comics?sort=created_at&limit=6'),
                fetch('/api/comics?is_free=1&limit=6')
            ]);

            const [featured, trending, newReleases, free] = await Promise.all([
                featuredRes.json(),
                trendingRes.json(),
                newRes.json(),
                freeRes.json()
            ]);

            // Use featured comics from backend, fallback to top-rated if none marked
            const featuredData = featured.data || featured;
            if (featuredData.length > 0) {
                setFeaturedComics(featuredData.slice(0, 5));
            } else {
                // Fallback to top-rated comics if no featured comics
                const topRated = await fetch('/api/comics?sort=rating&limit=5').then(res => res.json());
                setFeaturedComics((topRated.data || topRated).slice(0, 5));
            }
            
            setTrendingComics(trending.data || trending);
            setNewComics(newReleases.data || newReleases);
            setFreeComics(free.data || free);
        } catch (error) {
            console.error('Error fetching comics:', error);
            // Set some fallback data or empty arrays
            setFeaturedComics([]);
            setTrendingComics([]);
            setNewComics([]);
            setFreeComics([]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Welcome to BagComics">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-black text-white">
                <NavBar 
                    auth={auth} 
                    currentPage="home"
                    searchValue={searchQuery}
                    onSearchChange={setSearchQuery}
                    onSearch={(query) => {
                        // Universal search - redirect to comics page with search
                        window.location.href = `/comics?search=${encodeURIComponent(query)}`;
                    }}
                />
                {/* Main Content */}
                <main className="flex-1">
                    {loading ? (
                        <div className="flex items-center justify-center min-h-[60vh]">
                            <div className="text-center">
                                <div className="w-16 h-16 border-4 border-red-500/30 border-t-red-500 rounded-full animate-spin mx-auto mb-4"></div>
                                <p className="text-gray-400">Loading amazing comics...</p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Hero Section */}
                            {featuredComics.length > 0 && (
                                <section className="relative min-h-screen flex items-center overflow-hidden">
                                    {/* Background with better gradient overlay */}
                                    <div className="absolute inset-0 bg-gradient-to-r from-black via-black/90 to-black/60 z-10"></div>

                                    {featuredComics.map((comic, index) => (
                                        <div
                                            key={comic.id}
                                            className={`absolute inset-0 transition-opacity duration-1000 ${
                                                index === currentSlide ? 'opacity-100' : 'opacity-0'
                                            }`}
                                        >
                                            <div
                                                className="w-full h-full bg-cover bg-center bg-gray-800"
                                                style={{
                                                    backgroundImage: comic.cover_image_url
                                                        ? `url(${comic.cover_image_url})`
                                                        : cms?.hero?.hero_background_image?.content
                                                        ? `url(${cms.hero.hero_background_image.content})`
                                                        : 'linear-gradient(135deg, #1a1a1a 0%, #2d1b1b 100%)'
                                                }}
                                            ></div>
                                        </div>
                                    ))}

                                    <div className="relative z-20 w-full py-20 md:py-32">
                                        <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
                                            <div className="max-w-4xl">
                                                {/* Hero Title - Smaller fonts */}
                                                <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-6 bg-gradient-to-r from-red-500 via-red-400 to-red-300 bg-clip-text text-transparent leading-tight">
                                                    {cms?.hero?.hero_title?.content || 'African Stories, Boldly Told'}
                                                </h1>
                                                
                                                {/* Hero Subtitle - Better responsive sizing */}
                                                <p className="text-lg sm:text-xl md:text-2xl text-gray-300 mb-10 leading-relaxed max-w-3xl">
                                                    {cms?.hero?.hero_subtitle?.content || 'Discover captivating tales from the heart of Africa. Immerse yourself in rich cultures, legendary heroes, and timeless wisdom through our curated collection of comics.'}
                                                </p>

                                                {/* Featured Comic Card - Better padding and positioning */}
                                                {featuredComics[currentSlide] && (
                                                    <div className="bg-black/70 backdrop-blur-lg rounded-2xl p-6 mb-10 border border-red-900/30 max-w-xl">
                                                        <Link
                                                            href={`/comics/${featuredComics[currentSlide].slug}`}
                                                            className="block"
                                                        >
                                                            <h3 className="text-xl md:text-2xl font-bold text-white mb-2 hover:text-red-400 transition-colors cursor-pointer">
                                                                {featuredComics[currentSlide].title}
                                                            </h3>
                                                        </Link>
                                                        {featuredComics[currentSlide].author && (
                                                            <p className="text-red-400 mb-4 text-sm md:text-base">
                                                                by {featuredComics[currentSlide].author}
                                                            </p>
                                                        )}
                                                        <div className="flex items-center flex-wrap gap-3 mb-4">
                                                            <div className="flex items-center space-x-1">
                                                                <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                                                <span className="text-gray-300 text-sm">
                                                                    {Number(featuredComics[currentSlide].average_rating || 0).toFixed(1)}
                                                                </span>
                                                            </div>
                                                            {featuredComics[currentSlide].genre && (
                                                                <span className="px-3 py-1 bg-red-600/20 text-red-300 rounded-full text-xs border border-red-600/30">
                                                                    {featuredComics[currentSlide].genre}
                                                                </span>
                                                            )}
                                                            {featuredComics[currentSlide].is_free && (
                                                                <span className="px-3 py-1 bg-red-500/20 text-red-300 rounded-full text-xs border border-red-500/30">
                                                                    Free
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* CTA Buttons - Better responsive spacing */}
                                                <div className="flex flex-col sm:flex-row gap-4 max-w-md">
                                                    <Link
                                                        href={featuredComics[currentSlide] ? `/comics/${featuredComics[currentSlide].slug}` : '/comics'}
                                                        className="flex items-center justify-center space-x-2 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105 shadow-lg text-sm md:text-base"
                                                    >
                                                        <Play className="w-4 h-4" />
                                                        <span>{cms?.hero?.hero_cta_primary?.content || 'Start Reading'}</span>
                                                    </Link>
                                                    <Link
                                                        href="/comics"
                                                        className="flex items-center justify-center space-x-2 px-6 py-3 bg-black/80 backdrop-blur-sm text-white font-semibold rounded-lg border border-gray-600 hover:bg-gray-800/80 transition-all duration-300 text-sm md:text-base"
                                                    >
                                                        <Book className="w-4 h-4" />
                                                        <span>{cms?.hero?.hero_cta_secondary?.content || 'Browse Collection'}</span>
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Slide Navigation - Better positioning */}
                                    <div className="absolute bottom-6 left-1/2 transform -translate-x-1/2 z-20">
                                        <div className="flex space-x-3 bg-black/50 backdrop-blur-sm rounded-full px-4 py-2">
                                            {featuredComics.map((_, index) => (
                                                <button
                                                    key={index}
                                                    onClick={() => setCurrentSlide(index)}
                                                    className={`w-2 h-2 rounded-full transition-all duration-300 ${
                                                        index === currentSlide
                                                            ? 'bg-red-500 scale-150'
                                                            : 'bg-gray-400 hover:bg-gray-300'
                                                    }`}
                                                />
                                            ))}
                                        </div>
                                    </div>

                                    {/* Navigation Arrows - Better positioning */}
                                    <button
                                        onClick={() => setCurrentSlide((prev) => (prev - 1 + featuredComics.length) % featuredComics.length)}
                                        className="absolute left-6 top-1/2 transform -translate-y-1/2 z-20 p-2 bg-black/60 backdrop-blur-sm text-white rounded-full border border-red-900/30 hover:bg-black/80 hover:border-red-500/50 transition-all duration-300"
                                    >
                                        <ChevronLeft className="w-5 h-5" />
                                    </button>
                                    <button
                                        onClick={() => setCurrentSlide((prev) => (prev + 1) % featuredComics.length)}
                                        className="absolute right-6 top-1/2 transform -translate-y-1/2 z-20 p-2 bg-black/60 backdrop-blur-sm text-white rounded-full border border-red-900/30 hover:bg-black/80 hover:border-red-500/50 transition-all duration-300"
                                    >
                                        <ChevronRight className="w-5 h-5" />
                                    </button>
                                </section>
                            )}
                            {/* Comic Sections */}
                            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-16 space-y-16">
                                {/* Trending Comics */}
                                {trendingComics.length > 0 && (
                                    <ComicSection
                                        title={cms?.general?.trending_title?.content || "Trending Now"}
                                        subtitle={cms?.general?.trending_subtitle?.content || "Most popular comics this week"}
                                        icon={<TrendingUp className="w-6 h-6" />}
                                        comics={trendingComics}
                                        accentColor="red"
                                    />
                                )}

                                {/* New Releases */}
                                {newComics.length > 0 && (
                                    <ComicSection
                                        title={cms?.general?.new_releases_title?.content || "New Releases"}
                                        subtitle={cms?.general?.new_releases_subtitle?.content || "Fresh stories just added"}
                                        icon={<Clock className="w-6 h-6" />}
                                        comics={newComics}
                                        accentColor="orange"
                                    />
                                )}

                                {/* Free Comics */}
                                {freeComics.length > 0 && (
                                    <ComicSection
                                        title={cms?.general?.free_comics_title?.content || "Free to Read"}
                                        subtitle={cms?.general?.free_comics_subtitle?.content || "Start your journey at no cost"}
                                        icon={<Gift className="w-6 h-6" />}
                                        comics={freeComics}
                                        accentColor="purple"
                                    />
                                )}
                            </div>
                        </>
                    )}
                </main>

                {/* Footer */}
                <footer className="bg-black border-t border-red-900/30 py-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                            <div className="col-span-1 md:col-span-2">
                                <Link href="/" className="flex items-center space-x-3 mb-4">
                                    <img 
                                        src="/images/image.png" 
                                        alt="BAG Comics Logo" 
                                        className="h-10 w-auto"
                                    />
                                    <div className="text-2xl font-bold bg-gradient-to-r from-red-500 via-red-400 to-red-300 bg-clip-text text-transparent">
                                        BAG Comics
                                    </div>
                                </Link>
                                <p className="text-gray-400 mb-6 max-w-md">
                                    {cms?.general?.site_description?.content || 'Celebrating African storytelling through captivating comics. Discover heroes, legends, and adventures from across the continent.'}
                                </p>
                                <div className="flex space-x-4">
                                    {!auth.user && (
                                        <>
                                            <Link
                                                href="/register"
                                                className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300"
                                            >
                                                Get Started
                                            </Link>
                                            <Link
                                                href="/login"
                                                className="px-6 py-3 bg-gray-700 text-white font-semibold rounded-lg border border-gray-600 hover:bg-gray-600 transition-all duration-300"
                                            >
                                                Sign In
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>

                            <div>
                                <h3 className="text-white font-semibold mb-4">Explore</h3>
                                <ul className="space-y-2 text-gray-400">
                                    <li><Link href="/comics" className="hover:text-white transition-colors">All Comics</Link></li>
                                    <li><Link href="/comics?is_free=1" className="hover:text-white transition-colors">Free Comics</Link></li>
                                    <li><Link href="/comics?sort=rating" className="hover:text-white transition-colors">Top Rated</Link></li>
                                    <li><Link href="/comics?sort=created_at" className="hover:text-white transition-colors">New Releases</Link></li>
                                </ul>
                            </div>

                            <div>
                                <h3 className="text-white font-semibold mb-4">Account</h3>
                                <ul className="space-y-2 text-gray-400">
                                    {auth.user ? (
                                        <>
                                            <li><Link href="/dashboard" className="hover:text-white transition-colors">Dashboard</Link></li>
                                            <li><Link href="/library" className="hover:text-white transition-colors">My Library</Link></li>
                                            <li><Link href="/progress" className="hover:text-white transition-colors">Reading Progress</Link></li>
                                        </>
                                    ) : (
                                        <>
                                            <li><Link href="/login" className="hover:text-white transition-colors">Sign In</Link></li>
                                            <li><Link href="/register" className="hover:text-white transition-colors">Create Account</Link></li>
                                        </>
                                    )}
                                </ul>
                            </div>
                        </div>

                        <div className="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                            <p>{cms?.footer?.footer_copyright?.content || '&copy; 2024 BAG Comics. Celebrating African storytelling.'}</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

// Comic Section Component
interface ComicSectionProps {
    title: string;
    subtitle: string;
    icon: React.ReactNode;
    comics: Comic[];
    accentColor: 'red' | 'orange' | 'purple';
}

function ComicSection({ title, subtitle, icon, comics, accentColor }: ComicSectionProps) {
    const colorClasses = {
        red: {
            text: 'text-red-400',
            bg: 'bg-red-500/20',
            border: 'border-red-500/30',
            hover: 'hover:border-red-400'
        },
        orange: {
            text: 'text-orange-400',
            bg: 'bg-orange-500/20',
            border: 'border-orange-500/30',
            hover: 'hover:border-orange-400'
        },
        purple: {
            text: 'text-purple-400',
            bg: 'bg-purple-500/20',
            border: 'border-purple-500/30',
            hover: 'hover:border-purple-400'
        }
    };

    const colors = colorClasses[accentColor];

    return (
        <section>
            <div className="flex items-center space-x-3 mb-8">
                <div className={`p-3 ${colors.bg} ${colors.text} rounded-xl border ${colors.border}`}>
                    {icon}
                </div>
                <div>
                    <h2 className="text-3xl font-bold text-white">{title}</h2>
                    <p className="text-gray-400">{subtitle}</p>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {comics.map((comic) => (
                    <ComicCard key={comic.id} comic={comic} accentColor={accentColor} />
                ))}
            </div>
        </section>
    );
}

// Comic Card Component
interface ComicCardProps {
    comic: Comic;
    accentColor: 'red' | 'orange' | 'purple';
}

function ComicCard({ comic, accentColor }: ComicCardProps) {
    const colorClasses = {
        red: {
            text: 'text-red-400',
            bg: 'bg-red-500/20',
            border: 'border-red-500/30',
            hover: 'hover:border-red-400'
        },
        orange: {
            text: 'text-orange-400',
            bg: 'bg-orange-500/20',
            border: 'border-orange-500/30',
            hover: 'hover:border-orange-400'
        },
        purple: {
            text: 'text-purple-400',
            bg: 'bg-purple-500/20',
            border: 'border-purple-500/30',
            hover: 'hover:border-purple-400'
        }
    };

    const colors = colorClasses[accentColor];

    return (
        <Link
            href={`/comics/${comic.slug}`}
            className={`group bg-gray-800 rounded-xl overflow-hidden border border-gray-700 ${colors.hover} transition-all duration-300 hover:transform hover:scale-105 hover:shadow-xl block cursor-pointer`}
        >
            <div className="relative aspect-[3/4] overflow-hidden">
                <div
                    className="w-full h-full bg-cover bg-center bg-gray-700 transition-transform duration-300 group-hover:scale-110"
                    style={{
                        backgroundImage: comic.cover_image_url
                            ? `url(${comic.cover_image_url})`
                            : 'linear-gradient(135deg, #374151 0%, #4b5563 100%)'
                    }}
                ></div>
                <div className="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-60"></div>
                <div className="absolute inset-0 bg-red-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                {/* Badges */}
                <div className="absolute top-3 left-3 flex flex-col space-y-2">
                    {comic.is_free && (
                        <span className="px-2 py-1 bg-red-500/90 text-red-100 text-xs font-semibold rounded-full">
                            Free
                        </span>
                    )}
                    {comic.is_new_release && (
                        <span className="px-2 py-1 bg-orange-500/90 text-orange-100 text-xs font-semibold rounded-full">
                            New
                        </span>
                    )}
                </div>

                {/* Action Buttons */}
                <div className="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <button className="p-2 bg-gray-800/80 backdrop-blur-sm text-white rounded-full border border-gray-600 hover:bg-gray-700/80 transition-colors">
                        <Bookmark className="w-4 h-4" />
                    </button>
                </div>

                {/* Play Button */}
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <button className="p-4 bg-white/20 backdrop-blur-sm text-white rounded-full border border-white/30 hover:bg-white/30 transition-colors">
                        <Play className="w-6 h-6" />
                    </button>
                </div>
            </div>

            <div className="p-4">
                <h3 className="text-white font-semibold text-lg mb-2 line-clamp-2 group-hover:text-red-400 transition-colors">
                    {comic.title}
                </h3>

                {comic.author && (
                    <p className="text-gray-400 text-sm mb-3">by {comic.author}</p>
                )}

                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-1">
                        <Star className="w-4 h-4 text-yellow-400 fill-current" />
                        <span className="text-gray-300 text-sm">{Number(comic.average_rating || 0).toFixed(1)}</span>
                    </div>

                    {comic.reading_time_estimate && (
                        <div className="flex items-center space-x-1 text-gray-400 text-sm">
                            <Clock className="w-4 h-4" />
                            <span>{comic.reading_time_estimate}m</span>
                        </div>
                    )}
                </div>

                {comic.genre && (
                    <div className="mt-3">
                        <span className={`inline-block px-3 py-1 ${colors.bg} ${colors.text} rounded-full text-xs border ${colors.border}`}>
                            {comic.genre}
                        </span>
                    </div>
                )}
            </div>
        </Link>
    );
}






