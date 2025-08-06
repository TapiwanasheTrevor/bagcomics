<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\ComicBookmark;
use App\Models\UserComicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ComicBookmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['page_count' => 100]);
    }

    public function test_create_bookmark_creates_new_bookmark()
    {
        $bookmark = ComicBookmark::createBookmark($this->user, $this->comic, 25, 'Great scene!');

        $this->assertInstanceOf(ComicBookmark::class, $bookmark);
        $this->assertEquals($this->user->id, $bookmark->user_id);
        $this->assertEquals($this->comic->id, $bookmark->comic_id);
        $this->assertEquals(25, $bookmark->page_number);
        $this->assertEquals('Great scene!', $bookmark->note);
    }

    public function test_update_note_updates_bookmark_note()
    {
        $bookmark = ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
            'note' => 'Original note',
        ]);

        $bookmark->updateNote('Updated note');

        $this->assertEquals('Updated note', $bookmark->fresh()->note);
    }

    public function test_get_bookmarks_for_user_comic_returns_ordered_bookmarks()
    {
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 50,
        ]);
        
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);
        
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 75,
        ]);

        $bookmarks = ComicBookmark::getBookmarksForUserComic($this->user, $this->comic);

        $this->assertCount(3, $bookmarks);
        $this->assertEquals(25, $bookmarks[0]->page_number);
        $this->assertEquals(50, $bookmarks[1]->page_number);
        $this->assertEquals(75, $bookmarks[2]->page_number);
    }

    public function test_bookmark_exists_for_page_returns_correct_status()
    {
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);

        $this->assertTrue(ComicBookmark::bookmarkExistsForPage($this->user, $this->comic, 25));
        $this->assertFalse(ComicBookmark::bookmarkExistsForPage($this->user, $this->comic, 30));
    }

    public function test_remove_bookmark_for_page_removes_bookmark()
    {
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);

        $this->assertTrue(ComicBookmark::bookmarkExistsForPage($this->user, $this->comic, 25));
        
        $removed = ComicBookmark::removeBookmarkForPage($this->user, $this->comic, 25);
        
        $this->assertTrue($removed);
        $this->assertFalse(ComicBookmark::bookmarkExistsForPage($this->user, $this->comic, 25));
    }

    public function test_get_bookmark_count_for_comic_returns_correct_count()
    {
        $user2 = User::factory()->create();
        
        ComicBookmark::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'user_id' => $this->user->id,
        ]);
        
        ComicBookmark::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'user_id' => $user2->id,
        ]);

        $count = ComicBookmark::getBookmarkCountForComic($this->comic);
        
        $this->assertEquals(5, $count);
    }

    public function test_get_bookmark_count_for_user_returns_correct_count()
    {
        $comic2 = Comic::factory()->create();
        
        ComicBookmark::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);
        
        ComicBookmark::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
        ]);

        $count = ComicBookmark::getBookmarkCountForUser($this->user);
        
        $this->assertEquals(5, $count);
    }

    public function test_sync_with_progress_updates_progress_data()
    {
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'bookmark_count' => 0,
            'is_bookmarked' => false,
            'last_bookmark_at' => null,
        ]);

        // Create some bookmarks
        ComicBookmark::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $bookmark = ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $bookmark->syncWithProgress();

        $progress->refresh();
        $this->assertEquals(4, $progress->bookmark_count);
        $this->assertTrue($progress->is_bookmarked);
        $this->assertNotNull($progress->last_bookmark_at);
    }

    public function test_scopes_work_correctly()
    {
        $user2 = User::factory()->create();
        $comic2 = Comic::factory()->create();

        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);

        ComicBookmark::factory()->create([
            'user_id' => $user2->id,
            'comic_id' => $this->comic->id,
            'page_number' => 30,
        ]);

        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'page_number' => 35,
        ]);

        // Test forUser scope
        $userBookmarks = ComicBookmark::forUser($this->user)->get();
        $this->assertCount(2, $userBookmarks);

        // Test forComic scope
        $comicBookmarks = ComicBookmark::forComic($this->comic)->get();
        $this->assertCount(2, $comicBookmarks);

        // Test byPage scope
        $pageBookmarks = ComicBookmark::byPage(25)->get();
        $this->assertCount(1, $pageBookmarks);

        // Test combined scopes
        $specificBookmarks = ComicBookmark::forUser($this->user)
            ->forComic($this->comic)
            ->byPage(25)
            ->get();
        $this->assertCount(1, $specificBookmarks);
    }

    public function test_relationships_work_correctly()
    {
        $bookmark = ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $this->assertInstanceOf(User::class, $bookmark->user);
        $this->assertInstanceOf(Comic::class, $bookmark->comic);
        $this->assertEquals($this->user->id, $bookmark->user->id);
        $this->assertEquals($this->comic->id, $bookmark->comic->id);
    }
}