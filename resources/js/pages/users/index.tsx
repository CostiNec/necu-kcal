import { Head, router } from '@inertiajs/react';
import CheckRounded from '@mui/icons-material/CheckRounded';
import CloseRounded from '@mui/icons-material/CloseRounded';
import HourglassTopRounded from '@mui/icons-material/HourglassTopRounded';
import PersonAddAltRounded from '@mui/icons-material/PersonAddAltRounded';
import {
    Avatar,
    Badge,
    Box,
    Button,
    Card,
    CardContent,
    Grid,
    IconButton,
    InputAdornment,
    Paper,
    Stack,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { RouterLink } from '@/components/router-link';
import { AppLayout } from '@/layouts/app-layout';

type FriendshipState =
    | 'self'
    | 'none'
    | 'outgoing'
    | 'incoming'
    | 'friends';

type SocialUser = {
    id: number;
    name: string;
    username: string;
    friendship_id: number | null;
    friendship_state: FriendshipState;
};

export default function UsersIndex({
    friends,
    requests,
    searchResult,
    filters,
}: {
    friends: SocialUser[];
    requests: SocialUser[];
    searchResult: SocialUser | null;
    filters: {
        search: string;
        tab: 'friends' | 'requests';
    };
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [tab, setTab] = useState<'friends' | 'requests'>(filters.tab);
    const receivedRequests = useMemo(
        () =>
            requests.filter(
                (request) => request.friendship_state === 'incoming',
            ),
        [requests],
    );
    const sentRequests = useMemo(
        () =>
            requests.filter(
                (request) => request.friendship_state === 'outgoing',
            ),
        [requests],
    );
    const normalizedSearch = search.trim();
    const resultIsCurrent =
        normalizedSearch.toLocaleLowerCase() ===
        filters.search.toLocaleLowerCase();

    useEffect(() => {
        setTab(filters.tab);
    }, [filters.tab]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();

        if (!normalizedSearch) return;

        router.get(
            '/users',
            { search: normalizedSearch, tab },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const clearSearch = () => {
        setSearch('');

        if (filters.search) {
            router.get(
                '/users',
                { tab },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }
    };

    return (
        <AppLayout
            title={t('social.people')}
            subtitle={t('social.people_description')}
        >
            <Head title={t('social.people')} />
            <Stack spacing={3}>
                <Stack
                    component="form"
                    direction={{ xs: 'column', sm: 'row' }}
                    alignItems={{ sm: 'flex-start' }}
                    spacing={1}
                    onSubmit={submitSearch}
                >
                    <TextField
                        fullWidth
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('social.search_username')}
                        error={
                            resultIsCurrent &&
                            Boolean(filters.search) &&
                            !searchResult
                        }
                        helperText={
                            resultIsCurrent &&
                            Boolean(filters.search) &&
                            !searchResult
                                ? t('social.username_not_found', {
                                      username: filters.search,
                                  })
                                : t('social.exact_username_help')
                        }
                        slotProps={{
                            input: {
                                endAdornment:
                                    search || filters.search ? (
                                        <InputAdornment position="end">
                                            <IconButton
                                                size="small"
                                                aria-label={t(
                                                    'social.clear_search',
                                                )}
                                                onClick={clearSearch}
                                            >
                                                <CloseRounded fontSize="small" />
                                            </IconButton>
                                        </InputAdornment>
                                    ) : undefined,
                            },
                        }}
                    />
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={!normalizedSearch}
                        sx={{
                            minWidth: 120,
                            height: 56,
                            width: { xs: 1, sm: 'auto' },
                        }}
                    >
                        {t('common.search')}
                    </Button>
                </Stack>

                {resultIsCurrent && filters.search && searchResult && (
                    <Box>
                        <Typography
                            variant="overline"
                            color="text.secondary"
                        >
                            {t('social.search_result')}
                        </Typography>
                        <UserCard user={searchResult} />
                    </Box>
                )}

                <Paper variant="outlined">
                    <Tabs
                        value={tab}
                        onChange={(_, value: 'friends' | 'requests') =>
                            setTab(value)
                        }
                        variant="fullWidth"
                        aria-label={t('social.people_tabs')}
                        sx={{ borderBottom: 1, borderColor: 'divider' }}
                    >
                        <Tab
                            value="friends"
                            label={t('social.your_friends')}
                        />
                        <Tab
                            value="requests"
                            label={
                                <Badge
                                    color="error"
                                    badgeContent={receivedRequests.length}
                                    max={99}
                                >
                                    <Box component="span" sx={{ px: 1 }}>
                                        {t('social.friend_requests')}
                                    </Box>
                                </Badge>
                            }
                        />
                    </Tabs>

                    <Box sx={{ p: 2 }}>
                        {tab === 'friends' ? (
                            friends.length > 0 ? (
                                <UserGrid users={friends} />
                            ) : (
                                <EmptyState
                                    title={t('social.no_friends')}
                                    description={t(
                                        'social.no_friends_description',
                                    )}
                                />
                            )
                        ) : requests.length > 0 ? (
                            <Stack spacing={3}>
                                <RequestSection
                                    title={t('social.received_requests')}
                                    empty={t(
                                        'social.no_received_requests',
                                    )}
                                    users={receivedRequests}
                                />
                                <RequestSection
                                    title={t('social.sent_requests')}
                                    empty={t('social.no_sent_requests')}
                                    users={sentRequests}
                                />
                            </Stack>
                        ) : (
                            <EmptyState
                                title={t('social.no_requests')}
                                description={t(
                                    'social.no_requests_description',
                                )}
                            />
                        )}
                    </Box>
                </Paper>
            </Stack>
        </AppLayout>
    );
}

function RequestSection({
    title,
    empty,
    users,
}: {
    title: string;
    empty: string;
    users: SocialUser[];
}) {
    return (
        <Stack spacing={1.5}>
            <Typography variant="h6">{title}</Typography>
            {users.length > 0 ? (
                <UserGrid users={users} />
            ) : (
                <Typography variant="body2" color="text.secondary">
                    {empty}
                </Typography>
            )}
        </Stack>
    );
}

function UserGrid({ users }: { users: SocialUser[] }) {
    return (
        <Grid container spacing={2}>
            {users.map((user) => (
                <Grid key={user.id} size={{ xs: 12, md: 6 }}>
                    <UserCard user={user} />
                </Grid>
            ))}
        </Grid>
    );
}

function EmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <Box sx={{ py: 4, textAlign: 'center' }}>
            <Typography variant="h6">{title}</Typography>
            <Typography variant="body2" color="text.secondary">
                {description}
            </Typography>
        </Box>
    );
}

function UserCard({ user }: { user: SocialUser }) {
    return (
        <Card variant="outlined" sx={{ height: 1 }}>
            <CardContent>
                <Stack direction="row" alignItems="center" spacing={2}>
                    <Avatar
                        sx={{
                            color: 'primary.main',
                            bgcolor: 'primary.lighter',
                        }}
                    >
                        {user.name[0]?.toUpperCase()}
                    </Avatar>
                    <Box sx={{ minWidth: 0, flex: 1 }}>
                        <RouterLink
                            href={`/users/${user.username}`}
                            style={{
                                color: 'inherit',
                                textDecoration: 'none',
                            }}
                        >
                            <Typography variant="subtitle1" noWrap>
                                {user.name}
                            </Typography>
                        </RouterLink>
                        <Typography
                            variant="body2"
                            color="text.secondary"
                            noWrap
                        >
                            @{user.username}
                        </Typography>
                    </Box>
                    <FriendshipAction user={user} />
                </Stack>
            </CardContent>
        </Card>
    );
}

function FriendshipAction({ user }: { user: SocialUser }) {
    const { t } = useTranslation();

    if (user.friendship_state === 'self') {
        return (
            <Button
                color="inherit"
                onClick={() => router.visit(`/users/${user.username}`)}
            >
                {t('social.you')}
            </Button>
        );
    }

    if (user.friendship_state === 'friends') {
        return (
            <Button
                color="success"
                variant="soft"
                startIcon={<CheckRounded />}
                onClick={() => router.visit(`/users/${user.username}`)}
            >
                {t('social.friends')}
            </Button>
        );
    }

    if (user.friendship_state === 'incoming') {
        return (
            <Stack direction="row" spacing={0.5}>
                <Button
                    variant="contained"
                    startIcon={<CheckRounded />}
                    onClick={() =>
                        router.put(
                            `/friendships/${user.friendship_id}/accept`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('social.accept')}
                </Button>
                <Button
                    color="inherit"
                    onClick={() =>
                        router.delete(
                            `/friendships/${user.friendship_id}`,
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('social.decline')}
                </Button>
            </Stack>
        );
    }

    if (user.friendship_state === 'outgoing') {
        return (
            <Button
                color="inherit"
                variant="outlined"
                startIcon={<HourglassTopRounded />}
                onClick={() =>
                    router.delete(`/friendships/${user.friendship_id}`, {
                        preserveScroll: true,
                    })
                }
            >
                {t('social.requested')}
            </Button>
        );
    }

    return (
        <Button
            variant="soft"
            startIcon={<PersonAddAltRounded />}
            onClick={() =>
                router.post(
                    `/users/${user.username}/friend-request`,
                    {},
                    { preserveScroll: true },
                )
            }
        >
            {t('social.add_friend')}
        </Button>
    );
}
