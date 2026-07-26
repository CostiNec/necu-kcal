import CssBaseline from '@mui/material/CssBaseline';
import {
    ThemeProvider as MuiThemeProvider,
    type PaletteMode,
} from '@mui/material/styles';
import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
    type PropsWithChildren,
} from 'react';
import { createKcalTheme } from '@/theme';

type ColorModeContextValue = {
    mode: PaletteMode;
    toggleMode: () => void;
};

const ColorModeContext = createContext<ColorModeContextValue | null>(null);

function initialMode(): PaletteMode {
    if (typeof window === 'undefined') return 'light';

    const saved = window.localStorage.getItem('kcal-color-mode');
    if (saved === 'light' || saved === 'dark') return saved;

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

export function ThemeProvider({ children }: PropsWithChildren) {
    const [mode, setMode] = useState<PaletteMode>(initialMode);
    const theme = useMemo(() => createKcalTheme(mode), [mode]);
    const colorMode = useMemo(
        () => ({
            mode,
            toggleMode: () =>
                setMode((current) => (current === 'light' ? 'dark' : 'light')),
        }),
        [mode],
    );

    useEffect(() => {
        window.localStorage.setItem('kcal-color-mode', mode);
        document.documentElement.style.colorScheme = mode;
        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', mode === 'dark' ? '#141A21' : '#00A76F');
    }, [mode]);

    return (
        <ColorModeContext.Provider value={colorMode}>
            <MuiThemeProvider theme={theme}>
                <CssBaseline />
                {children}
            </MuiThemeProvider>
        </ColorModeContext.Provider>
    );
}

export function useColorMode() {
    const context = useContext(ColorModeContext);

    if (!context) {
        throw new Error('useColorMode must be used inside ThemeProvider');
    }

    return context;
}
