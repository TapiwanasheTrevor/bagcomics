<?php

namespace App\Console\Commands;

use App\Models\Comic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckStorageCommand extends Command
{
    protected $signature = 'storage:check {--fix : Fix missing symlinks and directories}';
    protected $description = 'Check storage configuration and file integrity';

    public function handle()
    {
        $this->info('Checking storage configuration...');
        
        // Check storage directories
        $this->checkDirectory('storage/app/public', 'Storage Public Directory');
        $this->checkDirectory('storage/app/public/comics', 'Comics Directory');
        $this->checkDirectory('storage/app/public/covers', 'Covers Directory');
        
        // Check storage symlink
        $symlinkPath = public_path('storage');
        if (is_link($symlinkPath)) {
            $target = readlink($symlinkPath);
            $this->info("✅ Storage symlink exists: {$symlinkPath} -> {$target}");
        } else {
            $this->error("❌ Storage symlink missing: {$symlinkPath}");
            if ($this->option('fix')) {
                $this->call('storage:link');
                $this->info("✅ Created storage symlink");
            }
        }
        
        // Check comic PDFs
        $this->info("\nChecking comic PDF files...");
        $comics = Comic::whereNotNull('pdf_file_path')->get();
        
        if ($comics->isEmpty()) {
            $this->warn('No comics with PDF files found in database');
            return;
        }
        
        $missing = [];
        $found = [];
        
        foreach ($comics as $comic) {
            $filePath = storage_path('app/public/' . $comic->pdf_file_path);
            if (file_exists($filePath)) {
                $size = filesize($filePath);
                $this->info("✅ {$comic->title} ({$comic->slug}): {$comic->pdf_file_path} ({$size} bytes)");
                $found[] = $comic;
            } else {
                $this->error("❌ {$comic->title} ({$comic->slug}): {$comic->pdf_file_path} - FILE MISSING");
                $missing[] = $comic;
            }
        }
        
        $this->info("\nSummary:");
        $this->info("Found: " . count($found) . " PDFs");
        if (!empty($missing)) {
            $this->error("Missing: " . count($missing) . " PDFs");
            $this->error("Missing files need to be re-uploaded or comic records need to be updated");
        }
        
        // List actual files in comics directory
        $comicsDir = storage_path('app/public/comics');
        if (is_dir($comicsDir)) {
            $files = array_diff(scandir($comicsDir), ['.', '..']);
            $this->info("\nActual files in comics directory:");
            foreach ($files as $file) {
                $size = filesize($comicsDir . '/' . $file);
                $this->info("  - {$file} ({$size} bytes)");
            }
        }
    }
    
    private function checkDirectory($path, $name)
    {
        $fullPath = base_path($path);
        if (is_dir($fullPath)) {
            $permissions = substr(sprintf('%o', fileperms($fullPath)), -4);
            $this->info("✅ {$name}: {$fullPath} (permissions: {$permissions})");
        } else {
            $this->error("❌ {$name}: {$fullPath} - DIRECTORY MISSING");
            if ($this->option('fix')) {
                mkdir($fullPath, 0755, true);
                $this->info("✅ Created directory: {$fullPath}");
            }
        }
    }
}