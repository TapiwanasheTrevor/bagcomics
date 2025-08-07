import React, { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { 
    Download, Maximize2, Minimize2, ChevronLeft, ChevronRight, RotateCw, 
    ZoomIn, ZoomOut, Eye, EyeOff, RefreshCw, Bookmark, BookmarkCheck,
    Settings, Play, Pause, SkipBack, SkipForward, Grid3X3, List,
    MousePointer, Move, Search, X, Plus, Minus, Home, Menu
} from 'lucide-react';
import { Document, Page, pdfjs } from 'react-pdf';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Set up PDF.js worker
const setupPdfWorker = () => {
    try {
        pdfjs.GlobalWorkerOptions.workerSrc = '/js/pdfjs/pdf.worker.min.js';
        pdfjs.GlobalWorkerOptions.verbosity = pdfjs.VerbosityLevel.ERRORS;
    } catch (error) {
        console.warn('PDF.js worker setup failed:', error);
        pdfjs.GlobalWorkerOptions.workerSrc = '';
    }
};

setupPdfWorker();

interface Bookmark {
    id: string;
    page: number;
    note?: string;
    created_at: string;
}

interface ReadingProgress {
    current_page: number;
    total_pages: number;
    progress_percentage: number;
    reading_time_minutes: number;
    is_completed: boolean;
}

interface ReaderSettings {
    theme: 'dark' | 'light' | 'sepia';
    autoAdvance: boolean;
    autoAdvanceDelay: number;
    fitMode: 'width' | 'height' | 'page';
    showPageNumbers: boolean;
    enableGestures: boolean;
    scrollDirection: 'horizontal' | 'vertical';
}

interface EnhancedPdfReaderProps {
    fileUrl: string;
    fileName?: string;
    downloadUrl?: string;
    onPageChange?: (page: number) => void;
    initialPage?: number;
    userHasDownloadAccess?: boolean;
    comicSlug?: string;
    onClose?: () => void;
}

const EnhancedPdfReader: React.FC<EnhancedPdfReaderProps> = ({
    fileUrl,
    fileName = 'document.pdf',
    downloadUrl,
    onPageChange,
    initialPage = 1,
    userHasDownloadAccess = false,
    comicSlug,
    onClose
}) => {
    // Core state
    const [isFullscreen, setIsFullscreen] = useState<boolean>(true);
    const [loading, setLoading] = useState<boolean>(true);
    const [numPages, setNumPages] = useState<number>(0);
    const [currentPage, setCurrentPage] = useState<number>(initialPage);
    const [scale, setScale] = useState<number>(1.2);
    const [rotation, setRotation] = useState<number>(0);
    const [error, setError] = useState<string | null>(null);
    
    // Enhanced features state
    const [bookmarks, setBookmarks] = useState<Bookmark[]>([]);
    const [showBookmarkPanel, setShowBookmarkPanel] = useState<boolean>(false);
    const [showSettingsPanel, setShowSettingsPanel] = useState<boolean>(false);
    const [showProgressBar, setShowProgressBar] = useState<boolean>(true);
    const [isAutoPlaying, setIsAutoPlaying] = useState<boolean>(false);
    const [showThumbnails, setShowThumbnails] = useState<boolean>(false);
    const [isPanning, setIsPanning] = useState<boolean>(false);
    const [panPosition, setPanPosition] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
    
    // Reading progress
    const [readingProgress, setReadingProgress] = useState<ReadingProgress>({
        current_page: initialPage,
        total_pages: 0,
        progress_percentage: 0,
        reading_time_minutes: 0,
        is_completed: false
    });
    
    // Reader settings
    const [settings, setSettings] = useState<ReaderSettings>({
        theme: 'dark',
        autoAdvance: false,
        autoAdvanceDelay: 5000,
        fitMode: 'width',
        showPageNumbers: true,
        enableGestures: true,
        scrollDirection: 'horizontal'
    });

    // Refs
    const containerRef = useRef<HTMLDivElement>(null);
    const pageRef = useRef<HTMLDivElement>(null);
    const autoPlayIntervalRef = useRef<NodeJS.Timeout | null>(null);
    const readingStartTime = useRef<number>(Date.now());
    const touchStartRef = useRef<{ x: number; y: number } | null>(null);

    // Load bookmarks on mount
    useEffect(() => {
        if (comicSlug) {
            loadBookmarks();
            loadReadingProgress();
        }
    }, [comicSlug]);

    // Auto-advance functionality
    useEffect(() => {
        if (isAutoPlaying && settings.autoAdvance) {
            autoPlayIntervalRef.current = setInterval(() => {
                if (currentPage < numPages) {
                    goToNextPage();
                } else {
                    setIsAutoPlaying(false);
                }
            }, settings.autoAdvanceDelay);
        } else {
            if (autoPlayIntervalRef.current) {
                clearInterval(autoPlayIntervalRef.current);
                autoPlayIntervalRef.current = null;
            }
        }

        return () => {
            if (autoPlayIntervalRef.current) {
                clearInterval(autoPlayIntervalRef.current);
            }
        };
    }, [isAutoPlaying, settings.autoAdvance, settings.autoAdvanceDelay, currentPage, numPages]);

    // Enhanced touch gesture handling
    useEffect(() => {
        if (!settings.enableGestures) return;

        let touchStartTime = 0;
        let touchStartDistance = 0;
        let initialScale = scale;
        let initialPanPosition = panPosition;

        const handleTouchStart = (e: TouchEvent) => {
            touchStartTime = Date.now();
            
            if (e.touches.length === 1) {
                touchStartRef.current = {
                    x: e.touches[0].clientX,
                    y: e.touches[0].clientY
                };
            } else if (e.touches.length === 2) {
                // Pinch zoom start
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                touchStartDistance = Math.sqrt(
                    Math.pow(touch2.clientX - touch1.clientX, 2) +
                    Math.pow(touch2.clientY - touch1.clientY, 2)
                );
                initialScale = scale;
                initialPanPosition = panPosition;
            }
        };

        const handleTouchMove = (e: TouchEvent) => {
            e.preventDefault(); // Prevent scrolling

            if (e.touches.length === 2) {
                // Pinch zoom
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                const currentDistance = Math.sqrt(
                    Math.pow(touch2.clientX - touch1.clientX, 2) +
                    Math.pow(touch2.clientY - touch1.clientY, 2)
                );

                if (touchStartDistance > 0) {
                    const scaleChange = currentDistance / touchStartDistance;
                    const newScale = Math.max(0.5, Math.min(3.0, initialScale * scaleChange));
                    setScale(newScale);
                }
            } else if (e.touches.length === 1 && scale > 1.2) {
                // Pan when zoomed in
                if (touchStartRef.current) {
                    const deltaX = e.touches[0].clientX - touchStartRef.current.x;
                    const deltaY = e.touches[0].clientY - touchStartRef.current.y;
                    
                    setPanPosition({
                        x: initialPanPosition.x + deltaX,
                        y: initialPanPosition.y + deltaY
                    });
                }
            }
        };

        const handleTouchEnd = (e: TouchEvent) => {
            const touchDuration = Date.now() - touchStartTime;
            
            if (!touchStartRef.current || e.changedTouches.length !== 1) {
                touchStartRef.current = null;
                return;
            }

            const touchEnd = {
                x: e.changedTouches[0].clientX,
                y: e.changedTouches[0].clientY
            };

            const deltaX = touchEnd.x - touchStartRef.current.x;
            const deltaY = touchEnd.y - touchStartRef.current.y;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

            // Double tap to zoom
            if (touchDuration < 300 && distance < 10) {
                if (scale > 1.2) {
                    resetZoom();
                } else {
                    setScale(2.0);
                }
            }
            // Swipe navigation (only if not zoomed in significantly)
            else if (scale <= 1.5 && Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
                if (deltaX > 0) {
                    goToPrevPage();
                } else {
                    goToNextPage();
                }
            }

            touchStartRef.current = null;
        };

        const container = containerRef.current;
        if (container) {
            container.addEventListener('touchstart', handleTouchStart, { passive: false });
            container.addEventListener('touchmove', handleTouchMove, { passive: false });
            container.addEventListener('touchend', handleTouchEnd);

            return () => {
                container.removeEventListener('touchstart', handleTouchStart);
                container.removeEventListener('touchmove', handleTouchMove);
                container.removeEventListener('touchend', handleTouchEnd);
            };
        }
    }, [settings.enableGestures, currentPage, numPages, scale, panPosition]);

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (showSettingsPanel || showBookmarkPanel) return;

            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    goToPrevPage();
                    break;
                case 'ArrowRight':
                case 'ArrowDown':
                case ' ':
                    e.preventDefault();
                    goToNextPage();
                    break;
                case 'Home':
                    e.preventDefault();
                    goToPage(1);
                    break;
                case 'End':
                    e.preventDefault();
                    goToPage(numPages);
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    zoomOut();
                    break;
                case 'f':
                case 'F11':
                    e.preventDefault();
                    toggleFullscreen();
                    break;
                case 'b':
                    e.preventDefault();
                    toggleBookmark();
                    break;
                case 'Escape':
                    e.preventDefault();
                    if (showBookmarkPanel) setShowBookmarkPanel(false);
                    else if (showSettingsPanel) setShowSettingsPanel(false);
                    else onClose?.();
                    break;
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [currentPage, numPages, showBookmarkPanel, showSettingsPanel]);

    const loadBookmarks = async () => {
        if (!comicSlug) return;

        try {
            const response = await fetch(`/api/comics/${comicSlug}/bookmarks`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setBookmarks(data.data || []);
            }
        } catch (error) {
            console.error('Failed to load bookmarks:', error);
        }
    };

    const loadReadingProgress = async () => {
        if (!comicSlug) return;

        try {
            const response = await fetch(`/api/comics/${comicSlug}/progress`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setReadingProgress(data);
                if (data.current_page > 1) {
                    setCurrentPage(data.current_page);
                }
            }
        } catch (error) {
            console.error('Failed to load reading progress:', error);
        }
    };

    const updateProgress = useCallback(async (page: number, totalPages: number) => {
        if (!comicSlug) return;

        const readingTimeMinutes = Math.floor((Date.now() - readingStartTime.current) / 60000);
        const progressData = {
            current_page: page,
            total_pages: totalPages,
            reading_time_minutes: readingTimeMinutes
        };

        setReadingProgress(prev => ({
            ...prev,
            ...progressData,
            progress_percentage: (page / totalPages) * 100,
            is_completed: page >= totalPages
        }));

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch(`/api/comics/${comicSlug}/progress`, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify(progressData)
            });
        } catch (error) {
            console.error('Failed to update reading progress:', error);
        }
    }, [comicSlug]);

    const onDocumentLoadSuccess = useCallback(({ numPages }: { numPages: number }) => {
        setNumPages(numPages);
        setLoading(false);
        setError(null);
        setReadingProgress(prev => ({ ...prev, total_pages: numPages }));
    }, []);

    const onDocumentLoadError = useCallback((error: any) => {
        console.error('PDF load error:', error);
        setError(error?.message || 'Failed to load PDF document');
        setLoading(false);
    }, []);

    const goToPage = useCallback((page: number) => {
        if (page >= 1 && page <= numPages) {
            setCurrentPage(page);
            onPageChange?.(page);
            updateProgress(page, numPages);
        }
    }, [numPages, onPageChange, updateProgress]);

    const goToPrevPage = useCallback(() => {
        if (currentPage > 1) {
            goToPage(currentPage - 1);
        }
    }, [currentPage, goToPage]);

    const goToNextPage = useCallback(() => {
        if (currentPage < numPages) {
            goToPage(currentPage + 1);
        }
    }, [currentPage, numPages, goToPage]);

    const zoomIn = useCallback(() => {
        setScale(prev => Math.min(prev + 0.2, 3.0));
    }, []);

    const zoomOut = useCallback(() => {
        setScale(prev => Math.max(prev - 0.2, 0.5));
    }, []);

    const resetZoom = useCallback(() => {
        setScale(1.2);
        setPanPosition({ x: 0, y: 0 });
    }, []);

    const rotate = useCallback(() => {
        setRotation(prev => (prev + 90) % 360);
    }, []);

    const toggleFullscreen = useCallback(() => {
        setIsFullscreen(prev => !prev);
    }, []);

    const toggleAutoPlay = useCallback(() => {
        setIsAutoPlaying(prev => !prev);
    }, []);

    const toggleBookmark = useCallback(async () => {
        if (!comicSlug) return;

        const existingBookmark = bookmarks.find(b => b.page === currentPage);
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (existingBookmark) {
                // Remove bookmark
                const response = await fetch(`/api/comics/${comicSlug}/bookmarks/${existingBookmark.id}`, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    }
                });

                if (response.ok) {
                    setBookmarks(prev => prev.filter(b => b.id !== existingBookmark.id));
                }
            } else {
                // Add bookmark
                const response = await fetch(`/api/comics/${comicSlug}/bookmarks`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({
                        page_number: currentPage,
                        note: `Bookmark on page ${currentPage}`
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    setBookmarks(prev => [...prev, data]);
                }
            }
        } catch (error) {
            console.error('Failed to toggle bookmark:', error);
        }
    }, [comicSlug, currentPage, bookmarks]);

    const isBookmarked = useMemo(() => {
        return bookmarks.some(b => b.page === currentPage);
    }, [bookmarks, currentPage]);

    const progressPercentage = useMemo(() => {
        return numPages > 0 ? (currentPage / numPages) * 100 : 0;
    }, [currentPage, numPages]);

    return (
        <div className={`fixed inset-0 z-50 bg-black text-white flex flex-col ${settings.theme === 'light' ? 'bg-gray-100 text-gray-900' : settings.theme === 'sepia' ? 'bg-amber-50 text-amber-900' : ''}`}>
            {/* Top Controls */}
            <div className="bg-black/95 backdrop-blur-sm border-b border-red-500/30 p-2 sm:p-4 flex items-center justify-between flex-shrink-0">
                <div className="flex items-center gap-2 sm:gap-4 min-w-0">
                    <button
                        onClick={onClose}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                        title="Close Reader"
                    >
                        <X className="h-4 w-4" />
                    </button>
                    <h3 className="text-white font-medium truncate text-sm sm:text-base max-w-[120px] sm:max-w-xs">{fileName}</h3>
                    {settings.showPageNumbers && numPages > 0 && (
                        <span className="text-gray-400 text-xs sm:text-sm whitespace-nowrap">
                            {currentPage}/{numPages}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-1 sm:gap-2 overflow-x-auto">
                    {/* Navigation Controls */}
                    <div className="hidden sm:flex items-center gap-2">
                        <button
                            onClick={() => goToPage(1)}
                            disabled={currentPage <= 1}
                            className="p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            title="First Page"
                        >
                            <SkipBack className="h-4 w-4" />
                        </button>
                    </div>

                    <button
                        onClick={goToPrevPage}
                        disabled={currentPage <= 1}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500/50"
                        title="Previous Page"
                    >
                        <ChevronLeft className="h-3 w-3 sm:h-4 sm:w-4" />
                    </button>

                    <div className="hidden sm:block">
                        <button
                            onClick={toggleAutoPlay}
                            className={`p-2 rounded-lg border transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50 ${isAutoPlaying ? 'bg-red-500 text-white border-red-500 hover:bg-red-600' : 'bg-red-500/20 text-red-400 border-red-500/30 hover:bg-red-500/30'}`}
                            title={isAutoPlaying ? "Pause Auto-advance" : "Start Auto-advance"}
                        >
                            {isAutoPlaying ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
                        </button>
                    </div>

                    <button
                        onClick={goToNextPage}
                        disabled={currentPage >= numPages}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500/50"
                        title="Next Page"
                    >
                        <ChevronRight className="h-3 w-3 sm:h-4 sm:w-4" />
                    </button>

                    <div className="hidden sm:flex items-center gap-2">
                        <button
                            onClick={() => goToPage(numPages)}
                            disabled={currentPage >= numPages}
                            className="p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            title="Last Page"
                        >
                            <SkipForward className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="w-px h-4 sm:h-6 bg-red-500/30 mx-1 sm:mx-2" />

                    {/* Zoom Controls */}
                    <button
                        onClick={zoomOut}
                        disabled={scale <= 0.5}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50 disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Zoom Out"
                    >
                        <ZoomOut className="h-3 w-3 sm:h-4 sm:w-4" />
                    </button>

                    <span className="text-white text-xs sm:text-sm px-1 sm:px-2 min-w-[3rem] sm:min-w-[4rem] text-center">
                        {Math.round(scale * 100)}%
                    </span>

                    <button
                        onClick={zoomIn}
                        disabled={scale >= 3.0}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50 disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Zoom In"
                    >
                        <ZoomIn className="h-3 w-3 sm:h-4 sm:w-4" />
                    </button>

                    <div className="hidden sm:block">
                        <button
                            onClick={resetZoom}
                            className="p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            title="Reset Zoom"
                        >
                            <Home className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="w-px h-4 sm:h-6 bg-red-500/30 mx-1 sm:mx-2" />

                    {/* Feature Controls */}
                    <button
                        onClick={toggleBookmark}
                        className={`p-1.5 sm:p-2 rounded-lg border transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50 ${isBookmarked ? 'bg-red-500 text-white border-red-500 hover:bg-red-600' : 'bg-red-500/20 text-red-400 border-red-500/30 hover:bg-red-500/30'}`}
                        title={isBookmarked ? "Remove Bookmark" : "Add Bookmark"}
                    >
                        {isBookmarked ? <BookmarkCheck className="h-3 w-3 sm:h-4 sm:w-4" /> : <Bookmark className="h-3 w-3 sm:h-4 sm:w-4" />}
                    </button>

                    <div className="hidden sm:block">
                        <button
                            onClick={() => setShowBookmarkPanel(!showBookmarkPanel)}
                            className="p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            title="Show Bookmarks"
                        >
                            <List className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="hidden md:block">
                        <button
                            onClick={() => setShowThumbnails(!showThumbnails)}
                            className="p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            title="Show Thumbnails"
                        >
                            <Grid3X3 className="h-4 w-4" />
                        </button>
                    </div>

                    <button
                        onClick={() => setShowSettingsPanel(!showSettingsPanel)}
                        className="p-1.5 sm:p-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                        title="Reader Settings"
                    >
                        <Settings className="h-3 w-3 sm:h-4 sm:w-4" />
                    </button>
                </div>
            </div>

            {/* Progress Bar */}
            {showProgressBar && (
                <div className="bg-black px-4 py-2 border-b border-red-500/30">
                    <div className="flex items-center gap-4">
                        <div className="flex-1 bg-red-500/20 border border-red-500/30 rounded-full h-2">
                            <div 
                                className="bg-red-500 h-2 rounded-full transition-all duration-300"
                                style={{ width: `${progressPercentage}%` }}
                            />
                        </div>
                        <span className="text-sm text-gray-400 min-w-[4rem]">
                            {Math.round(progressPercentage)}%
                        </span>
                    </div>
                </div>
            )}

            <div className="flex-1 flex min-h-0">
                {/* Main Content */}
                <div className="flex-1 relative">
                    {/* PDF Viewer */}
                    <div
                        ref={containerRef}
                        className="h-full bg-black overflow-auto flex items-center justify-center"
                        style={{
                            cursor: isPanning ? 'grabbing' : 'grab'
                        }}
                    >
                        {loading && (
                            <div className="absolute inset-0 flex items-center justify-center bg-black z-10">
                                <div className="text-center">
                                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-red-500 mx-auto mb-4"></div>
                                    <p className="text-gray-300">Loading PDF...</p>
                                </div>
                            </div>
                        )}

                        {error && !loading && (
                            <div className="absolute inset-0 flex items-center justify-center bg-black z-10">
                                <div className="text-center max-w-md p-6">
                                    <div className="text-red-400 mb-4">
                                        <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-white mb-2">Failed to Load PDF</h3>
                                    <p className="text-gray-300 mb-4">{error}</p>
                                    <button
                                        onClick={() => window.location.reload()}
                                        className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                                    >
                                        <RefreshCw className="w-4 h-4 inline mr-2" />
                                        Retry
                                    </button>
                                </div>
                            </div>
                        )}

                        <Document
                            file={useMemo(() => ({
                                url: fileUrl,
                                httpHeaders: {
                                    'Accept': 'application/pdf',
                                    'Cache-Control': 'no-cache',
                                },
                                withCredentials: true,
                            }), [fileUrl])}
                            options={useMemo(() => ({
                                cMapUrl: '/js/pdfjs/cmaps/',
                                cMapPacked: true,
                            }), [])}
                            onLoadSuccess={onDocumentLoadSuccess}
                            onLoadError={onDocumentLoadError}
                            loading={null}
                            error={null}
                        >
                            <div
                                ref={pageRef}
                                style={{
                                    transform: `translate(${panPosition.x}px, ${panPosition.y}px)`
                                }}
                            >
                                <Page
                                    pageNumber={currentPage}
                                    scale={scale}
                                    rotate={rotation}
                                    loading={
                                        <div className="text-center text-gray-300 p-8">
                                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-red-500 mx-auto mb-2"></div>
                                            <p className="text-sm">Loading page {currentPage}...</p>
                                        </div>
                                    }
                                    error={
                                        <div className="text-center text-red-400 p-8">
                                            <p>Failed to load page {currentPage}</p>
                                        </div>
                                    }
                                    className="shadow-lg max-w-full"
                                    renderTextLayer={false}
                                    renderAnnotationLayer={false}
                                    canvasProps={{
                                        className: 'max-w-full h-auto',
                                        style: {
                                            maxWidth: '100%',
                                            height: 'auto'
                                        }
                                    }}
                                />
                            </div>
                        </Document>
                    </div>
                </div>

                {/* Bookmark Panel */}
                {showBookmarkPanel && (
                    <div className="w-full sm:w-80 bg-black border-l border-red-500/30 flex flex-col absolute sm:relative inset-0 sm:inset-auto z-10 sm:z-auto">
                        <div className="p-4 border-b border-red-500/30">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold">Bookmarks</h3>
                                <button
                                    onClick={() => setShowBookmarkPanel(false)}
                                    className="p-1 rounded bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-auto p-4">
                            {bookmarks.length === 0 ? (
                                <p className="text-gray-400 text-center py-8">No bookmarks yet</p>
                            ) : (
                                <div className="space-y-2">
                                    {bookmarks.map((bookmark) => (
                                        <div
                                            key={bookmark.id}
                                            className="p-3 bg-red-500/20 border border-red-500/30 rounded-lg cursor-pointer hover:bg-red-500/30 transition-all duration-300"
                                            onClick={() => goToPage(bookmark.page)}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">Page {bookmark.page}</span>
                                                <span className="text-xs text-gray-400">
                                                    {new Date(bookmark.created_at).toLocaleDateString()}
                                                </span>
                                            </div>
                                            {bookmark.note && (
                                                <p className="text-sm text-gray-300 mt-1">{bookmark.note}</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Settings Panel */}
                {showSettingsPanel && (
                    <div className="w-80 bg-black border-l border-red-500/30 flex flex-col">
                        <div className="p-4 border-b border-red-500/30">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold">Reader Settings</h3>
                                <button
                                    onClick={() => setShowSettingsPanel(false)}
                                    className="p-1 rounded bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-all duration-300"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-auto p-4 space-y-6">
                            {/* Theme */}
                            <div>
                                <label className="block text-sm font-medium mb-2">Theme</label>
                                <select
                                    value={settings.theme}
                                    onChange={(e) => setSettings(prev => ({ ...prev, theme: e.target.value as any }))}
                                    className="w-full p-2 bg-red-500/20 border border-red-500/30 rounded-lg text-white hover:bg-red-500/30 transition-all duration-300"
                                >
                                    <option value="dark">Dark</option>
                                    <option value="light">Light</option>
                                    <option value="sepia">Sepia</option>
                                </select>
                            </div>

                            {/* Auto-advance */}
                            <div>
                                <label className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        checked={settings.autoAdvance}
                                        onChange={(e) => setSettings(prev => ({ ...prev, autoAdvance: e.target.checked }))}
                                        className="rounded"
                                    />
                                    <span className="text-sm">Auto-advance pages</span>
                                </label>
                                {settings.autoAdvance && (
                                    <div className="mt-2">
                                        <label className="block text-xs text-gray-400 mb-1">Delay (seconds)</label>
                                        <input
                                            type="range"
                                            min="2"
                                            max="10"
                                            value={settings.autoAdvanceDelay / 1000}
                                            onChange={(e) => setSettings(prev => ({ ...prev, autoAdvanceDelay: parseInt(e.target.value) * 1000 }))}
                                            className="w-full"
                                        />
                                        <span className="text-xs text-gray-400">{settings.autoAdvanceDelay / 1000}s</span>
                                    </div>
                                )}
                            </div>

                            {/* Fit Mode */}
                            <div>
                                <label className="block text-sm font-medium mb-2">Fit Mode</label>
                                <select
                                    value={settings.fitMode}
                                    onChange={(e) => setSettings(prev => ({ ...prev, fitMode: e.target.value as any }))}
                                    className="w-full p-2 bg-red-500/20 border border-red-500/30 rounded-lg text-white hover:bg-red-500/30 transition-all duration-300"
                                >
                                    <option value="width">Fit Width</option>
                                    <option value="height">Fit Height</option>
                                    <option value="page">Fit Page</option>
                                </select>
                            </div>

                            {/* Show Page Numbers */}
                            <div>
                                <label className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        checked={settings.showPageNumbers}
                                        onChange={(e) => setSettings(prev => ({ ...prev, showPageNumbers: e.target.checked }))}
                                        className="rounded"
                                    />
                                    <span className="text-sm">Show page numbers</span>
                                </label>
                            </div>

                            {/* Enable Gestures */}
                            <div>
                                <label className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        checked={settings.enableGestures}
                                        onChange={(e) => setSettings(prev => ({ ...prev, enableGestures: e.target.checked }))}
                                        className="rounded"
                                    />
                                    <span className="text-sm">Enable touch gestures</span>
                                </label>
                            </div>

                            {/* Progress Bar */}
                            <div>
                                <label className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        checked={showProgressBar}
                                        onChange={(e) => setShowProgressBar(e.target.checked)}
                                        className="rounded"
                                    />
                                    <span className="text-sm">Show progress bar</span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default EnhancedPdfReader;