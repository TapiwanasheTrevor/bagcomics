import { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import EnhancedPdfReader from '@/components/EnhancedPdfReader';

interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    cover_image_url?: string;
    page_count?: number;
    pdf_file_path?: string;
    pdf_file_name?: string;
    is_pdf_comic?: boolean;
    pdf_stream_url?: string;
    user_progress?: {
        current_page: number;
        total_pages?: number;
        progress_percentage: number;
        is_completed: boolean;
        is_bookmarked: boolean;
        last_read_at?: string;
    };
}

interface ComicReaderProps {
    comic: Comic;
}

export default function ComicReader({ comic }: ComicReaderProps) {
    const { auth } = usePage<SharedData>().props;

    const handlePageChange = async (page: number) => {
        if (auth.user) {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                await fetch(`/api/progress/comics/${comic.slug}`, {
                    method: 'PATCH',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({
                        current_page: page,
                        total_pages: comic.page_count,
                    }),
                });

                // Add comic to library if reading for first time
                await fetch(`/api/library/comics/${comic.slug}`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({
                        access_type: 'reading',
                        current_page: page,
                    }),
                });
            } catch (error) {
                console.error('Error updating progress:', error);
            }
        }
    };

    const handleClose = () => {
        // Navigate back to comic details page
        window.location.href = `/comics/${comic.slug}`;
    };

    return (
        <>
            <Head title={`Reading: ${comic.title} - BagComics`}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            {comic.is_pdf_comic && (comic.pdf_stream_url || comic.pdf_file_path) && (
                <EnhancedPdfReader
                    fileUrl={comic.pdf_stream_url || `/comics/${comic.slug}/stream`}
                    fileName={comic.pdf_file_name || `${comic.title}.pdf`}
                    downloadUrl={`/comics/${comic.slug}/download`}
                    userHasDownloadAccess={true}
                    comicSlug={comic.slug}
                    initialPage={comic.user_progress?.current_page || 1}
                    onPageChange={handlePageChange}
                    onClose={handleClose}
                />
            )}
        </>
    );
}