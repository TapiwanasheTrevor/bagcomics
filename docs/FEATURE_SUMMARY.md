# BagComics - Feature Summary for Rebuild

## Overview
BagComics is a digital comic book reading platform focused on African storytelling. The app allows users to browse, purchase, and read comics in PDF format with progress tracking, social features, and gamification elements.

---

## Tech Stack

### Backend
- **Framework:** Laravel 12.0 (PHP 8.2+)
- **Database:** PostgreSQL
- **Authentication:** Laravel Sanctum + Session-based
- **Payments:** Stripe via Laravel Cashier v15.7
- **Admin Panel:** Filament v3.3
- **Search:** Meilisearch with Laravel Scout
- **File Storage:** Local filesystem with AWS S3 support (configurable)
- **Queue:** Database-driven
- **Email:** SendGrid/SMTP

### Frontend
- **Framework:** React 19.0 with TypeScript
- **Build Tool:** Vite v7.0.4
- **SSR:** Inertia.js v2.0
- **Styling:** Tailwind CSS v4.0
- **Component Library:** Radix UI primitives
- **PDF Rendering:** PDF.js / react-pdf v10.0.1

---

## Critical Features for Rebuild

### 1. Comic Catalog & Display

**Current Implementation:**
- Comics stored with metadata: title, author, genre, tags, description, page count, language, publisher, ISBN
- Cover images stored in `/storage/` directory
- PDF files stored in `storage/app/public/` with path references in database
- Automatic slug generation via Spatie/Sluggable
- Visibility controls (`is_visible`, `is_free`, `has_mature_content`)
- Genre and tag-based categorization

**Key Files:**
- `app/Models/Comic.php` - Core model (829 lines)
- `app/Filament/Resources/ComicResource.php` - Admin management
- `resources/js/pages/comics/index.tsx` - Catalog page
- `resources/js/pages/comics/show.tsx` - Detail page (747 lines)

**Features:**
- Featured comics carousel on homepage
- Trending, new releases, and free comics sections
- Search with autocomplete (Meilisearch)
- Genre/tag filtering
- Rating display (5-star system)
- Reading time estimates
- View count tracking

---

### 2. PDF Reader System

**Current Implementation:**
- Primary reader: `EnhancedPdfReader.tsx` (1,188 lines)
- PDF streaming via `PdfStreamController.php`
- Uses react-pdf with PDF.js worker
- Supports range requests for progressive loading

**Reader Features:**
- Page-by-page navigation (arrows, keyboard, swipe)
- Zoom controls (50%-300%, pinch-to-zoom on mobile)
- Pan/drag when zoomed in
- Rotation support
- Auto-advance mode (configurable delay)
- Bookmarking system
- Progress bar display
- Theme options (dark/light/sepia)
- Fullscreen mode
- Dynamic padding calculation for zoom levels
- Touch gestures (swipe, pinch, double-tap)
- Keyboard shortcuts (arrows, +/-, f, b, Esc)

**Pain Point Analysis:**
The reader has complex dynamic padding logic attempting to fix zoom cutoff issues:
```javascript
// Lines 111-143 of EnhancedPdfReader.tsx
calculateDynamicPadding(currentScale) - calculates padding based on zoom
```
This complexity suggests the underlying scroll/zoom architecture has fundamental issues.

**PDF Streaming:**
- Range request support for large files
- CORS headers for PDF.js compatibility
- Authentication checks for non-free content
- Fallback to direct file paths

---

### 3. User Authentication & Access

**Current Implementation:**
- Standard Laravel auth (session-based for web, Sanctum for API)
- Email verification required
- Password reset via email
- CSRF protection on all forms

**User Journey to Reader:**
1. Homepage → Browse Comics
2. Click comic → Comic Detail Page
3. Login if not authenticated
4. Add to library (free) OR purchase (paid)
5. Click "Start Reading" → Reader page

**Pain Points Identified:**
- Session errors (419 Page Expired) likely from:
  - CSRF token expiration on long-idle sessions
  - Session driver configuration (currently database)
  - Inertia.js CSRF handling edge cases
- Password reset issues likely from:
  - Email configuration (SendGrid/SMTP settings)
  - Missing queue worker for async notifications

**Key Files:**
- `app/Http/Controllers/Auth/` - 8 auth controllers
- `app/Http/Requests/Auth/LoginRequest.php`
- `config/session.php` - Session configuration

