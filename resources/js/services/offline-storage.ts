// Offline storage service for comic platform

export interface OfflineComic {
    id: number;
    slug: string;
    title: string;
    author: string;
    cover_image_url: string;
    pdf_url: string;
    downloaded_at: string;
    file_size: number;
    last_accessed: string;
}

export interface OfflineReadingProgress {
    comic_id: number;
    current_page: number;
    total_pages: number;
    progress_percentage: number;
    last_read_at: string;
    synced: boolean;
}

export interface OfflineBookmark {
    id: string;
    comic_id: number;
    page_number: number;
    note: string;
    created_at: string;
    synced: boolean;
}

export class OfflineStorageService {
    private static instance: OfflineStorageService;
    private db: IDBDatabase | null = null;
    private readonly dbName = 'ComicPlatformDB';
    private readonly dbVersion = 1;
    
    private constructor() {}
    
    static getInstance(): OfflineStorageService {
        if (!this.instance) {
            this.instance = new OfflineStorageService();
        }
        return this.instance;
    }
    
    async initialize(): Promise<boolean> {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => {
                console.error('Failed to open IndexedDB');
                reject(false);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                console.log('IndexedDB initialized');
                resolve(true);
            };
            
            request.onupgradeneeded = (event) => {
                const db = (event.target as IDBOpenDBRequest).result;
                
                // Comics store
                if (!db.objectStoreNames.contains('comics')) {
                    const comicsStore = db.createObjectStore('comics', { keyPath: 'id' });
                    comicsStore.createIndex('slug', 'slug', { unique: true });
                    comicsStore.createIndex('downloaded_at', 'downloaded_at');
                }
                
                // Reading progress store
                if (!db.objectStoreNames.contains('reading_progress')) {
                    const progressStore = db.createObjectStore('reading_progress', { keyPath: 'comic_id' });
                    progressStore.createIndex('last_read_at', 'last_read_at');
                    progressStore.createIndex('synced', 'synced');
                }
                
                // Bookmarks store
                if (!db.objectStoreNames.contains('bookmarks')) {
                    const bookmarksStore = db.createObjectStore('bookmarks', { keyPath: 'id' });
                    bookmarksStore.createIndex('comic_id', 'comic_id');
                    bookmarksStore.createIndex('synced', 'synced');
                }
                
                // Offline actions store (for sync when back online)
                if (!db.objectStoreNames.contains('offline_actions')) {
                    const actionsStore = db.createObjectStore('offline_actions', { keyPath: 'id', autoIncrement: true });
                    actionsStore.createIndex('type', 'type');
                    actionsStore.createIndex('created_at', 'created_at');
                }
                
                console.log('IndexedDB schema created');
            };
        });
    }
    
    // Comic storage methods
    async storeComic(comic: OfflineComic): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['comics'], 'readwrite');
            const store = transaction.objectStore('comics');
            const request = store.put(comic);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    async getComic(id: number): Promise<OfflineComic | null> {
        if (!this.db) return null;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['comics'], 'readonly');
            const store = transaction.objectStore('comics');
            const request = store.get(id);
            
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(null);
        });
    }
    
    async getComicBySlug(slug: string): Promise<OfflineComic | null> {
        if (!this.db) return null;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['comics'], 'readonly');
            const store = transaction.objectStore('comics');
            const index = store.index('slug');
            const request = index.get(slug);
            
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(null);
        });
    }
    
    async getAllComics(): Promise<OfflineComic[]> {
        if (!this.db) return [];
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['comics'], 'readonly');
            const store = transaction.objectStore('comics');
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject([]);
        });
    }
    
    async removeComic(id: number): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['comics'], 'readwrite');
            const store = transaction.objectStore('comics');
            const request = store.delete(id);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    // Reading progress methods
    async storeReadingProgress(progress: OfflineReadingProgress): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['reading_progress'], 'readwrite');
            const store = transaction.objectStore('reading_progress');
            const request = store.put(progress);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    async getReadingProgress(comicId: number): Promise<OfflineReadingProgress | null> {
        if (!this.db) return null;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['reading_progress'], 'readonly');
            const store = transaction.objectStore('reading_progress');
            const request = store.get(comicId);
            
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(null);
        });
    }
    
    async getAllReadingProgress(): Promise<OfflineReadingProgress[]> {
        if (!this.db) return [];
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['reading_progress'], 'readonly');
            const store = transaction.objectStore('reading_progress');
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject([]);
        });
    }
    
    // Bookmark methods
    async storeBookmark(bookmark: OfflineBookmark): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['bookmarks'], 'readwrite');
            const store = transaction.objectStore('bookmarks');
            const request = store.put(bookmark);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    async getBookmarks(comicId: number): Promise<OfflineBookmark[]> {
        if (!this.db) return [];
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['bookmarks'], 'readonly');
            const store = transaction.objectStore('bookmarks');
            const index = store.index('comic_id');
            const request = index.getAll(comicId);
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject([]);
        });
    }
    
    async removeBookmark(id: string): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['bookmarks'], 'readwrite');
            const store = transaction.objectStore('bookmarks');
            const request = store.delete(id);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    // Offline actions for sync
    async storeOfflineAction(action: any): Promise<boolean> {
        if (!this.db) return false;
        
        const actionWithTimestamp = {
            ...action,
            created_at: new Date().toISOString()
        };
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['offline_actions'], 'readwrite');
            const store = transaction.objectStore('offline_actions');
            const request = store.add(actionWithTimestamp);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    async getOfflineActions(): Promise<any[]> {
        if (!this.db) return [];
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['offline_actions'], 'readonly');
            const store = transaction.objectStore('offline_actions');
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject([]);
        });
    }
    
    async removeOfflineAction(id: number): Promise<boolean> {
        if (!this.db) return false;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db!.transaction(['offline_actions'], 'readwrite');
            const store = transaction.objectStore('offline_actions');
            const request = store.delete(id);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(false);
        });
    }
    
    // Storage management
    async getStorageUsage(): Promise<{ used: number; quota: number }> {
        if ('storage' in navigator && 'estimate' in navigator.storage) {
            try {
                const estimate = await navigator.storage.estimate();
                return {
                    used: estimate.usage || 0,
                    quota: estimate.quota || 0
                };
            } catch (error) {
                console.error('Failed to get storage estimate:', error);
            }
        }
        
        return { used: 0, quota: 0 };
    }
    
    async clearOldData(daysOld: number = 30): Promise<void> {
        if (!this.db) return;
        
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - daysOld);
        const cutoffTimestamp = cutoffDate.toISOString();
        
        // Clear old comics that haven't been accessed recently
        const transaction = this.db.transaction(['comics'], 'readwrite');
        const store = transaction.objectStore('comics');
        const request = store.openCursor();
        
        request.onsuccess = (event) => {
            const cursor = (event.target as IDBRequest).result;
            if (cursor) {
                const comic = cursor.value as OfflineComic;
                if (comic.last_accessed < cutoffTimestamp) {
                    cursor.delete();
                }
                cursor.continue();
            }
        };
    }
}

