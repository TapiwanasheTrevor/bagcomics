# BagComics Frontend Integration Plan

## Overview

This document outlines the integration plan for the new React-based frontend located in `public/frontend/` with the existing Laravel backend. The new frontend uses **image-based comic pages** instead of PDFs, addressing the core pain points.

---

## Pain Points → Solutions Mapping

| Pain Point | New Frontend Solution |
|------------|----------------------|
| Complicated Access | One-click from card → Reader (no intermediate pages) |
| Broken Zoom/Scroll | Native browser image rendering with vertical scroll |
| Non-Intuitive Mobile Nav | Vertical scroll is the default, touch-friendly |
| Session Errors | Will use Laravel Sanctum with token refresh |
| Asset Failures | Cloud storage (S3/Cloudinary) for all images |
| Missing Social Proof | Bottom bar with Like, Rating, Comments |
| No Auto-Notifications | Backend trigger on comic publish (already exists) |

---

## Architecture

### Current State
```
Laravel Backend → Inertia.js → React (resources/js/)
                 ↓
              PDF files
```

### New State
```
Laravel Backend → REST API → New React Frontend (public/frontend/)
                 ↓
              Image URLs (S3/Cloudinary)
```

---

## Database Changes Required

### New Table: `comic_pages`

Stores individual page images for each comic.

```sql
CREATE TABLE comic_pages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    comic_id BIGINT NOT NULL,
    page_number INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    image_path VARCHAR(500) NULL,
    width INT NULL,
    height INT NULL,
    file_size INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (comic_id) REFERENCES comics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comic_page (comic_id, page_number)
);
```

### New Table: `comic_likes`

Tracks user likes (hearts) for comics.

```sql
CREATE TABLE comic_likes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    comic_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    UNIQUE KEY unique_user_comic (user_id, comic_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comic_id) REFERENCES comics(id) ON DELETE CASCADE
);
```

### New Table: `comic_comments`

User comments on comics.

```sql
CREATE TABLE comic_comments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    comic_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comic_id) REFERENCES comics(id) ON DELETE CASCADE
);
```

---

## API Endpoints Required

### Public Endpoints (No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v2/comics` | List all visible comics |
| GET | `/api/v2/comics/{slug}` | Get comic details with pages |
| GET | `/api/v2/comics/{slug}/pages` | Get all page images for a comic |
| GET | `/api/v2/genres` | List all genres |
| GET | `/api/v2/comics/featured` | Featured/trending comics |
| GET | `/api/v2/comics/recent` | Recently added comics |

### Protected Endpoints (Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v2/auth/login` | Login and get token |
| POST | `/api/v2/auth/register` | Register new user |
| POST | `/api/v2/auth/logout` | Logout and invalidate token |
| GET | `/api/v2/auth/user` | Get current user |
| POST | `/api/v2/comics/{slug}/like` | Toggle like on comic |
| GET | `/api/v2/comics/{slug}/like-status` | Check if user liked |
| POST | `/api/v2/comics/{slug}/rate` | Rate comic (1-5 stars) |
| GET | `/api/v2/comics/{slug}/comments` | Get comments |
| POST | `/api/v2/comics/{slug}/comments` | Add comment |
| GET | `/api/v2/library` | User's bookmarked comics |
| POST | `/api/v2/library/{slug}` | Add to library/bookmarks |
| DELETE | `/api/v2/library/{slug}` | Remove from library |
| PATCH | `/api/v2/progress/{slug}` | Update reading progress |

---

## API Response Formats

### Comic List Response
```json
{
  "data": [
    {
      "id": "1",
      "slug": "magnus-jakuta",
      "title": "Magnus Jakuta",
      "author": "Littles Arscott",
      "description": "A powerful warrior rises...",
      "coverImage": "https://cdn.bagcomics.com/covers/magnus.jpg",
      "genre": ["Action", "Sci-Fi"],
      "rating": 4.8,
      "totalChapters": 12,
      "episodes": 1,
      "likesCount": 245,
      "isLiked": false,
      "isBookmarked": false
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50
  }
}
```

### Comic Detail Response (with pages)
```json
{
  "data": {
    "id": "1",
    "slug": "magnus-jakuta",
    "title": "Magnus Jakuta",
    "author": "Littles Arscott",
    "description": "A powerful warrior rises...",
    "coverImage": "https://cdn.bagcomics.com/covers/magnus.jpg",
    "genre": ["Action", "Sci-Fi"],
    "rating": 4.8,
    "totalChapters": 12,
    "episodes": 1,
    "pages": [
      "https://cdn.bagcomics.com/pages/magnus/001.jpg",
      "https://cdn.bagcomics.com/pages/magnus/002.jpg",
      "https://cdn.bagcomics.com/pages/magnus/003.jpg"
    ],
    "likesCount": 245,
    "commentsCount": 32,
    "isLiked": false,
    "isBookmarked": false,
    "userProgress": {
      "currentPage": 5,
      "totalPages": 24,
      "percentage": 20.8
    }
  }
}
```

---

## Frontend Changes Required

### 1. Create API Service (`public/frontend/services/api.ts`)

