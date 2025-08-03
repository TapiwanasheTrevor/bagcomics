import React, { useState, useCallback, useEffect, useRef } from 'react';
import { Download, Maximize2, Minimize2, ChevronLeft, ChevronRight, RotateCw, ZoomIn, ZoomOut, Eye, EyeOff, RefreshCw } from 'lucide-react';
import { Document, Page, pdfjs } from 'react-pdf';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Set up PDF.js worker with multiple fallback options
const setupPdfWorker = () => {
    try {
        // Try local worker first (if available)
        const localWorkerSrc = '/build/assets/pdf.worker.min.js';

        // Fallback to CDN
        const cdnWorkerSrc = `https://unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.js`;

        // Use local worker file to avoid CORS and MIME type issues
        pdfjs.GlobalWorkerOptions.workerSrc = '/js/pdfjs/pdf.worker.min.js';

        // Disable worker if there are issues (fallback to main thread)
        if (typeof window !== 'undefined' && window.location.protocol === 'file:') {
            pdfjs.GlobalWorkerOptions.workerSrc = '';
        }

        // Configure for better compatibility
        pdfjs.GlobalWorkerOptions.verbosity = pdfjs.VerbosityLevel.ERRORS;

        console.log('PDF.js worker configured:', pdfjs.GlobalWorkerOptions.workerSrc);
    } catch (error) {
        console.warn('PDF.js worker setup failed:', error);
        // Disable worker as fallback
        pdfjs.GlobalWorkerOptions.workerSrc = '';
    }
};

setupPdfWorker();

interface PdfError {
    name: string;
    message: string;
    details?: {
        url: string;
        retryCount: number;
        workerSrc: string;
        userAgent: string;
    };
}

interface PdfViewerProps {
    fileUrl: string;
    fileName?: string;
    downloadUrl?: string;
    onPageChange?: (page: number) => void;
    initialPage?: number;
    userHasDownloadAccess?: boolean;
    comicSlug?: string;
}

interface PdfError {
    name: string;
    message: string;
    details?: any;
}

