import { Head, router } from '@inertiajs/react';
import CheckRounded from '@mui/icons-material/CheckRounded';
import NotificationsNoneRounded from '@mui/icons-material/NotificationsNoneRounded';
import {
    Avatar,
    Box,
    Button,
    Card,
    CardContent,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { RouterLink } from '@/components/router-link';
import { AppLayout } from '@/layouts/app-layout';

type Notification = {
    id: string;
    event: 'friend_request_received' | 'friend_request_accepted' | string;
    actor_name: string;
    actor_username: string;
    friendship_id: number | null;
    actionable: boolean;
    read_at: string | null;
    created_at: string;
};

export default function NotificationsIndex({
    notifications,
}: {
    notifications: Notification[];
}) {
    const { t } = useTranslation();

    return (
        <AppLayout
            title={t('notifications.title')}
            subtitle={t('notifications.description')}
        >
            <Head title={t('notifications.title')} />
            {notifications.length === 0 ? (
                <Paper
                    variant="outlined"
                    sx={{ p: 4, textAlign: 'center', borderStyle: 'dashed' }}
                >
                    <NotificationsNoneRounded
                        color="disabled"
                        sx={{ fontSize: 48 }}
                    />
                    <Typography variant="h6" sx={{ mt: 1 }}>
                        {t('notifications.empty')}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {t('notifications.empty_description')}
                    </Typography>
                </Paper>
            ) : (
                <Stack spacing={2}>
                    {notifications.map((notification) => (
                        <Card
                            key={notification.id}
                            variant={
                                notification.read_at ? 'outlined' : undefined
                            }
                        >
                            <CardContent>
                                <Stack
                                    direction="row"
                                    alignItems="center"
                                    spacing={2}
                                >
                                    <Avatar
                                        sx={{
                                            color: 'primary.main',
                                            bgcolor: 'primary.lighter',
                                        }}
                                    >
                                        {notification.actor_name[0]?.toUpperCase()}
                                    </Avatar>
                                    <Box sx={{ minWidth: 0, flex: 1 }}>
                                        <RouterLink
                                            href={`/users/${notification.actor_username}`}
                                            style={{
                                                color: 'inherit',
                                                textDecoration: 'none',
                                            }}
                                        >
                                            <Typography variant="subtitle1">
                                                {notification.event ===
                                                'friend_request_received'
                                                    ? t(
                                                          'notifications.friend_request',
                                                          {
                                                              name: notification.actor_name,
                                                          },
                                                      )
                                                    : t(
                                                          'notifications.friend_accepted',
                                                          {
                                                              name: notification.actor_name,
                                                          },
                                                      )}
                                            </Typography>
                                        </RouterLink>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            @{notification.actor_username} ·{' '}
                                            {dayjs(
                                                notification.created_at,
                                            ).format('D MMM, HH:mm')}
                                        </Typography>
                                    </Box>
                                    {notification.event ===
                                        'friend_request_received' &&
                                        notification.friendship_id &&
                                        notification.actionable && (
                                            <Stack direction="row" spacing={1}>
                                                <Button
                                                    variant="contained"
                                                    startIcon={<CheckRounded />}
                                                    onClick={() =>
                                                        router.put(
                                                            `/friendships/${notification.friendship_id}/accept`,
                                                        )
                                                    }
                                                >
                                                    {t('social.accept')}
                                                </Button>
                                                <Button
                                                    color="inherit"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/friendships/${notification.friendship_id}`,
                                                        )
                                                    }
                                                >
                                                    {t('social.decline')}
                                                </Button>
                                            </Stack>
                                        )}
                                </Stack>
                            </CardContent>
                        </Card>
                    ))}
                </Stack>
            )}
        </AppLayout>
    );
}
