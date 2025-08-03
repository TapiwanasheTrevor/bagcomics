import React, { useState, useEffect, useCallback } from 'react';
import { RefreshCw, AlertTriangle } from 'lucide-react';
import PdfViewer from './PdfViewer';
import AlternativePdfViewer from './AlternativePdfViewer';
import SimplePdfViewer from './SimplePdfViewer';

interface SmartPdfViewerProps {
    fileUrl: string;
    fileName?: string;
    downloadUrl?: string;
    onPageChange?: (page: number) => void;
    initialPage?: number;
    userHasDownloadAccess?: boolean;
    comicSlug?: string;
}

type ViewerMode = 'react-pdf' | 'canvas' | 'simple' | 'error';

const SmartPdfViewer: React.FC<SmartPdfViewerProps> = (props) => {
    const [viewerMode, setViewerMode] = useState<ViewerMode>('react-pdf');
    const [errorCount, setErrorCount] = useState<number>(0);
    const [lastError, setLastError] = useState<string | null>(null);

    // Handle viewer failures and fallback logic
    const handleViewerError = useCallback((error: string, currentMode: ViewerMode) => {
        console.error(`${currentMode} viewer failed:`, error);
        setLastError(error);
        setErrorCount(prev => prev + 1);

        // Fallback chain: react-pdf -> canvas -> simple -> error
        switch (currentMode) {
            case 'react-pdf':
                console.log('Falling back to canvas-based viewer...');
                setViewerMode('canvas');
                break;
            case 'canvas':
                console.log('Falling back to simple viewer...');
                setViewerMode('simple');
                break;
            case 'simple':
                console.log('All viewers failed, showing error state');
                setViewerMode('error');
                break;
            default:
                setViewerMode('error');
        }
    }, []);

    // Reset to primary viewer
    const resetViewer = useCallback(() => {
        setViewerMode('react-pdf');
        setErrorCount(0);
        setLastError(null);
    }, []);

    // Enhanced props with error handling
    const enhancedProps = {
        ...props,
        onError: (error: string) => handleViewerError(error, viewerMode)
    };

    // Render current viewer based on mode
    const renderViewer = () => {
        switch (viewerMode) {
            case 'react-pdf':
                return (
                    <div className="relative">
                        <PdfViewer {...enhancedProps} />
                        {errorCount > 0 && (
                            <div className="absolute top-4 right-4 bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                React-PDF Mode
                            </div>
                        )}
                    </div>
                );

            case 'canvas':
                return (
                    <div className="relative">
                        <AlternativePdfViewer {...enhancedProps} />
                        <div className="absolute top-4 right-4 bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            Canvas Mode
                        </div>
                    </div>
                );

            case 'simple':
                return (
                    <div className="relative">
                        <SimplePdfViewer {...enhancedProps} />
                        <div className="absolute top-4 right-4 bg-orange-600 text-white px-3 py-1 rounded text-sm">
                            Simple Mode
                        </div>
                    </div>
                );

            case 'error':
                return (
                    <div className="bg-gray-900 text-white rounded-lg overflow-hidden">
                        <div className="flex items-center justify-center h-96">
                            <div className="text-center max-w-md p-6">
                                <div className="text-red-400 mb-4">
                                    <AlertTriangle className="w-16 h-16 mx-auto mb-4" />
                                </div>
                                <h3 className="text-lg font-semibold text-white mb-2">
                                    Unable to Load PDF
                                </h3>
                                <p className="text-gray-300 mb-4">
                                    We tried multiple viewing methods but couldn't display this PDF.
                                </p>
                                {lastError && (
                                    <p className="text-sm text-gray-400 mb-4">
                                        Error: {lastError}
                                    </p>
                                )}
                                <div className="space-y-2">
                                    <button
                                        onClick={resetViewer}
                                        className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                                    >
                                        <RefreshCw className="w-4 h-4 inline mr-2" />
                                        Try Again
                                    </button>
                                    {props.userHasDownloadAccess && props.downloadUrl && (
                                        <a
                                            href={props.downloadUrl}
                                            download={props.fileName}
                                            className="block w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-center"
                                        >
                                            Download PDF Instead
                                        </a>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <div className="relative">
            {renderViewer()}
            
            {/* Debug info in development */}
            {process.env.NODE_ENV === 'development' && (
                <div className="absolute bottom-4 left-4 bg-black/80 text-white text-xs p-2 rounded">
                    Mode: {viewerMode} | Errors: {errorCount}
                </div>
            )}
        </div>
    );
};

export default SmartPdfViewer;
