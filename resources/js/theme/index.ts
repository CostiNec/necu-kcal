import { alpha, createTheme, type PaletteMode } from '@mui/material/styles';
import type {} from '@mui/x-date-pickers/themeAugmentation';

// Adapted for Kcal from the project's licensed Minimal UI v7 theme.
const grey = {
    50: '#FCFDFD',
    100: '#F9FAFB',
    200: '#F4F6F8',
    300: '#DFE3E8',
    400: '#C4CDD5',
    500: '#919EAB',
    600: '#637381',
    700: '#454F5B',
    800: '#1C252E',
    900: '#141A21',
};

const statusColors = {
    success: {
        lighter: '#D3FCD2',
        light: '#77ED8B',
        main: '#22C55E',
        dark: '#118D57',
        darker: '#065E49',
    },
    warning: {
        lighter: '#FFF5CC',
        light: '#FFD666',
        main: '#FFAB00',
        dark: '#B76E00',
        darker: '#7A4100',
    },
    error: {
        lighter: '#FFE9D5',
        light: '#FFAC82',
        main: '#FF5630',
        dark: '#B71D18',
        darker: '#7A0916',
    },
    info: {
        lighter: '#CAFDF5',
        light: '#61F3F3',
        main: '#00B8D9',
        dark: '#006C9C',
        darker: '#003768',
    },
};

const buttonHeight = 40;

