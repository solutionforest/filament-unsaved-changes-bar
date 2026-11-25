<?php

namespace Demo\Database\Seeders;

use Demo\App\Models\Post;
use Demo\App\Models\StoreSetting;
use Demo\App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => Hash::make('password')],
        );

        StoreSetting::current();

        // A real file, so the FileUpload field has something to load (and re-hash) on the edit page.
        Storage::disk('public')->put('covers/autumn-roast.png', (string) file_get_contents(__DIR__.'/../../resources/images/cover.png'));

        $posts = [
            ['title' => 'Introducing our autumn roast', 'status' => 'published', 'is_featured' => true, 'tags' => ['coffee', 'seasonal'], 'cover' => 'covers/autumn-roast.png'],
            ['title' => 'How we source our beans', 'status' => 'review', 'tags' => ['sourcing']],
            ['title' => 'Brewing guide: pour-over', 'status' => 'draft', 'tags' => ['guide']],
        ];

        foreach ($posts as $post) {
            Post::query()->updateOrCreate(['title' => $post['title']], $post + [
                'slug' => str($post['title'])->slug()->toString(),
                'published_at' => now()->subDays(3),
                'body' => '<p>'.$post['title'].'. Edit any field to see the unsaved-changes bar.</p>',
                'highlights' => [['text' => 'Single origin'], ['text' => 'Roasted in Hong Kong']],
            ]);
        }
    }
}
