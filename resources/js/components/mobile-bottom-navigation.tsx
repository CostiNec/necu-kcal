import BottomNavigation from '@mui/material/BottomNavigation';
import BottomNavigationAction from '@mui/material/BottomNavigationAction';
import Paper from '@mui/material/Paper';
import type { SvgIconProps } from '@mui/material/SvgIcon';
import { alpha } from '@mui/material/styles';
import type { ComponentType } from 'react';

type NavigationItem = {
    href: string;
    label: string;
    icon: ComponentType<SvgIconProps>;
};

export function MobileBottomNavigation({
    ariaLabel,
    items,
    value,
    onNavigate,
}: {
    ariaLabel: string;
    items: NavigationItem[];
    value: number;
    onNavigate: (href: string) => void;
}) {
    return (
        <Paper
            component="nav"
            aria-label={ariaLabel}
            variant="outlined"
            sx={(theme) => {
                const dark = theme.palette.mode === 'dark';

                return {
                    position: 'fixed',
                    zIndex: theme.zIndex.appBar,
                    right: 12,
                    bottom: 'max(12px, env(safe-area-inset-bottom))',
                    left: 12,
                    display: { lg: 'none' },
                    overflow: 'hidden',
                    p: 0.5,
                    borderRadius: '16px',
                    borderColor: alpha(
                        theme.palette.grey[500],
                        dark ? 0.16 : 0.2,
                    ),
                    bgcolor: alpha(
                        theme.palette.background.paper,
                        dark ? 0.88 : 0.82,
                    ),
                    backdropFilter: 'blur(5px)',
                    WebkitBackdropFilter: 'blur(5px)',
                    boxShadow: [
                        `0 12px 32px ${alpha(
                            theme.palette.common.black,
                            dark ? 0.32 : 0.12,
                        )}`,
                        `inset 0 1px 0 ${alpha(
                            theme.palette.common.white,
                            dark ? 0.1 : 0.72,
                        )}`,
                    ].join(', '),
                };
            }}
        >
            <BottomNavigation
                showLabels
                value={value}
                onChange={(_, nextValue: number) => {
                    const item = items[nextValue];

                    if (item) {
                        onNavigate(item.href);
                    }
                }}
                sx={{
                    height: 64,
                    gap: 0.5,
                    bgcolor: 'transparent',
                }}
            >
                {items.map((item) => {
                    const Icon = item.icon;

                    return (
                        <BottomNavigationAction
                            key={item.href}
                            aria-label={item.label}
                            label={item.label}
                            icon={<Icon />}
                            sx={(theme) => {
                                const dark = theme.palette.mode === 'dark';

                                return {
                                    py: 0.75,
                                    borderRadius: '16px',
                                    color: 'text.secondary',
                                    '& .MuiSvgIcon-root': {
                                        fontSize: 24,
                                    },
                                    '&.Mui-selected': {
                                        color: 'primary.main',
                                        bgcolor: alpha(
                                            theme.palette.primary.main,
                                            dark ? 0.14 : 0.1,
                                        ),
                                        boxShadow: `inset 0 1px 0 ${alpha(
                                            theme.palette.common.white,
                                            dark ? 0.1 : 0.56,
                                        )}`,
                                    },
                                    '&:active': {
                                        transform: 'scale(0.98)',
                                    },
                                };
                            }}
                        />
                    );
                })}
            </BottomNavigation>
        </Paper>
    );
}
