import CssBaseline from '@mui/material/CssBaseline';
import {
    ThemeProvider as MuiThemeProvider,
    type PaletteMode,
} from '@mui/material/styles';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { enUS, roRO } from '@mui/x-date-pickers/locales';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import 'dayjs/locale/en-gb';
import 'dayjs/locale/ro';
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
    fontSize: number;
    setFontSize: (fontSize: number) => void;
};

const ColorModeContext = createContext<ColorModeContextValue | null>(null);
const defaultFontSize = 14;
const minFontSize = 12;
const maxFontSize = 18;

function initialMode(): PaletteMode {
    if (typeof window === 'undefined') return 'light';

    const saved = window.localStorage.getItem('kcal-color-mode');
    if (saved === 'light' || saved === 'dark') return saved;

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function initialFontSize() {
    if (typeof window === 'undefined') return defaultFontSize;

    const stored = window.localStorage.getItem('kcal-font-size');

    if (stored === null) return defaultFontSize;

    const saved = Number(stored);

    if (!Number.isFinite(saved)) return defaultFontSize;

    return Math.min(maxFontSize, Math.max(minFontSize, saved));
}

export function ThemeProvider({ children }: PropsWithChildren) {
    const [mode, setMode] = useState<PaletteMode>(initialMode);
    const [fontSize, setFontSize] = useState(initialFontSize);
    const theme = useMemo(
        () => createKcalTheme(mode, fontSize),
        [fontSize, mode],
    );
    const isRomanian = document.documentElement.lang.startsWith('ro');
    const pickerLocale = isRomanian ? roRO : enUS;
    const colorMode = useMemo(
        () => ({
            mode,
            toggleMode: () =>
                setMode((current) => (current === 'light' ? 'dark' : 'light')),
            fontSize,
            setFontSize,
        }),
        [fontSize, mode],
    );

    useEffect(() => {
        window.localStorage.setItem('kcal-color-mode', mode);
        document.documentElement.style.colorScheme = mode;
        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', mode === 'dark' ? '#141A21' : '#00A76F');
    }, [mode]);

    useEffect(() => {
        window.localStorage.setItem('kcal-font-size', String(fontSize));
    }, [fontSize]);

    return (
        <ColorModeContext.Provider value={colorMode}>
            <MuiThemeProvider theme={theme}>
                <LocalizationProvider
                    dateAdapter={AdapterDayjs}
                    adapterLocale={isRomanian ? 'ro' : 'en-gb'}
                    localeText={
                        pickerLocale.components.MuiLocalizationProvider
                            .defaultProps.localeText
                    }
                >
                    <CssBaseline />
                    {children}
                </LocalizationProvider>
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
