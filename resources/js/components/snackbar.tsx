import CheckCircleOutlineRounded from '@mui/icons-material/CheckCircleOutlineRounded';
import ErrorOutlineRounded from '@mui/icons-material/ErrorOutlineRounded';
import InfoOutlined from '@mui/icons-material/InfoOutlined';
import WarningAmberRounded from '@mui/icons-material/WarningAmberRounded';
import Portal from '@mui/material/Portal';
import { styled } from '@mui/material/styles';
import { Toaster, toast } from 'sonner';

const classes = {
    toast: 'kcal-snackbar__toast',
    icon: 'kcal-snackbar__icon',
    content: 'kcal-snackbar__content',
    title: 'kcal-snackbar__title',
    description: 'kcal-snackbar__description',
    closeButton: 'kcal-snackbar__close-button',
    actionButton: 'kcal-snackbar__action-button',
    success: 'kcal-snackbar__success',
    error: 'kcal-snackbar__error',
    info: 'kcal-snackbar__info',
    warning: 'kcal-snackbar__warning',
};

const SnackbarRoot = styled(Toaster, {
    shouldForwardProp: (prop) =>
        prop !== 'theme' && prop !== 'sx' && prop !== 'as',
})(({ theme }) => ({
    top: '16px !important',
    left: '50% !important',
    right: 'auto !important',
    bottom: 'auto !important',
    width: 'min(300px, calc(100vw - 32px))',
    transform: 'translateX(-50%) !important',
    [`& .${classes.toast}`]: {
        gap: 12,
        width: '100%',
        minHeight: 52,
        display: 'flex',
        alignItems: 'center',
        padding: theme.spacing(0.5, 1, 0.5, 0.5),
        color: theme.palette.text.primary,
        border: `1px solid ${theme.palette.divider}`,
        borderRadius: 12,
        backgroundColor: theme.palette.background.paper,
        boxShadow: theme.shadows[8],
    },
    [`& .${classes.icon}`]: {
        width: 44,
        height: 44,
        flexShrink: 0,
        display: 'flex',
        alignItems: 'center',
        alignSelf: 'flex-start',
        justifyContent: 'center',
        borderRadius: 10,
        backgroundColor: 'color-mix(in srgb, currentColor 8%, transparent)',
        '& > svg': {
            width: 23,
            height: 23,
        },
    },
    [`& .${classes.content}`]: {
        gap: 2,
        minWidth: 0,
        display: 'flex',
        flex: '1 1 auto',
        paddingRight: 20,
        flexDirection: 'column',
    },
    [`& .${classes.title}`]: {
        fontSize: theme.typography.pxToRem(13),
        fontWeight: theme.typography.fontWeightMedium,
        lineHeight: 20 / 13,
    },
    [`& .${classes.description}`]: {
        opacity: 0.64,
        fontSize: theme.typography.pxToRem(13),
        lineHeight: 18 / 13,
    },
    [`& .${classes.closeButton}, & .${classes.actionButton}`]: {
        color: 'inherit',
        cursor: 'pointer',
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        border: `1px solid color-mix(in srgb, currentColor 16%, transparent)`,
        backgroundColor: 'transparent',
        transition: theme.transitions.create([
            'background-color',
            'border-color',
        ]),
        '&:hover': {
            borderColor:
                'color-mix(in srgb, currentColor 24%, transparent)',
            backgroundColor:
                'color-mix(in srgb, currentColor 8%, transparent)',
        },
    },
    [`& .${classes.closeButton}`]: {
        top: 6,
        right: 6,
        width: 20,
        height: 20,
        padding: 0,
        position: 'absolute',
        borderRadius: '50%',
        '& > svg': {
            width: 14,
            height: 14,
            opacity: 0.8,
        },
    },
    [`& .${classes.actionButton}`]: {
        padding: theme.spacing(0.25, 1),
        borderRadius: 6,
        fontSize: theme.typography.pxToRem(13),
        fontWeight: theme.typography.fontWeightMedium,
    },
    [`& .${classes.success} .${classes.icon}`]: {
        color: theme.palette.success.main,
    },
    [`& .${classes.error} .${classes.icon}`]: {
        color: theme.palette.error.main,
    },
    [`& .${classes.info} .${classes.icon}`]: {
        color: theme.palette.info.main,
    },
    [`& .${classes.warning} .${classes.icon}`]: {
        color: theme.palette.warning.main,
    },
}));

export function Snackbar() {
    return (
        <Portal>
            <SnackbarRoot
                expand
                closeButton
                gap={12}
                offset={16}
                duration={4000}
                visibleToasts={4}
                position="top-center"
                style={{
                    top: 16,
                    left: '50%',
                    right: 'auto',
                    bottom: 'auto',
                    width: 'min(300px, calc(100vw - 32px))',
                    transform: 'translateX(-50%)',
                }}
                toastOptions={{
                    unstyled: true,
                    classNames: {
                        toast: classes.toast,
                        icon: classes.icon,
                        content: classes.content,
                        title: classes.title,
                        description: classes.description,
                        closeButton: classes.closeButton,
                        actionButton: classes.actionButton,
                        success: classes.success,
                        error: classes.error,
                        info: classes.info,
                        warning: classes.warning,
                    },
                }}
                icons={{
                    success: <CheckCircleOutlineRounded />,
                    error: <ErrorOutlineRounded />,
                    info: <InfoOutlined />,
                    warning: <WarningAmberRounded />,
                }}
            />
        </Portal>
    );
}

export { toast };