---

### 4. User Library & Progress Tracking

**Current Implementation:**
- `UserLibrary` model tracks: access type, purchase info, favorites, ratings
- `UserComicProgress` model tracks: current page, total pages, reading time
- Progress saved automatically on page change
- Completion percentage calculated

**Library Features:**
- Access types: free, purchased, subscription
- Favorites list
- Personal ratings
- Reading time tracking
- Completion status (unread/reading/completed)
- Device sync tokens
- Last accessed timestamps

**API Endpoints:**
- `GET /api/library` - List user's library
- `POST /api/library/comics/{slug}` - Add to library
- `POST /api/library/comics/{slug}/favorite` - Toggle favorite
- `PATCH /api/progress/comics/{slug}` - Update progress

---

### 5. Payment System

**Current Implementation:**
- Stripe integration via Laravel Cashier
- Payment intents for individual purchases
- Subscription support (infrastructure present)
- Webhook handling for payment confirmations
- Receipt generation

**Key Files:**
- `app/Services/PaymentService.php`
- `app/Http/Controllers/Api/PaymentController.php`
- `app/Http/Controllers/StripeWebhookController.php`
- `resources/js/components/PaymentModal.tsx`

**Payment Flow:**
1. User clicks "Purchase" on comic detail page
2. Payment modal opens with Stripe Elements
3. Create payment intent via API
4. Confirm payment with card details
5. Webhook confirms payment
6. Comic added to user library

---

### 6. Review & Rating System

**Current Implementation:**
- 5-star ratings with written reviews
- Spoiler flagging
- Helpful/unhelpful voting
- Admin moderation workflow
- Approval system before public display

**Model:** `ComicReview`
- Rating (1-5)
- Title (optional)
- Content (required, 10-5000 chars)
- Spoiler flag
- Vote counts (helpful/total)
- Approval status

**This addresses the "Missing Social Proof" pain point - the feature exists but may need better UI prominence.**

---

### 7. Notification System

**Current Implementation:**
- `NewComicReleased` notification class exists
- Checks user preferences before sending
- Queue-based (async) delivery
- Email template in `views/emails/new-comic-notification`
- Admin trigger via `ComicNotificationController`

**Pain Point:** "No Auto-Notifications" - The infrastructure exists but:
- May not be triggered automatically on comic publish
- Requires queue worker running (`php artisan queue:work`)
- User preferences may default to opt-out

---

### 8. Admin Panel (Filament)

**Resources:**
1. **ComicResource** - Full comic CRUD, bulk upload
2. **UserResource** - User management
3. **PaymentResource** - Transaction history
4. **ReviewResource** - Moderation queue
5. **CmsContentResource** - Content management
6. **AnalyticsDashboardResource** - Metrics

**Admin Features:**
- Comic upload with PDF support
- Cover image management
- User administration
- Payment analytics
- Review moderation (approve/reject)
- CMS content versioning
- Notification triggers

---

### 9. Search & Discovery

**Current Implementation:**
- Meilisearch integration via Laravel Scout
- Autocomplete suggestions
- Filters: genre, tags, free, rating, etc.
- Trending algorithm based on views/ratings
- Recommendation engine (personalized)

**Key Files:**
- `app/Services/ComicSearchService.php`
- `app/Http/Controllers/Api/ComicSearchController.php`
- `app/Services/RecommendationService.php`

---

### 10. Gamification System

**Features:**
- Reading streaks (daily tracking)
- Reading goals (pages/time targets)
- Achievement system (unlockable badges)
- Progress visualization

**Models:**
- `UserStreak`
- `UserGoal`
- `Achievement`
- `UserAchievement`

---

### 11. Social Features

**Current Implementation:**
- Social sharing buttons (Facebook, Twitter, WhatsApp, etc.)
- OpenGraph metadata for link previews
- User following system (infrastructure)
- Reading lists (create, share, follow)

**Models:**
- `SocialShare` - Track sharing activity
- `ReadingList` - User-curated lists
- `UserFollow` - Following relationships

---

### 12. File Storage Architecture

**Current Configuration:**
```php
// config/filesystems.php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
's3' => [
    'driver' => 's3',
    // AWS credentials...
]
```