export function createKcalTheme(mode: PaletteMode) {
    const dark = mode === 'dark';

    return createTheme({
        cssVariables: true,
        palette: {
            mode,
            primary: dark
                ? {
                      lighter: 'rgba(0, 167, 111, 0.16)',
                      light: '#5BE49B',
                      main: '#00A76F',
                      dark: '#007867',
                      darker: '#004B50',
                      contrastText: '#FFFFFF',
                  }
                : {
                      lighter: '#C8FAD6',
                      light: '#5BE49B',
                      main: '#00A76F',
                      dark: '#007867',
                      darker: '#004B50',
                      contrastText: '#FFFFFF',
                  },
            secondary: {
                light: '#C684FF',
                main: dark ? '#C684FF' : '#8E33FF',
                dark: dark ? '#EBD6FF' : '#5119B7',
                contrastText: dark ? grey[900] : '#FFFFFF',
            },
            ...statusColors,
            success: dark
                ? {
                      ...statusColors.success,
                      lighter: 'rgba(34, 197, 94, 0.16)',
                      dark: '#77ED8B',
                  }
                : statusColors.success,
            warning: dark
                ? {
                      ...statusColors.warning,
                      lighter: 'rgba(255, 171, 0, 0.16)',
                      dark: '#FFD666',
                  }
                : statusColors.warning,
            error: dark
                ? {
                      ...statusColors.error,
                      lighter: 'rgba(255, 86, 48, 0.16)',
                      dark: '#FFAC82',
                  }
                : statusColors.error,
            info: dark
                ? {
                      ...statusColors.info,
                      lighter: 'rgba(0, 184, 217, 0.16)',
                      dark: '#61F3F3',
                  }
                : statusColors.info,
            grey,
            text: dark
                ? {
                      primary: grey[200],
                      secondary: grey[500],
                      disabled: grey[600],
                  }
                : {
                      primary: grey[800],
                      secondary: grey[600],
                      disabled: grey[500],
                  },
            background: dark
                ? { default: '#141A21', paper: '#1C252E' }
                : { default: '#F4F6F8', paper: '#FFFFFF' },
            divider: alpha(grey[500], dark ? 0.26 : 0.2),
        },
        shape: { borderRadius: 8 },
        typography: {
            fontFamily: '"Public Sans Variable", "Public Sans", Arial, sans-serif',
            fontWeightRegular: 400,
            fontWeightMedium: 600,
            fontWeightBold: 700,
            h1: { fontWeight: 800, letterSpacing: '-0.04em' },
            h2: { fontWeight: 800, letterSpacing: '-0.035em' },
            h3: { fontWeight: 700, letterSpacing: '-0.025em' },
            h4: { fontWeight: 700, letterSpacing: '-0.02em' },
            h5: { fontWeight: 700 },
            h6: { fontWeight: 600 },
            subtitle1: { fontWeight: 600 },
            subtitle2: { fontWeight: 600 },
            button: { fontWeight: 700, textTransform: 'none' },
        },
        components: {
            MuiCssBaseline: {
                styleOverrides: {
                    html: { scrollBehavior: 'smooth' },
                    body: {
                        transition:
                            'background-color 220ms ease, color 220ms ease',
                    },
                    '*': { boxSizing: 'border-box' },
                    '::selection': {
                        backgroundColor: alpha('#00A76F', 0.22),
                    },
                    '@keyframes kcal-card-enter': {
                        from: { opacity: 0, transform: 'translateY(8px)' },
                        to: { opacity: 1, transform: 'translateY(0)' },
                    },
                    '@media (prefers-reduced-motion: reduce)': {
                        '*': {
                            animationDuration: '0.01ms !important',
                            transitionDuration: '0.01ms !important',
                        },
                    },
                },
            },
            MuiButtonBase: {
                defaultProps: { disableRipple: false },
            },
            MuiButton: {
                defaultProps: { disableElevation: true },
                styleOverrides: {
                    root: ({ theme, ownerState }) => {
                        const softPalette =
                            ownerState.color === 'error'
                                ? theme.palette.error
                                : theme.palette.primary;

                        return {
                            borderRadius: 8,
                            height: buttonHeight,
                            padding: '6px 12px',
                            transition:
                                'transform 160ms ease, box-shadow 160ms ease, background-color 160ms ease',
                            '&:hover': { transform: 'translateY(-1px)' },
                            '&:active': { transform: 'translateY(0)' },
                            ...(ownerState.variant === 'outlined' && {
                                borderColor:
                                    'color-mix(in srgb, currentColor 36%, transparent)',
                                '&:hover': {
                                    transform: 'translateY(-1px)',
                                    borderColor: 'currentColor',
                                    boxShadow:
                                        '0 0 0 0.75px currentColor',
                                    backgroundColor:
                                        'color-mix(in srgb, currentColor 8%, transparent)',
                                },
                            }),
                            ...(ownerState.variant === 'text' && {
                                '&:hover': {
                                    transform: 'translateY(-1px)',
                                    backgroundColor:
                                        'color-mix(in srgb, currentColor 8%, transparent)',
                                },
                            }),
                            ...(ownerState.variant === 'soft' && {
                                color: dark
                                    ? softPalette.light
                                    : softPalette.dark,
                                backgroundColor: alpha(
                                    softPalette.main,
                                    dark ? 0.18 : 0.12,
                                ),
                                boxShadow: 'none',
                                '&:hover': {
                                    transform: 'translateY(-1px)',
                                    backgroundColor: alpha(
                                        softPalette.main,
                                        dark ? 0.26 : 0.2,
                                    ),
                                    boxShadow: 'none',
                                },
                            }),
                        };
                    },
                    sizeSmall: {
                        padding: '4px 8px',
                        fontSize: 13,
                    },
                    sizeLarge: {
                        padding: '8px 16px',
                        fontSize: 15,
                    },
                    contained: {
                        boxShadow: 'none',
                        '&:hover': {
                            boxShadow: 'none',
                        },
                    },
                    containedPrimary: {
                        boxShadow: 'none',
                        '&:hover': { boxShadow: 'none' },
                    },
                },
            },
            MuiIconButton: {
                styleOverrides: {
                    root: {
                        borderRadius: 8,
                        transition:
                            'transform 160ms ease, background-color 160ms ease',
                        '&:hover': { transform: 'scale(1.04)' },
                    },
                },
            },
            MuiCard: {
                styleOverrides: {
                    root: {
                        position: 'relative',
                        zIndex: 0,
                        borderRadius: 16,
                        animation:
                            'kcal-card-enter 420ms cubic-bezier(0.22, 1, 0.36, 1) both',
                        boxShadow: dark
                            ? '0 0 2px rgba(0,0,0,0.32), 0 12px 28px -8px rgba(0,0,0,0.42)'
                            : '0 0 2px rgba(145,158,171,0.2), 0 12px 24px -4px rgba(145,158,171,0.12)',
                    },
                },
            },
            MuiCardHeader: {
                defaultProps: {
                    titleTypographyProps: { variant: 'h6' },
                    subheaderTypographyProps: { variant: 'body2', mt: 0.5 },
                },
                styleOverrides: {
                    root: { padding: 16, paddingBottom: 0 },
                },
            },
            MuiCardContent: {
                styleOverrides: {
                    root: {
                        padding: 16,
                        '&:last-child': { paddingBottom: 16 },
                    },
                },
            },
            MuiContainer: {
                styleOverrides: {
                    root: {
                        paddingLeft: '16px !important',
                        paddingRight: '16px !important',
                    },
                },
            },
            MuiPaper: {
                styleOverrides: {
                    rounded: { borderRadius: 16 },
                },
            },
            MuiPickerPopper: {
                styleOverrides: {
                    paper: {
                        marginTop: 8,
                        overflow: 'hidden',
                        border: `1px solid ${alpha(grey[500], dark ? 0.3 : 0.2)}`,
                        borderRadius: 16,
                        backgroundImage: 'none',
                        boxShadow: dark
                            ? '0 20px 40px rgba(0,0,0,0.42)'
                            : '0 20px 40px rgba(28,37,46,0.16)',
                    },
                },
            },
            MuiPickersLayout: {
                styleOverrides: {
                    root: {
                        backgroundColor: dark ? grey[800] : '#FFFFFF',
                    },
                    actionBar: {
                        padding: '8px 12px 12px',
                    },
                },
            },
            MuiPickersCalendarHeader: {
                styleOverrides: {
                    root: {
                        marginTop: 12,
                        marginBottom: 4,
                        paddingLeft: 20,
                        paddingRight: 12,
                    },
                    label: {
                        fontWeight: 700,
                    },
                },
            },
            MuiDayCalendar: {
                styleOverrides: {
                    weekDayLabel: {
                        color: dark ? grey[500] : grey[600],
                        fontSize: 12,
                        fontWeight: 700,
                    },
                },
            },
            MuiPickerDay: {
                styleOverrides: {
                    root: {
                        borderRadius: 8,
                        fontWeight: 600,
                        '&:hover': {
                            backgroundColor: alpha(
                                dark ? '#5BE49B' : '#00A76F',
                                dark ? 0.16 : 0.1,
                            ),
                        },
                        '&.Mui-selected': {
                            color: '#FFFFFF',
                            backgroundColor: dark ? '#5BE49B' : '#00A76F',
                            boxShadow: dark
                                ? '0 4px 10px rgba(91,228,155,0.2)'
                                : '0 4px 10px rgba(0,167,111,0.24)',
                            '&:hover, &:focus': {
                                backgroundColor: dark ? '#5BE49B' : '#00A76F',
                            },
                        },
                        '&.MuiPickersDay-today': {
                            borderColor: alpha(
                                dark ? '#5BE49B' : '#00A76F',
                                0.5,
                            ),
                        },
                    },
                },
            },
            MuiMenu: {
                defaultProps: {
                    elevation: 0,
                },
                styleOverrides: {
                    paper: {
                        marginTop: 8,
                        border: `1px solid ${alpha(grey[500], dark ? 0.3 : 0.2)}`,
                        boxShadow: dark
                            ? '0 12px 30px rgba(0,0,0,0.34)'
                            : '0 12px 30px rgba(28,37,46,0.14)',
                    },
                    list: { padding: 6 },
                },
            },
            MuiMenuItem: {
                styleOverrides: {
                    root: {
                        minHeight: 44,
                        borderRadius: 8,
                        color: dark ? grey[200] : grey[800],
                    },
                },
            },
            MuiTextField: {
                defaultProps: { fullWidth: true },
            },
            MuiOutlinedInput: {
                styleOverrides: {
                    root: {
                        borderRadius: 8,
                        backgroundColor: dark ? grey[800] : '#FFFFFF',
                    },
                    notchedOutline: {
                        borderColor: alpha(grey[500], dark ? 0.34 : 0.24),
                    },
                },
            },
            MuiInputLabel: {
                styleOverrides: {
                    root: { fontWeight: 600 },
                },
            },
            MuiSelect: {
                defaultProps: {
                    MenuProps: {
                        PaperProps: { elevation: 0 },
                    },
                },
            },
            MuiLinearProgress: {
                styleOverrides: {
                    root: { height: 8, borderRadius: 8 },
                    bar: { borderRadius: 8 },
                },
            },
            MuiBottomNavigation: {
                styleOverrides: {
                    root: { height: 64, borderRadius: 16 },
                },
            },
            MuiBottomNavigationAction: {
                styleOverrides: {
                    root: {
                        minWidth: 0,
                        borderRadius: 16,
                        transition:
                            'transform 180ms ease, background-color 180ms ease, color 180ms ease',
                    },
                    label: {
                        fontSize: 11,
                        fontWeight: 600,
                        '&.Mui-selected': { fontSize: 11 },
                    },
                },
            },
            MuiChip: {
                styleOverrides: {
                    root: { fontWeight: 600 },
                },
            },
            MuiAlert: {
                styleOverrides: {
                    root: { borderRadius: 12 },
                },
            },
        },
    });
}

declare module '@mui/material/styles' {
    interface PaletteColor {
        lighter?: string;
        darker?: string;
    }

    interface SimplePaletteColorOptions {
        lighter?: string;
        darker?: string;
    }
}

declare module '@mui/material/Button' {
    interface ButtonPropsVariantOverrides {
        soft: true;
    }
}
