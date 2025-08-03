<?php

namespace Database\Seeders;

use App\Models\Comic;
use Illuminate\Database\Seeder;

class ComicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $comics = [
            [
                'title' => 'Anansi Chronicles: The Digital Edition',
                'slug' => 'anansi-chronicles-digital',
                'author' => 'Kwame Asante',
                'genre' => 'fantasy',
                'tags' => ['mythology', 'african folklore', 'digital comic', 'fantasy'],
                'description' => 'Experience the legendary spider god Anansi in this beautifully crafted digital comic. Follow his adventures through modern Ghana as he weaves ancient wisdom into contemporary challenges.',
                'page_count' => 24,
                'average_rating' => 4.8,
                'total_ratings' => 156,
                'total_readers' => 1247,
                'is_free' => false,
                'price' => 4.99,
            ],
            [
                'title' => 'Lagos 2090: Cyberpunk Chronicles',
                'slug' => 'lagos-2090-cyberpunk',
                'author' => 'Aisha Okafor',
                'genre' => 'sci-fi',
                'tags' => ['cyberpunk', 'futurism', 'action', 'technology'],
                'description' => 'In a cyberpunk Lagos of the future, a young hacker discovers a conspiracy that threatens the megacity. Neon-lit adventures meet African futurism.',
                'page_count' => 32,
                'average_rating' => 4.6,
                'total_ratings' => 89,
                'total_readers' => 892,
                'is_free' => false,
                'price' => 3.49,
            ],
            [
                'title' => 'Ubuntu Tales: Community Stories',
                'slug' => 'ubuntu-tales-community',
                'author' => 'Amara Okafor',
                'genre' => 'slice-of-life',
                'tags' => ['community', 'ubuntu philosophy', 'african stories', 'free'],
                'description' => 'A heartwarming collection of stories celebrating the Ubuntu philosophy - "I am because we are." This free digital comic showcases the power of community.',
                'page_count' => 16,
                'average_rating' => 4.5,
                'total_ratings' => 89,
                'total_readers' => 892,
                'is_free' => true,
                'price' => null,
            ],
            [
                'title' => 'Shaka: The Warrior King',
                'slug' => 'shaka-warrior-king',
                'author' => 'Thabo Mthembu',
                'genre' => 'action',
                'tags' => ['historical', 'warrior', 'zulu', 'biography'],
                'description' => 'The epic tale of Shaka Zulu, the legendary warrior king who united the Zulu nation. Experience the battles, politics, and personal struggles of one of Africa\'s greatest leaders.',
                'page_count' => 40,
                'average_rating' => 4.9,
                'total_ratings' => 234,
                'total_readers' => 1567,
                'is_free' => false,
                'price' => 5.99,
            ],
            [
                'title' => 'Ubuntu Squad: Heroes United',
                'slug' => 'ubuntu-squad-heroes',
                'author' => 'Thabo Mthembu',
                'genre' => 'superhero',
                'tags' => ['superhero', 'action', 'ubuntu', 'team'],
                'description' => 'A team of African superheroes with powers rooted in Ubuntu philosophy must protect Johannesburg from interdimensional threats.',
                'page_count' => 28,
                'average_rating' => 4.5,
                'total_ratings' => 167,
                'total_readers' => 1123,
                'is_free' => false,
                'price' => 1.99,
            ],
            [
                'title' => 'Mami Wata: Ocean Goddess',
                'slug' => 'mami-wata-ocean-goddess',
                'author' => 'Kemi Adebayo',
                'genre' => 'fantasy',
                'tags' => ['mythology', 'ocean', 'goddess', 'west africa'],
                'description' => 'Dive into the mystical world of Mami Wata, the powerful water spirit. Follow her journey as she protects the coastal communities of West Africa.',
                'page_count' => 22,
                'average_rating' => 4.7,
                'total_ratings' => 98,
                'total_readers' => 756,
                'is_free' => false,
                'price' => 3.99,
            ],
            [
                'title' => 'Baobab Chronicles: Ancient Wisdom',
                'slug' => 'baobab-chronicles-wisdom',
                'author' => 'Fatima Al-Rashid',
                'genre' => 'fantasy',
                'tags' => ['wisdom', 'ancient', 'baobab', 'spiritual'],
                'description' => 'Under the ancient baobab tree, stories of wisdom and magic unfold. Each tale teaches valuable lessons passed down through generations.',
                'page_count' => 18,
                'average_rating' => 4.4,
                'total_ratings' => 67,
                'total_readers' => 543,
                'is_free' => true,
                'price' => null,
            ],
            [
                'title' => 'Kente Warriors: Threads of Power',
                'slug' => 'kente-warriors-threads',
                'author' => 'Kofi Asante',
                'genre' => 'action',
                'tags' => ['kente', 'warriors', 'ghana', 'power'],
                'description' => 'Warriors who draw their power from the sacred Kente cloth must defend their kingdom from dark forces threatening to destroy their heritage.',
                'page_count' => 35,
                'average_rating' => 4.6,
                'total_ratings' => 145,
                'total_readers' => 987,
                'is_free' => false,
                'price' => 4.49,
            ],
        ];

        foreach ($comics as $comicData) {
            Comic::updateOrCreate(
                ['slug' => $comicData['slug']],
                array_merge($comicData, [
                    'language' => 'en',
                    'publication_year' => 2024,
                    'publisher' => 'African Comics Collective',
                    'pdf_file_path' => 'sample-comic.pdf',
                    'pdf_file_name' => $comicData['slug'] . '.pdf',
                    'pdf_file_size' => file_exists(public_path('sample-comic.pdf')) ? filesize(public_path('sample-comic.pdf')) : 1024000,
                    'pdf_mime_type' => 'application/pdf',
                    'is_pdf_comic' => true,
                    'pdf_metadata' => [
                        'version' => '1.0',
                        'created_with' => 'Adobe InDesign',
                        'optimized_for' => 'web',
                        'interactive_elements' => true,
                        'bookmarks' => true,
                    ],
                    'cover_image_path' => null,
                    'preview_pages' => [1, 2, 3],
                    'has_mature_content' => false,
                    'content_warnings' => null,
                    'is_visible' => true,
                    'published_at' => now()->subDays(rand(1, 60)),
                ])
            );
        }

        $this->command->info('Comics seeded successfully!');
        $this->command->info('Created ' . count($comics) . ' comics.');
    }
}
