import React, { useState, useEffect } from 'react';
import { Bookmark, BookmarkCheck, Edit3, Trash2, Plus, Search, X, Calendar, FileText } from 'lucide-react';

interface Bookmark {
    id: string;
    page: number;
    note?: string;
    created_at: string;
    updated_at?: string;
}

interface BookmarkManagerProps {
    comicSlug: string;
    currentPage: number;
    onGoToPage: (page: number) => void;
    onClose?: () => void;
}

const BookmarkManager: React.FC<BookmarkManagerProps> = ({
    comicSlug,
    currentPage,
    onGoToPage,
    onClose
}) => {
    const [bookmarks, setBookmarks] = useState<Bookmark[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [editingBookmark, setEditingBookmark] = useState<string | null>(null);
    const [editNote, setEditNote] = useState<string>('');
    const [showAddForm, setShowAddForm] = useState<boolean>(false);
    const [newBookmarkNote, setNewBookmarkNote] = useState<string>('');

    useEffect(() => {
        loadBookmarks();
    }, [comicSlug]);

    const loadBookmarks = async () => {
        setLoading(true);
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
            } else {
                console.error('Failed to load bookmarks');
            }
        } catch (error) {
            console.error('Error loading bookmarks:', error);
        } finally {
            setLoading(false);
        }
    };

    const addBookmark = async (page: number, note: string = '') => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
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
                    page_number: page,
                    note: note || `Bookmark on page ${page}`
                })
            });

            if (response.ok) {
                const data = await response.json();
                setBookmarks(prev => [...prev, data].sort((a, b) => a.page - b.page));
                setShowAddForm(false);
                setNewBookmarkNote('');
            } else {
                console.error('Failed to add bookmark');
            }
        } catch (error) {
            console.error('Error adding bookmark:', error);
        }
    };

    const updateBookmark = async (bookmarkId: string, note: string) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch(`/api/comics/${comicSlug}/bookmarks/${bookmarkId}`, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({ note })
            });

            if (response.ok) {
                const data = await response.json();
                setBookmarks(prev => prev.map(b => b.id === bookmarkId ? data : b));
                setEditingBookmark(null);
                setEditNote('');
            } else {
                console.error('Failed to update bookmark');
            }
        } catch (error) {
            console.error('Error updating bookmark:', error);
        }
    };

    const deleteBookmark = async (bookmarkId: string) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch(`/api/comics/${comicSlug}/bookmarks/${bookmarkId}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                }
            });

            if (response.ok) {
                setBookmarks(prev => prev.filter(b => b.id !== bookmarkId));
            } else {
                console.error('Failed to delete bookmark');
            }
        } catch (error) {
            console.error('Error deleting bookmark:', error);
        }
    };

    const startEditing = (bookmark: Bookmark) => {
        setEditingBookmark(bookmark.id);
        setEditNote(bookmark.note || '');
    };

    const cancelEditing = () => {
        setEditingBookmark(null);
        setEditNote('');
    };

    const saveEdit = () => {
        if (editingBookmark) {
            updateBookmark(editingBookmark, editNote);
        }
    };

    const filteredBookmarks = bookmarks.filter(bookmark =>
        bookmark.note?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        bookmark.page.toString().includes(searchTerm)
    );

    const isCurrentPageBookmarked = bookmarks.some(b => b.page === currentPage);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <div className="w-80 bg-gray-800 border-l border-gray-700 flex flex-col h-full">
            {/* Header */}
            <div className="p-4 border-b border-gray-700">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-white">Bookmarks</h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setShowAddForm(!showAddForm)}
                            className="p-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                            title="Add Bookmark"
                        >
                            <Plus className="h-4 w-4" />
                        </button>
                        {onClose && (
                            <button
                                onClick={onClose}
                                className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                                title="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search bookmarks..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </div>

            {/* Add Bookmark Form */}
            {showAddForm && (
                <div className="p-4 border-b border-gray-700 bg-gray-750">
                    <div className="space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-1">
                                Page: {currentPage}
                            </label>
                            <div className="flex items-center gap-2 text-sm text-gray-400">
                                {isCurrentPageBookmarked ? (
                                    <>
                                        <BookmarkCheck className="h-4 w-4 text-yellow-500" />
                                        <span>Already bookmarked</span>
                                    </>
                                ) : (
                                    <>
                                        <Bookmark className="h-4 w-4" />
                                        <span>Current page</span>
                                    </>
                                )}
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-1">
                                Note (optional)
                            </label>
                            <textarea
                                value={newBookmarkNote}
                                onChange={(e) => setNewBookmarkNote(e.target.value)}
                                placeholder="Add a note for this bookmark..."
                                className="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                                rows={2}
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => addBookmark(currentPage, newBookmarkNote)}
                                disabled={isCurrentPageBookmarked}
                                className="flex-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                            >
                                Add Bookmark
                            </button>
                            <button
                                onClick={() => {
                                    setShowAddForm(false);
                                    setNewBookmarkNote('');
                                }}
                                className="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Bookmarks List */}
            <div className="flex-1 overflow-auto">
                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                    </div>
                ) : filteredBookmarks.length === 0 ? (
                    <div className="text-center py-8 px-4">
                        <Bookmark className="h-12 w-12 text-gray-500 mx-auto mb-3" />
                        <p className="text-gray-400 mb-2">
                            {searchTerm ? 'No bookmarks found' : 'No bookmarks yet'}
                        </p>
                        <p className="text-sm text-gray-500">
                            {searchTerm ? 'Try a different search term' : 'Add your first bookmark to get started'}
                        </p>
                    </div>
                ) : (
                    <div className="p-4 space-y-3">
                        {filteredBookmarks.map((bookmark) => (
                            <div
                                key={bookmark.id}
                                className={`p-3 rounded-lg border transition-all duration-200 ${
                                    bookmark.page === currentPage
                                        ? 'bg-emerald-900/30 border-emerald-500/50'
                                        : 'bg-gray-700 border-gray-600 hover:bg-gray-650'
                                }`}
                            >
                                <div className="flex items-start justify-between mb-2">
                                    <button
                                        onClick={() => onGoToPage(bookmark.page)}
                                        className="flex items-center gap-2 text-left hover:text-emerald-400 transition-colors"
                                    >
                                        <Bookmark className={`h-4 w-4 flex-shrink-0 ${
                                            bookmark.page === currentPage ? 'text-emerald-400' : 'text-yellow-500'
                                        }`} />
                                        <span className="font-medium">Page {bookmark.page}</span>
                                    </button>
                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() => startEditing(bookmark)}
                                            className="p-1 rounded hover:bg-gray-600 transition-colors"
                                            title="Edit bookmark"
                                        >
                                            <Edit3 className="h-3 w-3 text-gray-400" />
                                        </button>
                                        <button
                                            onClick={() => deleteBookmark(bookmark.id)}
                                            className="p-1 rounded hover:bg-gray-600 transition-colors"
                                            title="Delete bookmark"
                                        >
                                            <Trash2 className="h-3 w-3 text-red-400" />
                                        </button>
                                    </div>
                                </div>

                                {editingBookmark === bookmark.id ? (
                                    <div className="space-y-2">
                                        <textarea
                                            value={editNote}
                                            onChange={(e) => setEditNote(e.target.value)}
                                            className="w-full p-2 bg-gray-600 border border-gray-500 rounded text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                                            rows={2}
                                            placeholder="Add a note..."
                                        />
                                        <div className="flex gap-2">
                                            <button
                                                onClick={saveEdit}
                                                className="px-2 py-1 bg-emerald-600 text-white rounded text-xs hover:bg-emerald-700 transition-colors"
                                            >
                                                Save
                                            </button>
                                            <button
                                                onClick={cancelEditing}
                                                className="px-2 py-1 bg-gray-600 text-white rounded text-xs hover:bg-gray-700 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {bookmark.note && (
                                            <div className="flex items-start gap-2 mb-2">
                                                <FileText className="h-3 w-3 text-gray-400 mt-0.5 flex-shrink-0" />
                                                <p className="text-sm text-gray-300 leading-relaxed">{bookmark.note}</p>
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2 text-xs text-gray-500">
                                            <Calendar className="h-3 w-3" />
                                            <span>{formatDate(bookmark.created_at)}</span>
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Footer Stats */}
            <div className="p-4 border-t border-gray-700 bg-gray-750">
                <div className="flex items-center justify-between text-sm text-gray-400">
                    <span>{bookmarks.length} bookmark{bookmarks.length !== 1 ? 's' : ''}</span>
                    {searchTerm && (
                        <span>{filteredBookmarks.length} found</span>
                    )}
                </div>
            </div>
        </div>
    );
};

export default BookmarkManager;