```typescript
const API_BASE = '/api/v2';

export const api = {
  // Comics
  getComics: () => fetch(`${API_BASE}/comics`).then(r => r.json()),
  getComic: (slug: string) => fetch(`${API_BASE}/comics/${slug}`).then(r => r.json()),
  getFeatured: () => fetch(`${API_BASE}/comics/featured`).then(r => r.json()),
  getRecent: () => fetch(`${API_BASE}/comics/recent`).then(r => r.json()),

  // Auth
  login: (email: string, password: string) =>
    fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    }).then(r => r.json()),

  // Library
  getLibrary: () => fetchWithAuth(`${API_BASE}/library`),
  addToLibrary: (slug: string) =>
    fetchWithAuth(`${API_BASE}/library/${slug}`, { method: 'POST' }),
  removeFromLibrary: (slug: string) =>
    fetchWithAuth(`${API_BASE}/library/${slug}`, { method: 'DELETE' }),

  // Engagement
  toggleLike: (slug: string) =>
    fetchWithAuth(`${API_BASE}/comics/${slug}/like`, { method: 'POST' }),
  rateComic: (slug: string, rating: number) =>
    fetchWithAuth(`${API_BASE}/comics/${slug}/rate`, {
      method: 'POST',
      body: JSON.stringify({ rating })
    }),
  getComments: (slug: string) =>
    fetch(`${API_BASE}/comics/${slug}/comments`).then(r => r.json()),
  addComment: (slug: string, content: string) =>
    fetchWithAuth(`${API_BASE}/comics/${slug}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content })
    }),
};
```

### 2. Update App.tsx to Use API

Replace `MOCK_COMICS` with API calls:

```typescript
const [comics, setComics] = useState<Comic[]>([]);
const [loading, setLoading] = useState(true);

useEffect(() => {
  api.getRecent().then(res => {
    setComics(res.data);
    setLoading(false);
  });
}, []);
```

### 3. Add Auth Context

Create authentication state management for login/logout.

### 4. Update Types

```typescript
export interface Comic {
  id: string;
  slug: string;
  title: string;
  author: string;
  description: string;
  coverImage: string;
  genre: string[];
  rating: number;
  totalChapters: number;
  episodes: number;
  pages: string[];
  likesCount: number;
  commentsCount: number;
  isLiked?: boolean;
  isBookmarked?: boolean;
  userProgress?: {
    currentPage: number;
    totalPages: number;
    percentage: number;
  };
}
```

---

## Deployment Options

### Option 1: Integrated with Laravel (Recommended)

Build the frontend and serve from Laravel:

```bash
cd public/frontend
npm run build
# Output goes to public/frontend/dist/
```

Configure Laravel route to serve the SPA:

```php
// routes/web.php
Route::get('/app/{any?}', function () {
    return file_get_contents(public_path('frontend/dist/index.html'));
})->where('any', '.*');
```

### Option 2: Separate Deployment

Deploy frontend to Vercel/Netlify, backend stays on current hosting.

- Frontend: `app.bagcomics.com`
- Backend API: `api.bagcomics.com`

---

## File Storage Migration

### Current State
- Files in `storage/app/public/`
- Symlinked to `public/storage/`
- Lost on redeploy

### Target State
- All images on AWS S3 or Cloudinary
- CDN for fast delivery
- Permanent URLs

### Migration Steps

1. Set up S3 bucket or Cloudinary account
2. Update `.env`:
   ```
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=xxx
   AWS_SECRET_ACCESS_KEY=xxx
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=bagcomics-assets
   AWS_URL=https://cdn.bagcomics.com
   ```
3. Create migration command to upload existing files
4. Update Comic model to return full S3 URLs

---

## Implementation Phases

### Phase 1: Backend API (Week 1)
- [x] Create integration plan
- [ ] Create `comic_pages` migration
- [ ] Create `ComicPage` model
- [ ] Create `comic_likes` migration
- [ ] Create `ComicLike` model
- [ ] Create `comic_comments` migration
- [ ] Create `ComicComment` model
- [ ] Create V2 API controllers
- [ ] Create API routes

### Phase 2: Frontend Integration (Week 2)
- [ ] Create API service layer
- [ ] Add auth context
- [ ] Replace mock data with API calls
- [ ] Implement like/rate/comment UI
- [ ] Add loading states
- [ ] Add error handling

### Phase 3: File Storage (Week 3)
- [ ] Set up S3/Cloudinary
- [ ] Migrate existing assets
- [ ] Update admin panel for image uploads
- [ ] Test CDN delivery

### Phase 4: Testing & Launch (Week 4)
- [ ] Cross-browser testing
- [ ] Mobile device testing
- [ ] Performance optimization
- [ ] Production deployment

---

## Admin Panel Updates

The Filament admin panel needs updates to support:

1. **Comic Page Management**
   - Upload multiple page images per comic
   - Reorder pages via drag-and-drop
   - Preview pages in grid view

2. **Bulk Image Upload**
   - ZIP file upload → auto-extract pages
   - Or drag-drop multiple images at once

3. **Moderation**
   - Comment moderation queue
   - User like analytics

---

## Summary

The new frontend is well-architected with:
- Image-based vertical scrolling (solves PDF issues)
- One-click access to reader (solves navigation issues)
- Clean dark theme matching Galatoons style
- Mobile-first responsive design

Key integration work needed:
1. Database changes for pages, likes, comments
2. New V2 API endpoints
3. Frontend API service layer
4. Cloud storage migration

This approach completely eliminates PDF-related bugs and provides a modern, fast reading experience.