const PdfViewer: React.FC<PdfViewerProps> = ({
    fileUrl,
    fileName = 'document.pdf',
    downloadUrl,
    onPageChange,
    initialPage = 1,
    userHasDownloadAccess = false,
    comicSlug
}) => {
    const [isFullscreen, setIsFullscreen] = useState<boolean>(false);
    const [loading, setLoading] = useState<boolean>(true);
    const [numPages, setNumPages] = useState<number>(0);
    const [currentPage, setCurrentPage] = useState<number>(initialPage);
    const [scale, setScale] = useState<number>(1.2);
    const [rotation, setRotation] = useState<number>(0);
    const [showProtectionOverlay, setShowProtectionOverlay] = useState<boolean>(true);
    const [useFallback, setUseFallback] = useState<boolean>(false);
    const [error, setError] = useState<PdfError | null>(null);
    const [retryCount, setRetryCount] = useState<number>(0);
    const [pageLoading, setPageLoading] = useState<boolean>(false);
    const [viewMode, setViewMode] = useState<'single' | 'continuous'>('single');
    const containerRef = useRef<HTMLDivElement>(null);
    const documentRef = useRef<any>(null);
    const [readingStartTime, setReadingStartTime] = useState<number>(Date.now());

    // Debug logging
    useEffect(() => {
        console.log('PdfViewer initialized with fileUrl:', fileUrl);
    }, [fileUrl]);

    // Progress tracking function
    const updateProgress = useCallback(async (page: number, totalPages: number) => {
        if (!comicSlug) return;

        try {
            const readingTimeMinutes = Math.floor((Date.now() - readingStartTime) / 60000);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            await fetch(`/api/progress/comics/${comicSlug}`, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    current_page: page,
                    total_pages: totalPages,
                    reading_time_minutes: readingTimeMinutes
                })
            });
        } catch (error) {
            console.error('Failed to update reading progress:', error);
        }
    }, [comicSlug, readingStartTime]);

    // Auto-fallback timeout
    useEffect(() => {
        if (loading && !useFallback) {
            const timeout = setTimeout(() => {
                console.log('PDF loading timeout - switching to fallback mode');
                setUseFallback(true);
                setLoading(false);
            }, 10000); // 10 second timeout

            return () => clearTimeout(timeout);
        }
    }, [loading, useFallback]);

    // Screenshot protection and dev tools detection
    useEffect(() => {
        let devToolsOpen = false;
        let warningShown = false;

        const handleKeyDown = (e: KeyboardEvent) => {
            // Disable common screenshot shortcuts
            if (
                (e.ctrlKey && e.shiftKey && (e.key === 'S' || e.key === 'I')) || // Chrome DevTools
                (e.key === 'F12') || // DevTools
                (e.ctrlKey && e.key === 'u') || // View Source
                (e.ctrlKey && e.key === 's') || // Save Page
                (e.key === 'PrintScreen') || // Print Screen
                (e.metaKey && e.shiftKey && e.key === '3') || // Mac screenshot
                (e.metaKey && e.shiftKey && e.key === '4') || // Mac screenshot
                (e.metaKey && e.shiftKey && e.key === '5') // Mac screenshot
            ) {
                e.preventDefault();
                e.stopPropagation();

                // Show warning
                if (!warningShown) {
                    const warning = document.createElement('div');
                    warning.className = 'security-warning show';
                    warning.textContent = '⚠️ Screenshot attempts are monitored and logged for security purposes';
                    document.body.appendChild(warning);

                    setTimeout(() => {
                        warning.remove();
                    }, 3000);

                    warningShown = true;
                    setTimeout(() => { warningShown = false; }, 5000);
                }

                return false;
            }
        };

        const handleContextMenu = (e: MouseEvent) => {
            e.preventDefault();
            return false;
        };

        const handleDragStart = (e: DragEvent) => {
            e.preventDefault();
            return false;
        };

        // Dev tools detection
        const detectDevTools = () => {
            const threshold = 160;
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;

            if (widthThreshold || heightThreshold) {
                if (!devToolsOpen) {
                    devToolsOpen = true;
                    console.clear();
                    console.log('%cContent Protection Active', 'color: red; font-size: 20px; font-weight: bold;');
                    console.log('%cThis content is protected. Unauthorized access attempts are logged.', 'color: red; font-size: 14px;');

                    // Blur content when dev tools are detected
                    const pdfElements = document.querySelectorAll('.pdf-protected');
                    pdfElements.forEach(el => {
                        (el as HTMLElement).style.filter = 'blur(5px)';
                        (el as HTMLElement).style.opacity = '0.3';
                    });
                }
            } else {
                if (devToolsOpen) {
                    devToolsOpen = false;
                    // Restore content
                    const pdfElements = document.querySelectorAll('.pdf-protected');
                    pdfElements.forEach(el => {
                        (el as HTMLElement).style.filter = '';
                        (el as HTMLElement).style.opacity = '';
                    });
                }
            }
        };

        // Check for dev tools every 500ms
        const devToolsInterval = setInterval(detectDevTools, 500);

        // Disable print
        const handleBeforePrint = (e: Event) => {
            e.preventDefault();
            alert('Printing is disabled for protected content');
            return false;
        };

        document.addEventListener('keydown', handleKeyDown);
        document.addEventListener('contextmenu', handleContextMenu);
        document.addEventListener('dragstart', handleDragStart);
        window.addEventListener('beforeprint', handleBeforePrint);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.removeEventListener('contextmenu', handleContextMenu);
            document.removeEventListener('dragstart', handleDragStart);
            window.removeEventListener('beforeprint', handleBeforePrint);
            clearInterval(devToolsInterval);
        };
    }, []);

    const toggleFullscreen = useCallback(() => {
        setIsFullscreen(prev => !prev);
    }, []);

    const onDocumentLoadSuccess = useCallback(({ numPages }: { numPages: number }) => {
        console.log('PDF loaded successfully:', numPages, 'pages');
        setNumPages(numPages);
        setLoading(false);
        setError(null);
        setRetryCount(0);
        documentRef.current = true;
    }, []);

    const onDocumentLoadError = useCallback((error: any) => {
        console.error('PDF load error:', error);
        const pdfError: PdfError = {
            name: error?.name || 'PDFLoadError',
            message: error?.message || 'Failed to load PDF document',
            details: {
                url: fileUrl,
                retryCount,
                workerSrc,
                userAgent: navigator.userAgent
            }
        };

        setError(pdfError);
        setLoading(false);

        // Auto-retry up to 2 times before falling back
        if (retryCount < 2) {
            console.log(`Retrying PDF load (attempt ${retryCount + 1}/2)...`);
            setTimeout(() => {
                setRetryCount(prev => prev + 1);
                setLoading(true);
                setError(null);
            }, 1000);
        } else {
            console.log('Max retries reached, falling back to iframe viewer');
            setUseFallback(true);
        }
    }, [fileUrl, retryCount]);

    const retryLoad = useCallback(() => {
        setError(null);
        setUseFallback(false);
        setRetryCount(0);
        setLoading(true);
        documentRef.current = null;
    }, []);

    const goToPrevPage = useCallback(() => {
        if (currentPage > 1) {
            const newPage = currentPage - 1;
            setCurrentPage(newPage);
            onPageChange?.(newPage);
            updateProgress(newPage, numPages);
        }
    }, [currentPage, onPageChange, updateProgress, numPages]);

    const goToNextPage = useCallback(() => {
        if (currentPage < numPages) {
            const newPage = currentPage + 1;
            setCurrentPage(newPage);
            onPageChange?.(newPage);
            updateProgress(newPage, numPages);
        }
    }, [currentPage, numPages, onPageChange, updateProgress]);

    const zoomIn = useCallback(() => {
        setScale(prev => Math.min(prev + 0.2, 3.0));
    }, []);

    const zoomOut = useCallback(() => {
        setScale(prev => Math.max(prev - 0.2, 0.5));
    }, []);

    const rotate = useCallback(() => {
        setRotation(prev => (prev + 90) % 360);
    }, []);

    const toggleProtectionOverlay = useCallback(() => {
        setShowProtectionOverlay(prev => !prev);
    }, []);

    return (
        <div
            className={`bg-gray-900 rounded-lg overflow-hidden pdf-protected no-print flex flex-col h-full ${isFullscreen ? 'fixed inset-0 z-50' : ''}`}
        >
            {/* Controls */}
            <div className="bg-gray-800 p-4 flex items-center justify-between border-b border-gray-700 pdf-controls flex-shrink-0">
                <div className="flex items-center gap-4">
                    <h3 className="text-white font-medium">{fileName}</h3>
                    {numPages > 0 && (
                        <span className="text-gray-400 text-sm">
                            {viewMode === 'single'
                                ? `Page ${currentPage} of ${numPages}`
                                : `${numPages} pages`
                            }
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {/* Navigation Controls - Only show in single page mode */}
                    {viewMode === 'single' && (
                        <>
                            <button
                                onClick={goToPrevPage}
                                disabled={currentPage <= 1}
                                className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Previous Page"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>

                            <button
                                onClick={goToNextPage}
                                disabled={currentPage >= numPages}
                                className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Next Page"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </>
                    )}

                    {/* Zoom Controls */}
                    <button
                        onClick={zoomOut}
                        disabled={scale <= 0.5}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Zoom Out"
                    >
                        <ZoomOut className="h-4 w-4" />
                    </button>

                    <span className="text-white text-sm px-2">
                        {Math.round(scale * 100)}%
                    </span>

                    <button
                        onClick={zoomIn}
                        disabled={scale >= 3.0}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Zoom In"
                    >
                        <ZoomIn className="h-4 w-4" />
                    </button>

                    {/* Rotate */}
                    <button
                        onClick={rotate}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title="Rotate"
                    >
                        <RotateCw className="h-4 w-4" />
                    </button>

                    {/* View Mode Toggle */}
                    <button
                        onClick={() => setViewMode(viewMode === 'single' ? 'continuous' : 'single')}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title={viewMode === 'single' ? 'Continuous View' : 'Single Page View'}
                    >
                        {viewMode === 'single' ? (
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        ) : (
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        )}
                    </button>



                    {/* Protection Toggle */}
                    <button
                        onClick={toggleProtectionOverlay}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title={showProtectionOverlay ? "Hide Protection" : "Show Protection"}
                    >
                        {showProtectionOverlay ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>

                    {/* Retry Button - Show when there's an error */}
                    {(error || useFallback) && (
                        <button
                            onClick={retryLoad}
                            className="p-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                            title="Retry Loading PDF"
                        >
                            <RefreshCw className="h-4 w-4" />
                        </button>
                    )}

                    {/* Download Button - Only show if user has access */}
                    {userHasDownloadAccess && downloadUrl && (
                        <a
                            href={downloadUrl}
                            download={fileName}
                            className="p-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                            title="Download PDF"
                        >
                            <Download className="h-4 w-4" />
                        </a>
                    )}

                    {/* Fallback Toggle */}
                    <button
                        onClick={() => setUseFallback(!useFallback)}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title={useFallback ? "Use Advanced Viewer" : "Use Simple Viewer"}
                    >
                        {useFallback ? '🔧' : '⚡'}
                    </button>

                    {/* Fullscreen Toggle */}
                    <button
                        onClick={toggleFullscreen}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title={isFullscreen ? "Exit Fullscreen" : "Enter Fullscreen"}
                    >
                        {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
                    </button>
                </div>
            </div>

            {/* PDF Content */}
            <div
                ref={containerRef}
                className={`relative flex-1 min-h-0 bg-gray-800 pdf-protected ${viewMode === 'continuous' ? 'overflow-hidden' : 'overflow-auto flex items-center justify-center'}`}
            >
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-gray-800 z-10">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto mb-4"></div>
                            <p className="text-gray-300">Loading PDF...</p>
                            {retryCount > 0 && (
                                <p className="text-gray-400 text-sm mt-2">Retry attempt {retryCount}/2</p>
                            )}
                        </div>
                    </div>
                )}

                {/* Error Display */}
                {error && !loading && !useFallback && (
                    <div className="absolute inset-0 flex items-center justify-center bg-gray-800 z-10">
                        <div className="text-center max-w-md p-6">
                            <div className="text-red-400 mb-4">
                                <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-white mb-2">Failed to Load PDF</h3>
                            <p className="text-gray-300 mb-4">{error.message}</p>
                            <div className="space-y-2">
                                <button
                                    onClick={retryLoad}
                                    className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    <RefreshCw className="w-4 h-4 inline mr-2" />
                                    Try Again
                                </button>
                                <button
                                    onClick={() => setUseFallback(true)}
                                    className="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                                >
                                    Use Fallback Viewer
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                <div className="relative">
                    {useFallback ? (
                        // Fallback iframe viewer
                        <div className="w-full h-full">
                            <iframe
                                src={fileUrl}
                                className="w-full h-full border-0 pdf-protected"
                                title={fileName}
                                style={{
                                    userSelect: 'none',
                                    WebkitUserSelect: 'none',
                                    pointerEvents: 'auto'
                                }}
                            />
                            <div className="absolute top-4 right-4 bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                Fallback Mode
                            </div>
                        </div>
                    ) : (
                        <Document
                            file={{
                                url: fileUrl,
                                httpHeaders: {
                                    'Accept': 'application/pdf',
                                    'Cache-Control': 'no-cache',
                                },
                                withCredentials: true,
                                // Add request options for better compatibility
                                ...(retryCount > 0 && {
                                    // On retry, try without credentials
                                    withCredentials: false,
                                }),
                            }}
                            onLoadSuccess={onDocumentLoadSuccess}
                            onLoadError={onDocumentLoadError}
                            loading={
                                <div className="text-center text-gray-300 p-8">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto mb-4"></div>
                                    <p>Loading PDF document...</p>
                                    <p className="text-xs text-gray-500 mt-2">
                                        {retryCount > 0 ? `Retry attempt ${retryCount}/2` : 'Initializing viewer...'}
                                    </p>
                                </div>
                            }
                            error={null} // We handle errors in onLoadError
                            options={{
                                cMapUrl: `https://unpkg.com/pdfjs-dist@${pdfjs.version}/cmaps/`,
                                cMapPacked: true,
                                standardFontDataUrl: `https://unpkg.com/pdfjs-dist@${pdfjs.version}/standard_fonts/`,
                                verbosity: 0,
                                // Disable worker on retry if it's causing issues
                                ...(retryCount > 0 && {
                                    disableWorker: true,
                                }),
                                // Add more compatibility options
                                isEvalSupported: false,
                                disableAutoFetch: false,
                                disableStream: retryCount > 0, // Disable streaming on retry
                            }}
                            key={`${fileUrl}-${retryCount}`} // Force re-render on retry
                        >
                            {viewMode === 'single' ? (
                                // Single page view
                                <Page
                                    pageNumber={currentPage}
                                    scale={scale}
                                    rotate={rotation}
                                    loading={
                                        <div className="text-center text-gray-300 p-8">
                                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-500 mx-auto mb-2"></div>
                                            <p className="text-sm">Loading page {currentPage}...</p>
                                        </div>
                                    }
                                    error={
                                        <div className="text-center text-red-400 p-8">
                                            <p>Failed to load page {currentPage}</p>
                                            <button
                                                onClick={() => setPageLoading(true)}
                                                className="mt-2 px-3 py-1 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700"
                                            >
                                                Retry Page
                                            </button>
                                        </div>
                                    }
                                    className="shadow-lg pdf-protected max-w-full"
                                    renderTextLayer={false}
                                    renderAnnotationLayer={false}
                                    onLoadSuccess={() => setPageLoading(false)}
                                    onLoadError={() => setPageLoading(false)}
                                    canvasProps={{
                                        className: 'pdf-protected max-w-full h-auto',
                                        style: {
                                            userSelect: 'none',
                                            WebkitUserSelect: 'none',
                                            pointerEvents: 'none',
                                            maxWidth: '100%',
                                            height: 'auto'
                                        }
                                    }}
                                />
                            ) : (
                                // Continuous scrolling view - render all pages in scrollable container
                                <div className="pdf-continuous-container h-full w-full">
                                    <div className="space-y-6 py-4 w-full">
                                        {Array.from(new Array(numPages), (el, index) => (
                                            <div key={`page_${index + 1}`} className="flex justify-center w-full">
                                                <Page
                                                    pageNumber={index + 1}
                                                    scale={scale}
                                                    rotate={rotation}
                                                    loading={
                                                        <div className="text-center text-gray-300 p-8">
                                                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-500 mx-auto mb-2"></div>
                                                            <p className="text-sm">Loading page {index + 1}...</p>
                                                        </div>
                                                    }
                                                    error={
                                                        <div className="text-center text-red-400 p-8">
                                                            <p>Failed to load page {index + 1}</p>
                                                        </div>
                                                    }
                                                    className="shadow-lg pdf-protected max-w-full"
                                                    renderTextLayer={false}
                                                    renderAnnotationLayer={false}
                                                    canvasProps={{
                                                        className: 'pdf-protected max-w-full h-auto',
                                                        style: {
                                                            userSelect: 'none',
                                                            WebkitUserSelect: 'none',
                                                            pointerEvents: 'none',
                                                            maxWidth: '100%',
                                                            height: 'auto'
                                                        }
                                                    }}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </Document>
                    )}

                    {/* Protection Overlay */}
                    {showProtectionOverlay && (
                        <div className="protection-overlay">
                            <div className="watermark watermark-top-right">
                                PROTECTED CONTENT
                            </div>
                            <div className="watermark watermark-bottom-left">
                                NO SCREENSHOTS
                            </div>
                            <div className="watermark watermark-center">
                                {userHasDownloadAccess ? 'LICENSED USER' : 'PREVIEW ONLY'}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default PdfViewer;
