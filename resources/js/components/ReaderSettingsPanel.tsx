import React, { useState, useEffect } from 'react';
import { 
    Settings, X, Palette, Clock, Eye, Smartphone, Volume2, 
    Monitor, Sun, Moon, Coffee, Zap, RotateCw, Move, 
    Navigation, Keyboard, Save, RotateCcw
} from 'lucide-react';

interface ReaderSettings {
    theme: 'dark' | 'light' | 'sepia';
    autoAdvance: boolean;
    autoAdvanceDelay: number;
    fitMode: 'width' | 'height' | 'page';
    showPageNumbers: boolean;
    enableGestures: boolean;
    scrollDirection: 'horizontal' | 'vertical';
    enableKeyboardShortcuts: boolean;
    enableSounds: boolean;
    brightness: number;
    contrast: number;
    pageTransition: 'none' | 'fade' | 'slide';
    doubleClickAction: 'zoom' | 'next' | 'bookmark';
    swipeThreshold: number;
    autoSaveProgress: boolean;
    showProgressBar: boolean;
    showThumbnails: boolean;
    enableFullscreen: boolean;
}

interface ReaderSettingsPanelProps {
    settings: ReaderSettings;
    onSettingsChange: (settings: ReaderSettings) => void;
    onClose?: () => void;
}

