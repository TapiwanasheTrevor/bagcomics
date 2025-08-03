import React, { useState, useEffect, useRef } from 'react';
import { Download, Maximize2, Minimize2, X, RefreshCw, AlertTriangle } from 'lucide-react';

interface SimplePdfViewerProps {
    fileUrl: string;
    fileName?: string;
    downloadUrl?: string;
    userHasDownloadAccess?: boolean;
}

const SimplePdfViewer: React.FC<SimplePdfViewerProps> = ({
    fileUrl,
    fileName = 'document.pdf',
    downloadUrl,
    userHasDownloadAccess = false
}) => {
    const [isFullscreen, setIsFullscreen] = useState<boolean>(false);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [viewerMode, setViewerMode] = useState<'iframe' | 'object' | 'embed'>('iframe');
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const objectRef = useRef<HTMLObjectElement>(null);

    useEffect(() => {
        // Test PDF accessibility
        testPdfAccess();
    }, [fileUrl]);

    const testPdfAccess = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(fileUrl, {
                method: 'HEAD',
                credentials: 'include'
            });

            if (response.ok) {
                const contentType = response.headers.get('Content-Type');
                console.log('PDF test successful:', {
                    status: response.status,
                    contentType,
                    headers: Object.fromEntries(response.headers.entries())
                });
                setLoading(false);
            } else {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
        } catch (err: any) {
            console.error('PDF access test failed:', err);
            setError(err.message);
            setLoading(false);
        }
    };

    const handleIframeLoad = () => {
        setLoading(false);
        console.log('Iframe loaded successfully');
    };

    const handleIframeError = () => {
        console.error('Iframe failed to load');
        setError('Failed to load PDF in iframe');
        setLoading(false);
    };

    const toggleFullscreen = () => {
        setIsFullscreen(prev => !prev);
    };

    const retryLoad = () => {
        setError(null);
        setLoading(true);
        
        // Cycle through different viewer modes
        if (viewerMode === 'iframe') {
            setViewerMode('object');
        } else if (viewerMode === 'object') {
            setViewerMode('embed');
        } else {
            setViewerMode('iframe');
        }

        // Force reload
        setTimeout(() => {
            testPdfAccess();
        }, 100);
    };

    const renderPdfViewer = () => {
        const commonProps = {
            width: '100%',
            height: '100%',
            style: { border: 'none' }
        };

        switch (viewerMode) {
            case 'iframe':
                return (
                    <iframe
                        ref={iframeRef}
                        src={fileUrl}
                        title={fileName}
                        onLoad={handleIframeLoad}
                        onError={handleIframeError}
                        {...commonProps}
                    />
                );

            case 'object':
                return (
                    <object
                        ref={objectRef}
                        data={fileUrl}
                        type="application/pdf"
                        {...commonProps}
                    >
                        <p>Your browser doesn't support PDF viewing. 
                           <a href={fileUrl} target="_blank" rel="noopener noreferrer">
                               Click here to download the PDF
                           </a>
                        </p>
                    </object>
                );

            case 'embed':
                return (
                    <embed
                        src={fileUrl}
                        type="application/pdf"
                        {...commonProps}
                    />
                );

            default:
                return null;
        }
    };

    if (error) {
        return (
            <div className="flex items-center justify-center h-96 bg-gray-800">
                <div className="text-center max-w-md p-6">
                    <AlertTriangle className="w-16 h-16 mx-auto mb-4 text-red-400" />
                    <h3 className="text-lg font-semibold text-white mb-2">PDF Loading Failed</h3>
                    <p className="text-gray-300 mb-4">{error}</p>
                    <div className="space-y-2">
                        <button
                            onClick={retryLoad}
                            className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            <RefreshCw className="w-4 h-4 inline mr-2" />
                            Try Different Method ({viewerMode})
                        </button>
                        <a
                            href={fileUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-center"
                        >
                            Open in New Tab
                        </a>
                    </div>
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
                        Simple PDF Viewer ({viewerMode})
                    </span>
                    {loading && (
                        <div className="flex items-center space-x-2">
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-500"></div>
                            <span className="text-sm text-gray-400">Loading...</span>
                        </div>
                    )}
                </div>

                <div className="flex items-center space-x-2">
                    <button
                        onClick={retryLoad}
                        className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                        title="Try Different Method"
                    >
                        <RefreshCw className="h-4 w-4" />
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
            <div className={`relative ${isFullscreen ? 'h-[calc(100vh-80px)]' : 'h-[80vh]'} bg-gray-800`}>
                {renderPdfViewer()}
                
                {/* Loading overlay */}
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto mb-4"></div>
                            <p className="text-gray-300">Loading PDF...</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Debug info */}
            <div className="p-2 bg-gray-700 text-xs text-gray-400 border-t border-gray-600">
                <div className="flex justify-between items-center">
                    <span>URL: {fileUrl}</span>
                    <span>Mode: {viewerMode}</span>
                </div>
            </div>
        </div>
    );
};

export default SimplePdfViewer;
