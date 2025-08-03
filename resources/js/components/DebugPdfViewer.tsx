import React, { useState, useEffect } from 'react';
import { AlertCircle, CheckCircle, XCircle, Loader } from 'lucide-react';

interface DebugPdfViewerProps {
    fileUrl: string;
    fileName?: string;
}

interface TestResult {
    name: string;
    status: 'pending' | 'success' | 'error';
    message: string;
    details?: any;
}

const DebugPdfViewer: React.FC<DebugPdfViewerProps> = ({ fileUrl, fileName }) => {
    const [tests, setTests] = useState<TestResult[]>([
        { name: 'URL Accessibility', status: 'pending', message: 'Testing...' },
        { name: 'CORS Headers', status: 'pending', message: 'Testing...' },
        { name: 'Content-Type', status: 'pending', message: 'Testing...' },
        { name: 'PDF.js Worker', status: 'pending', message: 'Testing...' },
        { name: 'PDF Document Load', status: 'pending', message: 'Testing...' },
    ]);

    const updateTest = (name: string, status: 'success' | 'error', message: string, details?: any) => {
        setTests(prev => prev.map(test => 
            test.name === name ? { ...test, status, message, details } : test
        ));
    };

    useEffect(() => {
        runDiagnostics();
    }, [fileUrl]);

    const runDiagnostics = async () => {
        // Reset tests
        setTests(prev => prev.map(test => ({ ...test, status: 'pending', message: 'Testing...' })));

        // Test 1: URL Accessibility
        try {
            const response = await fetch(fileUrl, { 
                method: 'HEAD',
                credentials: 'include'
            });
            
            if (response.ok) {
                updateTest('URL Accessibility', 'success', `Status: ${response.status} ${response.statusText}`);
            } else {
                updateTest('URL Accessibility', 'error', `Status: ${response.status} ${response.statusText}`);
            }

            // Test 2: CORS Headers
            const corsHeaders = {
                'Access-Control-Allow-Origin': response.headers.get('Access-Control-Allow-Origin'),
                'Access-Control-Allow-Credentials': response.headers.get('Access-Control-Allow-Credentials'),
            };
            
            if (corsHeaders['Access-Control-Allow-Origin']) {
                updateTest('CORS Headers', 'success', 'CORS headers present', corsHeaders);
            } else {
                updateTest('CORS Headers', 'error', 'Missing CORS headers', corsHeaders);
            }

            // Test 3: Content-Type
            const contentType = response.headers.get('Content-Type');
            if (contentType?.includes('application/pdf')) {
                updateTest('Content-Type', 'success', `Content-Type: ${contentType}`);
            } else {
                updateTest('Content-Type', 'error', `Unexpected Content-Type: ${contentType || 'missing'}`);
            }

        } catch (error: any) {
            updateTest('URL Accessibility', 'error', `Network error: ${error.message}`);
            updateTest('CORS Headers', 'error', 'Cannot test - network error');
            updateTest('Content-Type', 'error', 'Cannot test - network error');
        }

        // Test 4: PDF.js Worker
        try {
            const { pdfjs } = await import('react-pdf');
            const workerSrc = pdfjs.GlobalWorkerOptions.workerSrc;
            
            if (workerSrc) {
                // Test worker accessibility
                try {
                    const workerResponse = await fetch(workerSrc, { method: 'HEAD' });
                    if (workerResponse.ok) {
                        updateTest('PDF.js Worker', 'success', `Worker loaded from: ${workerSrc}`);
                    } else {
                        updateTest('PDF.js Worker', 'error', `Worker not accessible: ${workerResponse.status}`);
                    }
                } catch (workerError: any) {
                    updateTest('PDF.js Worker', 'error', `Worker fetch failed: ${workerError.message}`);
                }
            } else {
                updateTest('PDF.js Worker', 'error', 'No worker configured');
            }
        } catch (error: any) {
            updateTest('PDF.js Worker', 'error', `PDF.js import failed: ${error.message}`);
        }

        // Test 5: PDF Document Load
        try {
            const { pdfjs } = await import('react-pdf');
            
            const loadingTask = pdfjs.getDocument({
                url: fileUrl,
                withCredentials: true,
                httpHeaders: {
                    'Accept': 'application/pdf',
                },
            });

            const pdf = await loadingTask.promise;
            updateTest('PDF Document Load', 'success', `PDF loaded successfully. Pages: ${pdf.numPages}`);
        } catch (error: any) {
            updateTest('PDF Document Load', 'error', `PDF load failed: ${error.message}`, {
                name: error.name,
                stack: error.stack?.split('\n').slice(0, 3),
            });
        }
    };

    const getStatusIcon = (status: TestResult['status']) => {
        switch (status) {
            case 'pending':
                return <Loader className="w-4 h-4 animate-spin text-blue-500" />;
            case 'success':
                return <CheckCircle className="w-4 h-4 text-green-500" />;
            case 'error':
                return <XCircle className="w-4 h-4 text-red-500" />;
        }
    };

    const getStatusColor = (status: TestResult['status']) => {
        switch (status) {
            case 'pending':
                return 'text-blue-600 bg-blue-50 border-blue-200';
            case 'success':
                return 'text-green-600 bg-green-50 border-green-200';
            case 'error':
                return 'text-red-600 bg-red-50 border-red-200';
        }
    };

    return (
        <div className="bg-white rounded-lg shadow-lg p-6 max-w-4xl mx-auto">
            <div className="flex items-center mb-6">
                <AlertCircle className="w-6 h-6 text-blue-500 mr-2" />
                <h2 className="text-xl font-semibold text-gray-900">PDF Loading Diagnostics</h2>
            </div>

            <div className="mb-4 p-3 bg-gray-50 rounded-lg">
                <p className="text-sm text-gray-600">
                    <strong>File:</strong> {fileName || 'Unknown'}
                </p>
                <p className="text-sm text-gray-600 break-all">
                    <strong>URL:</strong> {fileUrl}
                </p>
            </div>

            <div className="space-y-3">
                {tests.map((test, index) => (
                    <div
                        key={index}
                        className={`p-4 border rounded-lg ${getStatusColor(test.status)}`}
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex items-center">
                                {getStatusIcon(test.status)}
                                <span className="ml-2 font-medium">{test.name}</span>
                            </div>
                            <span className="text-sm">{test.message}</span>
                        </div>
                        
                        {test.details && (
                            <div className="mt-2 p-2 bg-white/50 rounded text-xs">
                                <pre className="whitespace-pre-wrap">
                                    {JSON.stringify(test.details, null, 2)}
                                </pre>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="mt-6 flex justify-center">
                <button
                    onClick={runDiagnostics}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Run Diagnostics Again
                </button>
            </div>

            <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 className="font-medium text-yellow-800 mb-2">Common Issues & Solutions:</h3>
                <ul className="text-sm text-yellow-700 space-y-1">
                    <li>• <strong>CORS errors:</strong> Server needs proper Access-Control headers</li>
                    <li>• <strong>Worker errors:</strong> PDF.js worker not accessible from CDN</li>
                    <li>• <strong>Content-Type:</strong> Server should return 'application/pdf'</li>
                    <li>• <strong>Authentication:</strong> PDF endpoint may require proper session/auth</li>
                    <li>• <strong>File size:</strong> Large PDFs may timeout or fail to load</li>
                </ul>
            </div>
        </div>
    );
};

export default DebugPdfViewer;
