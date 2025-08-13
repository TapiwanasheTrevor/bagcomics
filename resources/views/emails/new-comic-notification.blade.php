@component('mail::message')
<div style="text-align: center; margin-bottom: 30px;">
    <img src="{{ asset('/images/bagcomics.jpeg') }}" alt="BAG Comics" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
</div>

# 📚 New Comic Alert!

Hello **{{ $user->name }}**!

We're excited to announce that a fantastic new comic has just been added to the BAG Comics collection!

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;">
    <h2 style="margin: 0 0 10px 0; color: #1a1a1a;">{{ $comic->title }}</h2>
    <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 16px;">
        <strong>by {{ $comic->author ?? 'Unknown Author' }}</strong>
    </p>
    
    @if($comic->description)
    <p style="margin: 10px 0; color: #4b5563; line-height: 1.5;">
        {{ Str::limit($comic->description, 150) }}
    </p>
    @endif
    
    <div style="margin-top: 15px;">
        @if($comic->genre)
        <span style="display: inline-block; background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 16px; font-size: 14px; margin-right: 8px;">
            📖 {{ $comic->genre }}
        </span>
        @endif
        
        @if($comic->is_free)
        <span style="display: inline-block; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 16px; font-size: 14px; font-weight: 600;">
            🎉 FREE
        </span>
        @else
        <span style="display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 16px; font-size: 14px;">
            💰 ${{ number_format($comic->price, 2) }}
        </span>
        @endif
    </div>
</div>

@if($comic->cover_image_path)
<div style="text-align: center; margin: 25px 0;">
    <img src="{{ asset('storage/' . $comic->cover_image_path) }}" alt="{{ $comic->title }} Cover" style="max-width: 200px; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
</div>
@endif

@component('mail::button', ['url' => route('comics.show', $comic->slug), 'color' => 'red'])
🚀 Start Reading Now
@endcomponent

<div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 0; color: #6b7280; font-size: 14px; text-align: center;">
        <strong>Why you'll love this comic:</strong><br>
        Join thousands of readers exploring authentic African stories, rich cultures, and incredible adventures. 
        Every comic on BAG Comics supports African creators and storytellers.
    </p>
</div>

---

<p style="color: #9ca3af; font-size: 14px; line-height: 1.5;">
    You're receiving this email because you've opted in to new comic notifications. 
    You can update your preferences anytime in your 
    <a href="{{ route('dashboard') }}" style="color: #dc2626;">account settings</a>.
</p>

Thanks for being part of our amazing community! 🌟

**The BAG Comics Team**

<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
    <p style="margin: 0; color: #9ca3af; font-size: 12px;">
        © {{ date('Y') }} BAG Comics. African Stories, Boldly Told.
    </p>
</div>
@endcomponent