<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-2">Analytics Dashboard</h2>
            <p class="text-blue-800">
                Comprehensive analytics and insights for your comic platform. Monitor user engagement, 
                revenue metrics, content performance, and growth trends in real-time.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Key Performance Indicators</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ number_format(\App\Models\Payment::where('status', 'completed')->sum('amount'), 2) }}</div>
                            <div class="text-sm text-gray-600">Total Revenue ($)</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format(\App\Models\User::count()) }}</div>
                            <div class="text-sm text-gray-600">Total Users</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ number_format(\App\Models\Comic::sum('view_count')) }}</div>
                            <div class="text-sm text-gray-600">Total Views</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">{{ number_format(\App\Models\ComicReview::avg('rating'), 1) }}</div>
                            <div class="text-sm text-gray-600">Avg Rating</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ \App\Filament\Resources\ComicResource::getUrl('index') }}" 
                           class="block w-full text-left bg-blue-50 hover:bg-blue-100 p-3 rounded-lg transition-colors">
                            <div class="font-medium text-blue-900">Manage Comics</div>
                            <div class="text-sm text-blue-700">View and edit comic library</div>
                        </a>
                        <a href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}" 
                           class="block w-full text-left bg-green-50 hover:bg-green-100 p-3 rounded-lg transition-colors">
                            <div class="font-medium text-green-900">Manage Users</div>
                            <div class="text-sm text-green-700">View user analytics</div>
                        </a>
                        <a href="{{ \App\Filament\Resources\ReviewResource::getUrl('index') }}" 
                           class="block w-full text-left bg-orange-50 hover:bg-orange-100 p-3 rounded-lg transition-colors">
                            <div class="font-medium text-orange-900">Content Moderation</div>
                            <div class="text-sm text-orange-700">Review flagged content</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>