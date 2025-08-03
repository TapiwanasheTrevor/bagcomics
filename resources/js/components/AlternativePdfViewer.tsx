import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Download, Maximize2, Minimize2, ChevronLeft, ChevronRight, RotateCw, ZoomIn, ZoomOut, RefreshCw, X } from 'lucide-react';
import * as pdfjsLib from 'pdfjs-dist';

// Configure PDF.js worker with local file to avoid CORS issues
pdfjsLib.GlobalWorkerOptions.workerSrc = '/js/pdfjs/pdf.worker.min.js';

interface AlternativePdfViewerProps {
    fileUrl: string;
    fileName?: string;
    downloadUrl?: string;
    onPageChange?: (page: number) => void;
    initialPage?: number;
    userHasDownloadAccess?: boolean;
    comicSlug?: string;
}

const AlternativePdfViewer: React.FC<AlternativePdfViewerProps> = ({
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
    const [error, setError] = useState<string | null>(null);
    const [numPages, setNumPages] = useState<number>(0);
    const [currentPage, setCurrentPage] = useState<number>(initialPage);
    const [scale, setScale] = useState<number>(1.2);
    const [rotation, setRotation] = useState<number>(0);
    const [pdfDoc, setPdfDoc] = useState<any>(null);
    
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    // Load PDF document
    const loadPDF = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            
            const loadingTask = pdfjsLib.getDocument({
                url: fileUrl,
                httpHeaders: {
                    'Accept': 'application/pdf',
                },
                withCredentials: true,
                verbosity: 0
            });
            
            const pdf = await loadingTask.promise;
            setPdfDoc(pdf);
            setNumPages(pdf.numPages);
            setLoading(false);
            
            // Render first page
            await renderPage(pdf, currentPage);
        } catch (err: any) {
            console.error('PDF loading error:', err);
            setError(err.message || 'Failed to load PDF');
            setLoading(false);
        }
    }, [fileUrl, currentPage]);

    // Render specific page
    const renderPage = useCallback(async (pdf: any, pageNumber: number) => {
        if (!pdf || !canvasRef.current) return;
        
        try {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale, rotation });
            
            const canvas = canvasRef.current;
            const context = canvas.getContext('2d');
            
            if (!context) return;
            
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };
            
            await page.render(renderContext).promise;
        } catch (err) {
            console.error('Page rendering error:', err);
        }
    }, [scale, rotation]);

    // Initialize PDF
    useEffect(() => {
        loadPDF();
    }, [loadPDF]);

    // Re-render page when scale or rotation changes
    useEffect(() => {
        if (pdfDoc && currentPage) {
            renderPage(pdfDoc, currentPage);
        }
    }, [pdfDoc, currentPage, scale, rotation, renderPage]);

    // Navigation functions
    const goToPrevPage = useCallback(() => {
        if (currentPage > 1) {
            const newPage = currentPage - 1;
            setCurrentPage(newPage);
            onPageChange?.(newPage);
        }
    }, [currentPage, onPageChange]);

    const goToNextPage = useCallback(() => {
        if (currentPage < numPages) {
            const newPage = currentPage + 1;
            setCurrentPage(newPage);
            onPageChange?.(newPage);
        }
    }, [currentPage, numPages, onPageChange]);

    // Zoom functions
    const zoomIn = useCallback(() => {
        setScale(prev => Math.min(prev + 0.25, 3.0));
    }, []);

    const zoomOut = useCallback(() => {
        setScale(prev => Math.max(prev - 0.25, 0.5));
    }, []);

    // Rotation function
    const rotate = useCallback(() => {
        setRotation(prev => (prev + 90) % 360);
    }, []);

    // Fullscreen toggle
    const toggleFullscreen = useCallback(() => {
        setIsFullscreen(prev => !prev);
    }, []);

    // Retry loading
    const retryLoad = useCallback(() => {
        loadPDF();
    }, [loadPDF]);

    if (loading) {
        return (
            <div className="flex items-center justify-center h-96 bg-gray-800">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto mb-4"></div>
                    <p className="text-gray-300">Loading PDF...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center h-96 bg-gray-800">
                <div className="text-center max-w-md p-6">
                    <div className="text-red-400 mb-4">
                        <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-white mb-2">Failed to Load PDF</h3>
                    <p className="text-gray-300 mb-4">{error}</p>
                    <button
                        onClick={retryLoad}
                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <RefreshCw className="w-4 h-4 inline mr-2" />
                        Try Again
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className={`bg-gray-900 text-white ${isFullscreen ? 'fixed inset-0 z-50' : 'rounded-lg overflow-hidden'}`}>
            {/* Controls */}
            <div className="flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700">
                <div className="flex items-center space-x-4">
                    <span className="text-sm text-gray-300">
                        Page {currentPage} of {numPages}
                    </span>
                </div>

                <div className="flex items-center space-x-2">
                    {/* Navigation */}
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

                    {/* Download Button */}
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
                className={`relative ${isFullscreen ? 'h-[calc(100vh-80px)]' : 'h-[80vh]'} bg-gray-800 overflow-auto flex items-center justify-center`}
            >
                <canvas
                    ref={canvasRef}
                    className="max-w-full max-h-full shadow-lg"
                    style={{
                        userSelect: 'none',
                        WebkitUserSelect: 'none',
                        pointerEvents: 'none'
                    }}
                />
            </div>
        </div>
    );
};

export default AlternativePdfViewer;
