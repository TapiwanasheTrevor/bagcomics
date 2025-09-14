import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { ChevronLeft, ChevronRight, ZoomIn, ZoomOut, RotateCw, Maximize2, X } from 'lucide-react';
import { Document, Page, pdfjs } from 'react-pdf';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Set up PDF.js worker
const setupPdfWorker = () => {
    try {
        // Use CDN worker for better compatibility
        pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.js`;
        pdfjs.GlobalWorkerOptions.verbosity = pdfjs.VerbosityLevel.ERRORS;
        console.log('PDF.js worker configured:', pdfjs.GlobalWorkerOptions.workerSrc);
    } catch (error) {
        console.warn('PDF.js worker setup failed:', error);
        // Fallback to local worker
        pdfjs.GlobalWorkerOptions.workerSrc = '/js/pdfjs/pdf.worker.min.js';
    }
};

setupPdfWorker();

interface SimplePdfReaderProps {
  fileUrl: string;
  fileName?: string;
  onPageChange?: (page: number) => void;
  initialPage?: number;
  comicSlug?: string;
  onClose?: () => void;
}

const SimplePdfReader: React.FC<SimplePdfReaderProps> = ({
  fileUrl,
  fileName = 'document.pdf',
  onPageChange,
  initialPage = 1,
  comicSlug,
  onClose
}) => {
  console.log('SimplePdfReader initialized with:', { fileUrl, fileName });
  console.log('PDF.js version:', pdfjs.version);
  console.log('PDF.js worker src:', pdfjs.GlobalWorkerOptions.workerSrc);
  
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [totalPages, setTotalPages] = useState(0);
  const [zoom, setZoom] = useState(120);
  const [rotation, setRotation] = useState(0);
  const [pagesRead, setPagesRead] = useState(new Set());
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Add loading timeout
  useEffect(() => {
    console.log('Loading state changed:', loading);
    
    const timeout = setTimeout(() => {
      if (loading) {
        console.warn('PDF loading timeout after 5 seconds');
        console.warn('Checking network access to PDF...');
        
        // Try to fetch the URL directly to see if it's accessible
        fetch(fileUrl, { method: 'HEAD' })
          .then(response => {
            console.log('PDF HEAD request status:', response.status);
            if (response.ok) {
              setError(`PDF is accessible but PDF.js failed to load it. This might be a CORS or PDF.js compatibility issue.`);
            } else {
              setError(`PDF file not accessible. Status: ${response.status}`);
            }
          })
          .catch(error => {
            console.error('Network error accessing PDF:', error);
            setError(`Network error accessing PDF: ${error.message}`);
          })
          .finally(() => {
            setLoading(false);
          });
      }
    }, 5000); // 5 second timeout

    return () => clearTimeout(timeout);
  }, [loading, fileUrl]);

  useEffect(() => {
    // Mark current page as read after 2 seconds
    const timer = setTimeout(() => {
      setPagesRead(prev => new Set([...prev, currentPage]));
    }, 2000);
    
    return () => clearTimeout(timer);
  }, [currentPage]);

  useEffect(() => {
    // Call onPageChange when page changes
    if (onPageChange) {
      onPageChange(currentPage);
    }
  }, [currentPage, onPageChange]);


  const handleZoomIn = () => {
    if (zoom < 200) {
      setZoom(zoom + 25);
    }
  };

  const handleZoomOut = () => {
    if (zoom > 50) {
      setZoom(zoom - 25);
    }
  };

  const handleRotate = () => {
    setRotation((rotation + 90) % 360);
  };

  const handleFullscreen = () => {
    if (!isFullscreen) {
      containerRef.current?.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
    setIsFullscreen(!isFullscreen);
  };

  const handlePageInput = (e) => {
    const page = parseInt(e.target.value);
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page);
    }
  };

  const progressPercentage = totalPages > 0 ? (pagesRead.size / totalPages) * 100 : 0;

  const handlePrevPage = useCallback(() => {
    if (currentPage > 1) {
      const newPage = currentPage - 1;
      setCurrentPage(newPage);
      onPageChange?.(newPage);
    }
  }, [currentPage, onPageChange]);

  const handleNextPage = useCallback(() => {
    if (currentPage < totalPages) {
      const newPage = currentPage + 1;
      setCurrentPage(newPage);
      onPageChange?.(newPage);
    }
  }, [currentPage, totalPages, onPageChange]);

  const onDocumentLoadSuccess = ({ numPages }: { numPages: number }) => {
    console.log('PDF loaded successfully!', { numPages, fileUrl });
    setTotalPages(numPages);
    setLoading(false);
    setError(null);
  };

  const onDocumentLoadError = (error: Error) => {
    console.error('PDF load error:', error);
    console.error('Failed to load URL:', fileUrl);
    setError(`Failed to load PDF from: ${fileUrl}. Error: ${error.message}`);
    setLoading(false);
  };

  return (
    <div className="fixed inset-0 z-50 flex flex-col h-screen bg-gray-900">
      {/* Header Controls */}
      <div className="bg-gray-800 border-b border-gray-700 px-4 py-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            {/* Close Button */}
            <button
              onClick={onClose}
              className="p-2 rounded bg-red-500 text-white hover:bg-red-600"
              title="Close Reader"
            >
              <X className="w-5 h-5" />
            </button>

            {/* File Name */}
            <h3 className="font-medium truncate max-w-xs text-white">{fileName}</h3>

            {/* Navigation Controls */}
            <div className="flex items-center space-x-2">
              <button
                onClick={handlePrevPage}
                disabled={currentPage === 1}
                className="p-2 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed text-white"
              >
                <ChevronLeft className="w-5 h-5" />
              </button>
              
              <div className="flex items-center space-x-2">
                <input
                  type="number"
                  value={currentPage}
                  onChange={handlePageInput}
                  className="w-12 px-2 py-1 text-center border border-gray-600 rounded bg-gray-700 text-white"
                  min="1"
                  max={totalPages}
                />
                <span className="text-sm text-gray-300">/ {totalPages}</span>
              </div>
              
              <button
                onClick={handleNextPage}
                disabled={currentPage === totalPages}
                className="p-2 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed text-white"
              >
                <ChevronRight className="w-5 h-5" />
              </button>
            </div>

            {/* Zoom Controls */}
            <div className="flex items-center space-x-2 border-l border-gray-600 pl-4">
              <button
                onClick={handleZoomOut}
                disabled={zoom <= 50}
                className="p-2 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed text-white"
              >
                <ZoomOut className="w-5 h-5" />
              </button>
              
              <span className="text-sm font-medium w-12 text-center text-white">{zoom}%</span>
              
              <button
                onClick={handleZoomIn}
                disabled={zoom >= 200}
                className="p-2 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed text-white"
              >
                <ZoomIn className="w-5 h-5" />
              </button>
            </div>

            {/* Other Controls */}
            <div className="flex items-center space-x-2 border-l border-gray-600 pl-4">
              <button
                onClick={handleRotate}
                className="p-2 rounded hover:bg-gray-700 text-white"
                title="Rotate"
              >
                <RotateCw className="w-5 h-5" />
              </button>
              
              <button
                onClick={handleFullscreen}
                className="p-2 rounded hover:bg-gray-700 text-white"
                title="Fullscreen"
              >
                <Maximize2 className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Progress Indicator */}
          <div className="flex items-center space-x-3">
            <div className="text-sm text-gray-300">
              Progress: {pagesRead.size} / {totalPages} pages
            </div>
            <div className="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-blue-500 transition-all duration-300"
                style={{ width: `${progressPercentage}%` }}
              />
            </div>
            <span className="text-sm font-medium text-white">{Math.round(progressPercentage)}%</span>
          </div>
        </div>
      </div>

      {/* PDF Viewer Area */}
      <div 
        ref={containerRef}
        className="flex-1 overflow-auto bg-gray-900 p-8"
      >
        <div 
          className="mx-auto bg-white shadow-lg"
          style={{
            transform: `scale(${zoom / 100}) rotate(${rotation}deg)`,
            transformOrigin: 'center top',
            transition: 'transform 0.3s ease',
            width: '800px',
            minHeight: '1000px'
          }}
        >
          {/* PDF Content - Secure react-pdf viewer */}
          {loading && (
            <div className="flex items-center justify-center h-full">
              <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                <p className="text-gray-300">Loading PDF...</p>
              </div>
            </div>
          )}

          {error && !loading && (
            <div className="flex items-center justify-center h-full">
              <div className="text-center max-w-md p-6">
                <div className="text-red-400 mb-4">
                  <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                </div>
                <h3 className="text-lg font-semibold mb-2 text-white">Failed to Load PDF</h3>
                <p className="text-gray-300 mb-4">{error}</p>
                <button
                  onClick={() => window.location.reload()}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  Retry
                </button>
              </div>
            </div>
          )}

          {!loading && !error && (
            <div className="mx-auto bg-white shadow-lg flex items-center justify-center">
              <Document
                file={fileUrl}
                onLoadSuccess={onDocumentLoadSuccess}
                onLoadError={onDocumentLoadError}
                loading={
                  <div className="text-center text-gray-300 p-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-4"></div>
                    <p className="text-sm">Loading PDF document...</p>
                    <p className="text-xs mt-2">URL: {fileUrl}</p>
                  </div>
                }
                error={null}
              >
                <Page
                  pageNumber={currentPage}
                  scale={zoom / 100}
                  rotate={rotation}
                  className="shadow-lg max-w-full"
                  style={{
                    // Disable right-click and text selection for security
                    userSelect: 'none',
                    WebkitUserSelect: 'none',
                    MozUserSelect: 'none',
                    pointerEvents: 'none',
                  }}
                  onContextMenu={(e) => e.preventDefault()}
                  loading={
                    <div className="text-center text-gray-300 p-8">
                      <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
                      <p className="text-sm">Loading page {currentPage}...</p>
                    </div>
                  }
                  error={
                    <div className="text-center text-red-400 p-8 bg-red-50 rounded-lg border border-red-200">
                      <h4 className="text-lg font-semibold mb-2">Page Load Error</h4>
                      <p className="text-sm text-red-600 mb-4">Failed to load page {currentPage}</p>
                      <button
                        onClick={() => window.location.reload()}
                        className="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors"
                      >
                        Retry
                      </button>
                    </div>
                  }
                />
              </Document>
            </div>
          )}
        </div>
      </div>

      {/* Status Bar */}
      <div className="bg-gray-800 border-t border-gray-700 px-4 py-2">
        <div className="flex items-center justify-between text-sm text-gray-300">
          <div>
            {pagesRead.has(currentPage) ? (
              <span className="text-green-600">✓ Page read</span>
            ) : (
              <span>Reading page...</span>
            )}
          </div>
          <div>
            Zoom: {zoom}% | Rotation: {rotation}° | Page {currentPage} of {totalPages}
          </div>
        </div>
      </div>
    </div>
  );
};

export default SimplePdfReader;