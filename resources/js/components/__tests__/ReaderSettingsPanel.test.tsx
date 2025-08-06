import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { vi } from 'vitest';
import ReaderSettingsPanel from '../ReaderSettingsPanel';

const defaultSettings = {
    theme: 'dark' as const,
    autoAdvance: false,
    autoAdvanceDelay: 5000,
    fitMode: 'width' as const,
    showPageNumbers: true,
    enableGestures: true,
    scrollDirection: 'horizontal' as const,
    enableKeyboardShortcuts: true,
    enableSounds: false,
    brightness: 100,
    contrast: 100,
    pageTransition: 'none' as const,
    doubleClickAction: 'zoom' as const,
    swipeThreshold: 50,
    autoSaveProgress: true,
    showProgressBar: true,
    showThumbnails: false,
    enableFullscreen: true
};

const mockProps = {
    settings: defaultSettings,
    onSettingsChange: vi.fn(),
    onClose: vi.fn()
};

// Mock localStorage
const localStorageMock = {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn(),
};
Object.defineProperty(window, 'localStorage', {
    value: localStorageMock
});

describe('ReaderSettingsPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders settings panel interface', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        expect(screen.getByText('Reader Settings')).toBeInTheDocument();
        expect(screen.getByText('Save')).toBeInTheDocument();
        expect(screen.getByTitle('Reset to Defaults')).toBeInTheDocument();
        expect(screen.getByText('Appearance')).toBeInTheDocument();
        expect(screen.getByText('Reading Behavior')).toBeInTheDocument();
        expect(screen.getByText('Auto-advance')).toBeInTheDocument();
    });

    it('displays current settings correctly', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        // Check theme selection
        expect(screen.getByDisplayValue('dark')).toBeChecked();
        
        // Check checkboxes
        expect(screen.getByLabelText('Show page numbers')).toBeChecked();
        expect(screen.getByLabelText('Enable touch gestures')).toBeChecked();
        expect(screen.getByLabelText('Enable keyboard shortcuts')).toBeChecked();
        expect(screen.getByLabelText('Auto-save reading progress')).toBeChecked();
        
        // Check that auto-advance is not enabled
        expect(screen.getByLabelText('Enable auto-advance')).not.toBeChecked();
    });

    it('updates theme setting', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Select light theme
        const lightThemeRadio = screen.getByDisplayValue('light');
        await user.click(lightThemeRadio);

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            theme: 'light'
        });
    });

    it('updates brightness setting', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Find brightness slider
        const brightnessSlider = screen.getByDisplayValue('100');
        await user.clear(brightnessSlider);
        await user.type(brightnessSlider, '80');

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            brightness: 80
        });
    });

    it('enables auto-advance and shows delay setting', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Enable auto-advance
        const autoAdvanceCheckbox = screen.getByLabelText('Enable auto-advance');
        await user.click(autoAdvanceCheckbox);

        // Should show delay slider
        expect(screen.getByText('Delay: 5s')).toBeInTheDocument();

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            autoAdvance: true
        });
    });

    it('updates auto-advance delay', async () => {
        const user = userEvent.setup();
        const settingsWithAutoAdvance = {
            ...defaultSettings,
            autoAdvance: true
        };

        render(<ReaderSettingsPanel {...mockProps} settings={settingsWithAutoAdvance} />);

        // Find delay slider
        const delaySlider = screen.getByDisplayValue('5000');
        fireEvent.change(delaySlider, { target: { value: '8000' } });

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...settingsWithAutoAdvance,
            autoAdvanceDelay: 8000
        });
    });

    it('updates fit mode setting', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Change fit mode
        const fitModeSelect = screen.getByDisplayValue('Fit Width');
        await user.selectOptions(fitModeSelect, 'height');

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            fitMode: 'height'
        });
    });

    it('updates page transition setting', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Change page transition
        const transitionSelect = screen.getByDisplayValue('None');
        await user.selectOptions(transitionSelect, 'fade');

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            pageTransition: 'fade'
        });
    });

    it('updates double click action', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Change double click action
        const doubleClickSelect = screen.getByDisplayValue('Zoom');
        await user.selectOptions(doubleClickSelect, 'next');

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            doubleClickAction: 'next'
        });
    });

    it('shows swipe threshold when gestures are enabled', async () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        expect(screen.getByText('Swipe Threshold: 50px')).toBeInTheDocument();
    });

    it('hides swipe threshold when gestures are disabled', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Disable gestures
        const gesturesCheckbox = screen.getByLabelText('Enable touch gestures');
        await user.click(gesturesCheckbox);

        expect(screen.queryByText('Swipe Threshold:')).not.toBeInTheDocument();
    });

    it('updates swipe threshold', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Find swipe threshold slider
        const thresholdSlider = screen.getByDisplayValue('50');
        fireEvent.change(thresholdSlider, { target: { value: '75' } });

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            swipeThreshold: 75
        });
    });

    it('shows keyboard shortcuts help when enabled', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        expect(screen.getByText('Keyboard Shortcuts')).toBeInTheDocument();
        expect(screen.getByText('Next/Previous Page')).toBeInTheDocument();
        expect(screen.getByText('← → ↑ ↓ Space')).toBeInTheDocument();
    });

    it('hides keyboard shortcuts help when disabled', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Disable keyboard shortcuts
        const keyboardCheckbox = screen.getByLabelText('Enable keyboard shortcuts');
        await user.click(keyboardCheckbox);

        expect(screen.queryByText('Keyboard Shortcuts')).not.toBeInTheDocument();
    });

    it('shows unsaved changes indicator', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Make a change
        const pageNumbersCheckbox = screen.getByLabelText('Show page numbers');
        await user.click(pageNumbersCheckbox);

        expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();
        expect(screen.getByText('Save Now')).toBeInTheDocument();
    });

    it('saves settings to localStorage', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Make a change
        const pageNumbersCheckbox = screen.getByLabelText('Show page numbers');
        await user.click(pageNumbersCheckbox);

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(localStorageMock.setItem).toHaveBeenCalledWith(
            'readerSettings',
            JSON.stringify({
                ...defaultSettings,
                showPageNumbers: false
            })
        );
    });

    it('resets to default settings', async () => {
        const user = userEvent.setup();
        const customSettings = {
            ...defaultSettings,
            theme: 'light' as const,
            showPageNumbers: false,
            brightness: 80
        };

        render(<ReaderSettingsPanel {...mockProps} settings={customSettings} />);

        // Reset to defaults
        const resetButton = screen.getByTitle('Reset to Defaults');
        await user.click(resetButton);

        // Should show default values
        expect(screen.getByDisplayValue('dark')).toBeChecked();
        expect(screen.getByLabelText('Show page numbers')).toBeChecked();
        expect(screen.getByDisplayValue('100')).toBeInTheDocument(); // Brightness
    });

    it('calls onClose when close button is clicked', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        const closeButton = screen.getByTitle('Close Settings');
        await user.click(closeButton);

        expect(mockProps.onClose).toHaveBeenCalled();
    });

    it('updates multiple checkbox settings', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Toggle multiple checkboxes
        await user.click(screen.getByLabelText('Show page numbers'));
        await user.click(screen.getByLabelText('Show progress bar'));
        await user.click(screen.getByLabelText('Enable sound effects'));

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(mockProps.onSettingsChange).toHaveBeenCalledWith({
            ...defaultSettings,
            showPageNumbers: false,
            showProgressBar: false,
            enableSounds: true
        });
    });

    it('disables save button when no changes are made', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        const saveButton = screen.getByText('Save');
        expect(saveButton).toBeDisabled();
    });

    it('enables save button when changes are made', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Make a change
        const pageNumbersCheckbox = screen.getByLabelText('Show page numbers');
        await user.click(pageNumbersCheckbox);

        const saveButton = screen.getByText('Save');
        expect(saveButton).not.toBeDisabled();
    });

    it('resets hasChanges state after saving', async () => {
        const user = userEvent.setup();
        render(<ReaderSettingsPanel {...mockProps} />);

        // Make a change
        const pageNumbersCheckbox = screen.getByLabelText('Show page numbers');
        await user.click(pageNumbersCheckbox);

        expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();

        // Save settings
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(screen.queryByText('You have unsaved changes')).not.toBeInTheDocument();
    });

    it('updates settings when props change', () => {
        const { rerender } = render(<ReaderSettingsPanel {...mockProps} />);

        expect(screen.getByDisplayValue('dark')).toBeChecked();

        // Update props
        const newSettings = { ...defaultSettings, theme: 'light' as const };
        rerender(<ReaderSettingsPanel {...mockProps} settings={newSettings} />);

        expect(screen.getByDisplayValue('light')).toBeChecked();
    });

    it('shows all theme options', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        expect(screen.getByText('Dark')).toBeInTheDocument();
        expect(screen.getByText('Light')).toBeInTheDocument();
        expect(screen.getByText('Sepia')).toBeInTheDocument();
    });

    it('shows all fit mode options', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        const fitModeSelect = screen.getByDisplayValue('Fit Width');
        expect(fitModeSelect).toBeInTheDocument();
        
        // Check that all options are available
        expect(screen.getByText('Fit Width')).toBeInTheDocument();
        expect(screen.getByText('Fit Height')).toBeInTheDocument();
        expect(screen.getByText('Fit Page')).toBeInTheDocument();
    });

    it('shows all transition options', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        const transitionSelect = screen.getByDisplayValue('None');
        expect(transitionSelect).toBeInTheDocument();
    });

    it('shows all double click action options', () => {
        render(<ReaderSettingsPanel {...mockProps} />);

        const doubleClickSelect = screen.getByDisplayValue('Zoom');
        expect(doubleClickSelect).toBeInTheDocument();
    });
});