import { Toaster as Sonner } from 'sonner';

function Toaster() {
    return (
        <Sonner
            position="top-center"
            toastOptions={{
                classNames: {
                    toast: 'glass-panel rounded-2xl text-card-foreground',
                },
            }}
        />
    );
}

export { Toaster };