const ReaderSettingsPanel: React.FC<ReaderSettingsPanelProps> = ({
    settings,
    onSettingsChange,
    onClose
}) => {
    const [localSettings, setLocalSettings] = useState<ReaderSettings>(settings);
    const [hasChanges, setHasChanges] = useState<boolean>(false);

    useEffect(() => {
        setLocalSettings(settings);
        setHasChanges(false);
    }, [settings]);

    useEffect(() => {
        const hasChanged = JSON.stringify(localSettings) !== JSON.stringify(settings);
        setHasChanges(hasChanged);
    }, [localSettings, settings]);

    const updateSetting = <K extends keyof ReaderSettings>(
        key: K,
        value: ReaderSettings[K]
    ) => {
        setLocalSettings(prev => ({ ...prev, [key]: value }));
    };

    const saveSettings = () => {
        onSettingsChange(localSettings);
        setHasChanges(false);
        
        // Save to localStorage for persistence
        localStorage.setItem('readerSettings', JSON.stringify(localSettings));
    };

    const resetToDefaults = () => {
        const defaultSettings: ReaderSettings = {
            theme: 'dark',
            autoAdvance: false,
            autoAdvanceDelay: 5000,
            fitMode: 'width',
            showPageNumbers: true,
            enableGestures: true,
            scrollDirection: 'horizontal',
            enableKeyboardShortcuts: true,
            enableSounds: false,
            brightness: 100,
            contrast: 100,
            pageTransition: 'none',
            doubleClickAction: 'zoom',
            swipeThreshold: 50,
            autoSaveProgress: true,
            showProgressBar: true,
            showThumbnails: false,
            enableFullscreen: true
        };
        setLocalSettings(defaultSettings);
    };

    const themeOptions = [
        { value: 'dark', label: 'Dark', icon: Moon, description: 'Easy on the eyes in low light' },
        { value: 'light', label: 'Light', icon: Sun, description: 'Better for bright environments' },
        { value: 'sepia', label: 'Sepia', icon: Coffee, description: 'Warm, paper-like appearance' }
    ];

    const fitModeOptions = [
        { value: 'width', label: 'Fit Width', description: 'Fit page to screen width' },
        { value: 'height', label: 'Fit Height', description: 'Fit page to screen height' },
        { value: 'page', label: 'Fit Page', description: 'Fit entire page to screen' }
    ];

    const transitionOptions = [
        { value: 'none', label: 'None', description: 'Instant page changes' },
        { value: 'fade', label: 'Fade', description: 'Smooth fade transition' },
        { value: 'slide', label: 'Slide', description: 'Sliding page transition' }
    ];

    const doubleClickOptions = [
        { value: 'zoom', label: 'Zoom', description: 'Zoom in/out on double click' },
        { value: 'next', label: 'Next Page', description: 'Go to next page' },
        { value: 'bookmark', label: 'Bookmark', description: 'Toggle bookmark' }
    ];

    return (
        <div className="w-80 bg-gray-800 border-l border-gray-700 flex flex-col h-full">
            {/* Header */}
            <div className="p-4 border-b border-gray-700">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                        <Settings className="h-5 w-5 text-emerald-400" />
                        <h3 className="text-lg font-semibold text-white">Reader Settings</h3>
                    </div>
                    {onClose && (
                        <button
                            onClick={onClose}
                            className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                            title="Close Settings"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>

                {/* Action Buttons */}
                <div className="flex gap-2">
                    <button
                        onClick={saveSettings}
                        disabled={!hasChanges}
                        className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Save className="h-4 w-4" />
                        <span>Save</span>
                    </button>
                    <button
                        onClick={resetToDefaults}
                        className="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                        title="Reset to Defaults"
                    >
                        <RotateCcw className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Settings Content */}
            <div className="flex-1 overflow-auto p-4 space-y-6">
                {/* Appearance */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Palette className="h-4 w-4 text-purple-400" />
                        <h4 className="text-sm font-semibold text-white">Appearance</h4>
                    </div>
                    
                    {/* Theme */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">Theme</label>
                        <div className="space-y-2">
                            {themeOptions.map(({ value, label, icon: Icon, description }) => (
                                <label key={value} className="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="theme"
                                        value={value}
                                        checked={localSettings.theme === value}
                                        onChange={(e) => updateSetting('theme', e.target.value as any)}
                                        className="text-emerald-500"
                                    />
                                    <Icon className="h-4 w-4 text-gray-400" />
                                    <div className="flex-1">
                                        <div className="text-sm text-white">{label}</div>
                                        <div className="text-xs text-gray-400">{description}</div>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </div>

                    {/* Brightness */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">
                            Brightness: {localSettings.brightness}%
                        </label>
                        <input
                            type="range"
                            min="50"
                            max="150"
                            value={localSettings.brightness}
                            onChange={(e) => updateSetting('brightness', parseInt(e.target.value))}
                            className="w-full accent-emerald-500"
                        />
                    </div>

                    {/* Contrast */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">
                            Contrast: {localSettings.contrast}%
                        </label>
                        <input
                            type="range"
                            min="50"
                            max="150"
                            value={localSettings.contrast}
                            onChange={(e) => updateSetting('contrast', parseInt(e.target.value))}
                            className="w-full accent-emerald-500"
                        />
                    </div>
                </div>

                {/* Reading Behavior */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Eye className="h-4 w-4 text-blue-400" />
                        <h4 className="text-sm font-semibold text-white">Reading Behavior</h4>
                    </div>

                    {/* Fit Mode */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">Fit Mode</label>
                        <select
                            value={localSettings.fitMode}
                            onChange={(e) => updateSetting('fitMode', e.target.value as any)}
                            className="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white"
                        >
                            {fitModeOptions.map(({ value, label, description }) => (
                                <option key={value} value={value} title={description}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Page Transition */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">Page Transition</label>
                        <select
                            value={localSettings.pageTransition}
                            onChange={(e) => updateSetting('pageTransition', e.target.value as any)}
                            className="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white"
                        >
                            {transitionOptions.map(({ value, label, description }) => (
                                <option key={value} value={value} title={description}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Double Click Action */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-300 mb-2">Double Click Action</label>
                        <select
                            value={localSettings.doubleClickAction}
                            onChange={(e) => updateSetting('doubleClickAction', e.target.value as any)}
                            className="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white"
                        >
                            {doubleClickOptions.map(({ value, label, description }) => (
                                <option key={value} value={value} title={description}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Auto-advance */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Clock className="h-4 w-4 text-orange-400" />
                        <h4 className="text-sm font-semibold text-white">Auto-advance</h4>
                    </div>

                    <div className="mb-4">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.autoAdvance}
                                onChange={(e) => updateSetting('autoAdvance', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Enable auto-advance</span>
                        </label>
                    </div>

                    {localSettings.autoAdvance && (
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Delay: {localSettings.autoAdvanceDelay / 1000}s
                            </label>
                            <input
                                type="range"
                                min="2000"
                                max="15000"
                                step="1000"
                                value={localSettings.autoAdvanceDelay}
                                onChange={(e) => updateSetting('autoAdvanceDelay', parseInt(e.target.value))}
                                className="w-full accent-emerald-500"
                            />
                        </div>
                    )}
                </div>

                {/* Mobile & Touch */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Smartphone className="h-4 w-4 text-green-400" />
                        <h4 className="text-sm font-semibold text-white">Mobile & Touch</h4>
                    </div>

                    <div className="space-y-3">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.enableGestures}
                                onChange={(e) => updateSetting('enableGestures', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Enable touch gestures</span>
                        </label>

                        {localSettings.enableGestures && (
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Swipe Threshold: {localSettings.swipeThreshold}px
                                </label>
                                <input
                                    type="range"
                                    min="20"
                                    max="100"
                                    value={localSettings.swipeThreshold}
                                    onChange={(e) => updateSetting('swipeThreshold', parseInt(e.target.value))}
                                    className="w-full accent-emerald-500"
                                />
                            </div>
                        )}
                    </div>
                </div>

                {/* Interface */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Monitor className="h-4 w-4 text-cyan-400" />
                        <h4 className="text-sm font-semibold text-white">Interface</h4>
                    </div>

                    <div className="space-y-3">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.showPageNumbers}
                                onChange={(e) => updateSetting('showPageNumbers', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Show page numbers</span>
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.showProgressBar}
                                onChange={(e) => updateSetting('showProgressBar', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Show progress bar</span>
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.showThumbnails}
                                onChange={(e) => updateSetting('showThumbnails', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Show thumbnails</span>
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.enableFullscreen}
                                onChange={(e) => updateSetting('enableFullscreen', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Enable fullscreen</span>
                        </label>
                    </div>
                </div>

                {/* Controls */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Keyboard className="h-4 w-4 text-yellow-400" />
                        <h4 className="text-sm font-semibold text-white">Controls</h4>
                    </div>

                    <div className="space-y-3">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.enableKeyboardShortcuts}
                                onChange={(e) => updateSetting('enableKeyboardShortcuts', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Enable keyboard shortcuts</span>
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.enableSounds}
                                onChange={(e) => updateSetting('enableSounds', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Enable sound effects</span>
                        </label>
                    </div>
                </div>

                {/* Data & Privacy */}
                <div>
                    <div className="flex items-center gap-2 mb-3">
                        <Save className="h-4 w-4 text-red-400" />
                        <h4 className="text-sm font-semibold text-white">Data & Privacy</h4>
                    </div>

                    <div className="space-y-3">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={localSettings.autoSaveProgress}
                                onChange={(e) => updateSetting('autoSaveProgress', e.target.checked)}
                                className="rounded text-emerald-500"
                            />
                            <span className="text-sm text-gray-300">Auto-save reading progress</span>
                        </label>
                    </div>
                </div>

                {/* Keyboard Shortcuts Help */}
                {localSettings.enableKeyboardShortcuts && (
                    <div className="bg-gray-700 rounded-lg p-4">
                        <h4 className="text-sm font-semibold text-white mb-3">Keyboard Shortcuts</h4>
                        <div className="space-y-2 text-xs text-gray-300">
                            <div className="flex justify-between">
                                <span>Next/Previous Page</span>
                                <span className="text-gray-400">← → ↑ ↓ Space</span>
                            </div>
                            <div className="flex justify-between">
                                <span>First/Last Page</span>
                                <span className="text-gray-400">Home End</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Zoom In/Out</span>
                                <span className="text-gray-400">+ -</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Toggle Bookmark</span>
                                <span className="text-gray-400">B</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Fullscreen</span>
                                <span className="text-gray-400">F F11</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Close Reader</span>
                                <span className="text-gray-400">Esc</span>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Footer */}
            {hasChanges && (
                <div className="p-4 border-t border-gray-700 bg-gray-750">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-yellow-400">You have unsaved changes</span>
                        <button
                            onClick={saveSettings}
                            className="px-3 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition-colors"
                        >
                            Save Now
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ReaderSettingsPanel;