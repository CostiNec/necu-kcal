export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
};

let initialized = false;
let installPrompt: BeforeInstallPromptEvent | null = null;
const listeners = new Set<() => void>();

const notify = () => listeners.forEach((listener) => listener());

export function initializePwaInstallPrompt() {
    if (initialized || typeof window === 'undefined') return;

    initialized = true;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event as BeforeInstallPromptEvent;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        notify();
    });
}

export const subscribeToPwaInstallPrompt = (listener: () => void) => {
    listeners.add(listener);

    return () => listeners.delete(listener);
};

export const getPwaInstallPrompt = () => installPrompt;

export async function requestPwaInstall() {
    const prompt = installPrompt;

    if (!prompt) return;

    try {
        await prompt.prompt();
        await prompt.userChoice;
    } finally {
        installPrompt = null;
        notify();
    }
}
