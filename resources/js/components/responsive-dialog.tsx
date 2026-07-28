import Dialog, { type DialogProps } from '@mui/material/Dialog';
import SwipeableDrawer from '@mui/material/SwipeableDrawer';
import Box from '@mui/material/Box';
import useMediaQuery from '@mui/material/useMediaQuery';
import { useTheme } from '@mui/material/styles';

type ResponsiveDialogProps = DialogProps & {
    mobileActionsDirection?: 'row' | 'column';
};

export function ResponsiveDialog({
    children,
    mobileActionsDirection = 'row',
    onClose,
    open,
    sx,
    ...dialogProps
}: ResponsiveDialogProps) {
    const theme = useTheme();
    const desktop = useMediaQuery(theme.breakpoints.up('md'));

    if (desktop) {
        return (
            <Dialog
                {...dialogProps}
                open={open}
                onClose={onClose}
                sx={sx}
            >
                {children}
            </Dialog>
        );
    }

    return (
        <SwipeableDrawer
            anchor="bottom"
            open={open}
            onOpen={() => undefined}
            onClose={(event) => onClose?.(event, 'backdropClick')}
            disableSwipeToOpen
            hysteresis={0.3}
            sx={sx}
            slotProps={{
                paper: {
                    sx: {
                        maxHeight: '92dvh',
                        overflow: 'hidden',
                        borderTopLeftRadius: 24,
                        borderTopRightRadius: 24,
                        backgroundImage: 'none',
                        '& > form': {
                            display: 'flex',
                            minHeight: 0,
                            flexDirection: 'column',
                        },
                        '& .MuiDialogContent-root': {
                            overflowY: 'auto',
                        },
                        '& .MuiDialogActions-root': {
                            gap: 1,
                            px: 3,
                            pb: 'max(24px, env(safe-area-inset-bottom))',
                            ...(mobileActionsDirection === 'column'
                                ? {
                                      alignItems: 'stretch',
                                      flexDirection: 'column',
                                      '& > button': {
                                          width: 1,
                                          minHeight: 48,
                                          ml: '0 !important',
                                      },
                                  }
                                : {}),
                        },
                    },
                },
            }}
        >
            <Box
                aria-hidden
                sx={{
                    width: 44,
                    height: 5,
                    flexShrink: 0,
                    mx: 'auto',
                    mt: 1.25,
                    mb: 0.25,
                    borderRadius: 999,
                    bgcolor: 'divider',
                    cursor: 'grab',
                }}
            />
            {children}
        </SwipeableDrawer>
    );
}