**Pain Point:** "Manual Re-uploads" - Files stored in `storage/app/public/`:
- Symlinked to `public/storage`
- Not persisted across code deployments on ephemeral platforms
- Solution: Move to S3 or external storage

**File Organization:**
- Cover images: `storage/app/public/covers/`
- PDF files: `storage/app/public/comics/` or similar
- Accessed via `/storage/` URL prefix

---

## Pain Points Mapped to Features

| Pain Point | Related Feature | Root Cause |
|------------|-----------------|------------|
| Complicated Access | User Journey | Too many steps: Home → Catalog → Detail → Login → Add → Read |
| Broken Zoom/Scroll | PDF Reader | Complex dynamic padding in `EnhancedPdfReader.tsx` |
| Non-Intuitive Mobile Nav | PDF Reader | Horizontal scrolling paradigm, touch handling complexity |
| Session Errors (419/403) | Authentication | CSRF token expiration, session driver issues |
| Broken Password Reset | Email System | Email configuration, missing queue worker |
| Asset Failures | File Storage | Storage symlink issues, missing files |
| Manual Re-uploads | File Storage | Ephemeral local storage, no cloud persistence |
| Developer Dependency | Hosting | Personal account dependencies |
| Missing Social Proof | Review System | Feature exists but UI not prominent |
| No Auto-Notifications | Notification System | Infrastructure exists but not triggered |

---

## Recommended Architecture for Rebuild

### Simplify User Journey
1. **Homepage** → Show "Read Now" buttons directly on comic cards
2. **Comic Detail** → Single "Read" button (handles login/purchase inline)
3. **Reader** → Full-screen immersive experience

### PDF Reader Improvements
1. Use native browser PDF rendering or simpler library
2. Implement vertical scrolling as default for mobile
3. Remove complex dynamic padding - use CSS containment
4. Separate touch handling from zoom logic

### File Storage
1. Use S3 or DigitalOcean Spaces from day one
2. Implement CDN for PDF delivery
3. Never rely on local filesystem for production assets

### Authentication
1. Increase session lifetime for reading sessions
2. Implement automatic token refresh
3. Use more resilient email provider with guaranteed delivery

### Database Schema (Simplified)
Core tables needed:
- `users` - Basic user data
- `comics` - Comic metadata + file references
- `user_libraries` - User ↔ Comic relationships
- `user_progress` - Reading progress
- `reviews` - Ratings and reviews
- `payments` - Transaction records

---

## File Counts & Complexity

| Category | Count |
|----------|-------|
| Database Models | 25 |
| Database Migrations | 44 |
| API Controllers | 23 |
| Services | 23 |
| React Pages | 20 |
| React Components | 106 |
| Custom Middleware | 10 |

**Note:** This complexity suggests over-engineering. A rebuild should prioritize:
1. Core reading experience
2. Simple catalog/discovery
3. Basic commerce (purchase/subscription)
4. Essential user features (library, progress)

---

## API Endpoints Summary

### Public (No Auth)
- `GET /api/comics` - List comics
- `GET /api/comics/{slug}` - Comic details
- `GET /api/search/comics` - Search
- `POST /api/comics/{slug}/track-view` - Track views

### Protected (Auth Required)
- `GET /api/library` - User's library
- `POST /api/library/comics/{slug}` - Add to library
- `PATCH /api/progress/comics/{slug}` - Update progress
- `POST /api/payments/create-intent` - Start purchase
- `GET/POST /api/reviews/comics/{slug}` - Reviews

### Admin
- `GET /api/admin/reviews/pending` - Moderation queue
- `POST /api/admin/notifications/new-comic` - Send notifications
- `GET /api/admin/analytics/dashboard` - Platform metrics

---

## Summary

BagComics has a feature-rich foundation with:
- Complete comic catalog and management
- PDF-based reader with advanced features
- Full payment integration
- User library and progress tracking
- Review/rating system
- Admin panel

**Critical fixes needed for current app:**
1. Simplify PDF reader zoom/scroll behavior
2. Move file storage to cloud (S3)
3. Fix email delivery for password reset/notifications
4. Increase session stability
5. Reduce steps to start reading

**For a rebuild, prioritize:**
1. Simple, reliable PDF reading experience
2. Cloud-first file storage
3. Streamlined user journey
4. Mobile-first responsive design
5. Robust session/auth handling
