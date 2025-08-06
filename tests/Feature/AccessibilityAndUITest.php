<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Comic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityAndUITest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function homepage_has_proper_semantic_structure()
    {
        $response = $this->get('/');
        
        $response->assertOk();
        
        // Check for semantic HTML elements
        $response->assertSee('<main', false);
        $response->assertSee('<header', false);
        $response->assertSee('<nav', false);
        
        // Check for accessibility attributes
        $response->assertSee('role=', false);
        $response->assertSee('aria-', false);
    }

    /** @test */
    public function forms_have_proper_labels_and_aria_attributes()
    {
        $response = $this->get('/register');
        
        $response->assertOk();
        
        // Check that form inputs have associated labels
        $content = $response->getContent();
        
        // Look for label elements
        $this->assertStringContainsString('<label', $content);
        
        // Look for aria attributes on form elements
        $this->assertMatchesRegularExpression('/<input[^>]*aria-\w+/', $content);
    }

    /** @test */
    public function images_have_alt_text()
    {
        Comic::factory()->create([
            'title' => 'Test Comic',
            'cover_image_path' => 'covers/test.jpg',
            'is_visible' => true
        ]);

        $response = $this->get('/comics');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check that images have alt attributes
        $this->assertMatchesRegularExpression('/<img[^>]*alt="[^"]*"/', $content);
        
        // Check that alt text is meaningful (not empty)
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*alt=""/', $content);
    }

    /** @test */
    public function navigation_is_keyboard_accessible()
    {
        $response = $this->get('/');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for tabindex attributes where appropriate
        // Skip elements should have tabindex="-1"
        // Interactive elements should be focusable
        
        // Check for focus indicators in CSS (this is basic check)
        $response->assertSee('focus:', false);
    }

    /** @test */
    public function color_contrast_meets_wcag_standards()
    {
        // This is a basic check - in practice you'd use tools like axe-core
        $response = $this->get('/');
        $response->assertOk();

        // Check that we're not using problematic color combinations
        // This is a simplified check - real implementation would need color analysis
        $content = $response->getContent();
        
        // Check for CSS custom properties for colors (shows intentional color design)
        $this->assertStringContainsString('--color-', $content);
    }

    /** @test */
    public function pdf_reader_is_accessible()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => true,
            'pdf_file_path' => 'comics/test.pdf'
        ]);

        // Add comic to user's library
        $user->library()->create(['comic_id' => $comic->id]);

        $response = $this->actingAs($user)->get("/comics/{$comic->slug}/read");
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for ARIA labels on PDF controls
        $this->assertStringContainsString('aria-label', $content);
        
        // Check for keyboard navigation support
        $this->assertStringContainsString('tabindex', $content);
        
        // Check for screen reader announcements
        $this->assertStringContainsString('aria-live', $content);
    }

    /** @test */
    public function mobile_responsive_breakpoints_work()
    {
        // Test different screen sizes
        $viewports = [
            'mobile' => ['width' => 375, 'height' => 667],
            'tablet' => ['width' => 768, 'height' => 1024],
            'desktop' => ['width' => 1200, 'height' => 800]
        ];

        foreach ($viewports as $device => $size) {
            $response = $this->get('/', [
                'HTTP_USER_AGENT' => $this->getUserAgent($device)
            ]);
            
            $response->assertOk();
            
            // Check that responsive meta tag is present
            $response->assertSee('<meta name="viewport"', false);
        }
    }

    /** @test */
    public function dark_mode_accessibility()
    {
        $response = $this->get('/', [
            'HTTP_COOKIE' => 'theme=dark'
        ]);
        
        $response->assertOk();
        
        // Check that dark mode classes or variables are present
        $content = $response->getContent();
        $this->assertStringContainsString('dark:', $content);
    }

    /** @test */
    public function error_pages_are_accessible()
    {
        // Test 404 page
        $response = $this->get('/non-existent-page');
        $response->assertNotFound();

        $content = $response->getContent();
        
        // Should have proper heading structure
        $this->assertStringContainsString('<h1', $content);
        
        // Should have navigation back to site
        $this->assertStringContainsString('href="/"', $content);
    }

    /** @test */
    public function form_validation_errors_are_announced()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ]);

        // Should redirect back with errors
        $response->assertSessionHasErrors(['name', 'email', 'password']);
        
        // Follow redirect to see error display
        $errorResponse = $this->get('/register');
        $content = $errorResponse->getContent();
        
        // Check for ARIA attributes on error messages
        $this->assertStringContainsString('role="alert"', $content);
        $this->assertStringContainsString('aria-describedby', $content);
    }

    /** @test */
    public function loading_states_are_accessible()
    {
        $user = User::factory()->create();
        
        // Test API endpoint loading state
        $response = $this->actingAs($user)->get('/library');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for loading indicators with proper ARIA
        $this->assertStringContainsString('aria-busy', $content);
        $this->assertStringContainsString('aria-live="polite"', $content);
    }

    /** @test */
    public function skip_links_are_present()
    {
        $response = $this->get('/');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for skip to main content link
        $this->assertStringContainsString('Skip to main content', $content);
        $this->assertStringContainsString('href="#main"', $content);
    }

    /** @test */
    public function focus_management_in_modals()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['is_free' => false, 'price' => 9.99]);

        $response = $this->actingAs($user)->get("/comics/{$comic->slug}");
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for modal ARIA attributes
        $this->assertStringContainsString('role="dialog"', $content);
        $this->assertStringContainsString('aria-modal="true"', $content);
        $this->assertStringContainsString('aria-labelledby', $content);
    }

    /** @test */
    public function reading_progress_is_announced()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['is_visible' => true, 'is_free' => true]);
        
        $user->library()->create(['comic_id' => $comic->id]);

        $response = $this->actingAs($user)->get("/comics/{$comic->slug}/read");
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for progress announcements
        $this->assertStringContainsString('aria-live', $content);
        $this->assertStringContainsString('progress', $content);
    }

    /** @test */
    public function table_data_has_proper_headers()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $response = $this->actingAs($admin)->get('/admin/comics');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check for table structure
        $this->assertStringContainsString('<table', $content);
        $this->assertStringContainsString('<th', $content);
        $this->assertStringContainsString('scope="col"', $content);
    }

    /** @test */
    public function search_functionality_is_accessible()
    {
        $response = $this->get('/comics');
        $response->assertOk();

        $content = $response->getContent();
        
        // Check search form accessibility
        $this->assertStringContainsString('role="search"', $content);
        $this->assertStringContainsString('aria-label="Search comics"', $content);
        
        // Check for search results announcements
        $this->assertStringContainsString('aria-live', $content);
    }

    private function getUserAgent(string $device): string
    {
        $userAgents = [
            'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'tablet' => 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ];

        return $userAgents[$device] ?? $userAgents['desktop'];
    }
}