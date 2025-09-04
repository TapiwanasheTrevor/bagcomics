import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                {/* Auth card with proper dark theme support */}
                <div className="bg-card border border-border rounded-lg shadow-lg p-6">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                                <div className="mb-1 flex h-12 w-12 items-center justify-center rounded-md overflow-hidden bg-red-600">
                                    <img 
                                        src="/images/bagcomics.jpeg" 
                                        alt="BAG Comics" 
                                        className="size-12 object-cover rounded-md"
                                    />
                                </div>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-semibold text-card-foreground">{title}</h1>
                                <p className="text-center text-sm text-muted-foreground">{description}</p>
                            </div>
                        </div>
                        <div className="space-y-4">
                            {children}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