// Download manager for comics
export class ComicDownloadManager {
    private static instance: ComicDownloadManager;
    private downloads = new Map<number, { progress: number; controller: AbortController }>();
    
    private constructor() {}
    
    static getInstance(): ComicDownloadManager {
        if (!this.instance) {
            this.instance = new ComicDownloadManager();
        }
        return this.instance;
    }
    
    async downloadComic(
        comic: { id: number; slug: string; title: string; author: string; cover_image_url: string; pdf_url: string },
        onProgress?: (progress: number) => void
    ): Promise<boolean> {
        const controller = new AbortController();
        this.downloads.set(comic.id, { progress: 0, controller });
        
        try {
            const response = await fetch(comic.pdf_url, {
                signal: controller.signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const contentLength = response.headers.get('content-length');
            const total = contentLength ? parseInt(contentLength, 10) : 0;
            let loaded = 0;
            
            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('Failed to get response reader');
            }
            
            const chunks: Uint8Array[] = [];
            
            while (true) {
                const { done, value } = await reader.read();
                
                if (done) break;
                
                chunks.push(value);
                loaded += value.length;
                
                if (total > 0) {
                    const progress = (loaded / total) * 100;
                    this.downloads.set(comic.id, { progress, controller });
                    onProgress?.(progress);
                }
            }
            
            // Combine chunks into blob
            const blob = new Blob(chunks, { type: 'application/pdf' });
            const blobUrl = URL.createObjectURL(blob);
            
            // Store in IndexedDB
            const storage = OfflineStorageService.getInstance();
            const offlineComic: OfflineComic = {
                id: comic.id,
                slug: comic.slug,
                title: comic.title,
                author: comic.author,
                cover_image_url: comic.cover_image_url,
                pdf_url: blobUrl,
                downloaded_at: new Date().toISOString(),
                file_size: loaded,
                last_accessed: new Date().toISOString()
            };
            
            await storage.storeComic(offlineComic);
            this.downloads.delete(comic.id);
            
            return true;
        } catch (error) {
            console.error('Download failed:', error);
            this.downloads.delete(comic.id);
            return false;
        }
    }
    
    cancelDownload(comicId: number): void {
        const download = this.downloads.get(comicId);
        if (download) {
            download.controller.abort();
            this.downloads.delete(comicId);
        }
    }
    
    getDownloadProgress(comicId: number): number {
        return this.downloads.get(comicId)?.progress || 0;
    }
    
    isDownloading(comicId: number): boolean {
        return this.downloads.has(comicId);
    }
}

// Export singleton instances
export const offlineStorage = OfflineStorageService.getInstance();
export const downloadManager = ComicDownloadManager.getInstance();