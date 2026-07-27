import { Head, router, useForm } from '@inertiajs/react';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import EditRounded from '@mui/icons-material/EditRounded';
import ExpandMoreRounded from '@mui/icons-material/ExpandMoreRounded';
import InfoOutlined from '@mui/icons-material/InfoOutlined';
import MonitorWeightOutlined from '@mui/icons-material/MonitorWeightOutlined';
import SaveRounded from '@mui/icons-material/SaveRounded';
import TrendingDownRounded from '@mui/icons-material/TrendingDownRounded';
import TrendingFlatRounded from '@mui/icons-material/TrendingFlatRounded';
import TrendingUpRounded from '@mui/icons-material/TrendingUpRounded';
import {
    Box,
    Button,
    Card,
    CardContent,
    CardHeader,
    CircularProgress,
    Collapse,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Grid,
    IconButton,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import dayjs from 'dayjs';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import {
    formatDate,
    formatNumber,
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';

type WeightEntry = {
    id: number;
    date: string;
    weight: number;
    note: string | null;
};

type WeightPoint = {
    date: string;
    weight: number;
};

const weightColor = 'var(--mui-palette-primary-main)';

export default function WeightIndex({
    today,
    entries,
    trend,
    summary,
}: {
    today: string;
    entries: WeightEntry[];
    trend: WeightPoint[];
    summary: {
        current: number | null;
        change: number | null;
        loggedDays: number;
    };
}) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [detailsEntry, setDetailsEntry] = useState<WeightEntry | null>(null);
    const form = useForm<{
        date: string;
        weight: NumberInputValue;
        note: string;
    }>({
        date: today,
        weight: '',
        note: '',
    });
    const chartData = trend.map((point) => ({
        ...point,
        label: formatDate(point.date, { year: undefined }),
    }));

    const resetForm = () => {
        setEditingId(null);
        setFormOpen(false);
        form.clearErrors();
        form.setData({
            date: today,
            weight: '',
            note: '',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editingId) {
            form.put(`/weight/${editingId}`, {
                preserveScroll: true,
                onSuccess: resetForm,
            });
        } else {
            form.post('/weight', {
                preserveScroll: true,
                onSuccess: resetForm,
            });
        }
    };

    const startEditing = (entry: WeightEntry) => {
        setEditingId(entry.id);
        setFormOpen(true);
        form.clearErrors();
        form.setData({
            date: entry.date,
            weight: entry.weight,
            note: entry.note ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <AppLayout
            title={t('weight.title')}
            subtitle={t('weight.description')}
        >
            <Head title={t('weight.title')} />

            <Stack spacing={{ xs: 2, sm: 3 }}>
                <Grid container spacing={{ xs: 2, sm: 3 }}>
                    <Grid size={{ xs: 12 }}>
                        <Card>
                            <CardHeader
                                title={
                                    editingId
                                        ? t('weight.edit_entry')
                                        : t('weight.add_entry')
                                }
                                subheader={t('weight.form_description')}
                            />
                            <Box
                                sx={{
                                    px: 3,
                                    pt: 2,
                                    pb: formOpen ? 0 : 3,
                                }}
                            >
                                <Button
                                    variant={formOpen ? 'text' : 'soft'}
                                    color={formOpen ? 'inherit' : 'primary'}
                                    disabled={form.processing}
                                    endIcon={
                                        <ExpandMoreRounded
                                            sx={{
                                                transition:
                                                    'transform 140ms ease-out',
                                                transform: formOpen
                                                    ? 'rotate(180deg)'
                                                    : 'rotate(0deg)',
                                            }}
                                        />
                                    }
                                    onClick={() => {
                                        if (formOpen) {
                                            resetForm();
                                        } else {
                                            setFormOpen(true);
                                        }
                                    }}
                                >
                                    {formOpen
                                        ? t('common.close')
                                        : t('weight.open_form')}
                                </Button>
                            </Box>
                            <Collapse
                                in={formOpen}
                                timeout={{ enter: 180, exit: 130 }}
                                easing={{
                                    enter: 'cubic-bezier(0.22, 1, 0.36, 1)',
                                    exit: 'cubic-bezier(0.4, 0, 1, 1)',
                                }}
                                unmountOnExit
                            >
                                <CardContent>
                                    <Stack
                                        component="form"
                                        spacing={2.5}
                                        onSubmit={submit}
                                    >
                                    <DatePicker
                                        label={t('weight.date')}
                                        format="DD.MM.YYYY"
                                        value={
                                            form.data.date
                                                ? dayjs(form.data.date)
                                                : null
                                        }
                                        maxDate={dayjs(today)}
                                        slotProps={{
                                            textField: {
                                                fullWidth: true,
                                                required: true,
                                                error: Boolean(
                                                    form.errors.date,
                                                ),
                                                helperText: form.errors.date,
                                            },
                                            actionBar: {
                                                actions: [
                                                    'today',
                                                    'cancel',
                                                    'accept',
                                                ],
                                            },
                                        }}
                                        onChange={(value) =>
                                            form.setData(
                                                'date',
                                                value?.isValid()
                                                    ? value.format('YYYY-MM-DD')
                                                    : '',
                                            )
                                        }
                                    />
                                    <TextField
                                        fullWidth
                                        required
                                        autoFocus
                                        type="number"
                                        label={t('weight.weight_kg')}
                                        value={form.data.weight}
                                        error={Boolean(form.errors.weight)}
                                        helperText={form.errors.weight}
                                        slotProps={{
                                            htmlInput: {
                                                min: 20,
                                                max: 500,
                                                step: 0.1,
                                            },
                                        }}
                                        onChange={(event) =>
                                            form.setData(
                                                'weight',
                                                parseNumberInput(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                    />
                                    <TextField
                                        fullWidth
                                        multiline
                                        minRows={3}
                                        label={t('weight.note')}
                                        placeholder={t(
                                            'weight.note_placeholder',
                                        )}
                                        value={form.data.note}
                                        error={Boolean(form.errors.note)}
                                        helperText={
                                            form.errors.note ??
                                            t('weight.note_help')
                                        }
                                        slotProps={{
                                            htmlInput: { maxLength: 500 },
                                        }}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Stack direction="row" spacing={1}>
                                        <Button
                                            type="submit"
                                            variant="contained"
                                            disabled={form.processing}
                                            startIcon={
                                                form.processing ? (
                                                    <CircularProgress
                                                        size={18}
                                                        color="inherit"
                                                    />
                                                ) : (
                                                    <SaveRounded />
                                                )
                                            }
                                        >
                                            {editingId
                                                ? t('weight.update_entry')
                                                : t('weight.save_entry')}
                                        </Button>
                                        {editingId && (
                                            <Button
                                                color="inherit"
                                                onClick={resetForm}
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                        )}
                                    </Stack>
                                    </Stack>
                                </CardContent>
                            </Collapse>
                        </Card>
                    </Grid>
                </Grid>

                <Grid container spacing={{ xs: 2, sm: 3 }}>
                    <Grid size={{ xs: 12, sm: 4 }}>
                        <SummaryCard
                            label={t('weight.current')}
                            value={
                                summary.current === null
                                    ? '—'
                                    : `${formatNumber(summary.current, 2)} kg`
                            }
                            context={t('weight.latest_entry')}
                            icon={<MonitorWeightOutlined />}
                        />
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4 }}>
                        <SummaryCard
                            compactOnMobile
                            label={t('weight.change_90_days')}
                            value={
                                summary.change === null
                                    ? '—'
                                    : `${summary.change > 0 ? '+' : ''}${formatNumber(summary.change, 2)} kg`
                            }
                            context={t('weight.change_context')}
                            icon={<TrendIcon change={summary.change} />}
                        />
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4 }}>
                        <SummaryCard
                            compactOnMobile
                            label={t('weight.logged_days')}
                            value={formatNumber(summary.loggedDays)}
                            context={t('weight.last_90_days')}
                            icon={<SaveRounded />}
                        />
                    </Grid>
                </Grid>

                <Grid container spacing={{ xs: 2, sm: 3 }}>
                    <Grid size={{ xs: 12 }}>
                        <Card sx={{ height: 1 }}>
                            <CardHeader
                                title={t('weight.trend')}
                                subheader={t('weight.trend_description')}
                            />
                            <CardContent>
                                {chartData.length === 0 ? (
                                    <EmptyState />
                                ) : (
                                    <Box
                                        role="img"
                                        aria-label={t('weight.trend_chart')}
                                        sx={{ width: 1, height: 320 }}
                                    >
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <LineChart
                                                data={chartData}
                                                margin={{
                                                    top: 12,
                                                    right: 12,
                                                    left: -12,
                                                    bottom: 0,
                                                }}
                                            >
                                                <CartesianGrid
                                                    vertical={false}
                                                    stroke="var(--mui-palette-divider)"
                                                    strokeDasharray="3 3"
                                                />
                                                <XAxis
                                                    dataKey="label"
                                                    axisLine={false}
                                                    tickLine={false}
                                                    minTickGap={30}
                                                    tick={{
                                                        fill: 'var(--mui-palette-text-secondary)',
                                                        fontSize: 12,
                                                    }}
                                                />
                                                <YAxis
                                                    domain={[
                                                        'dataMin - 2',
                                                        'dataMax + 2',
                                                    ]}
                                                    axisLine={false}
                                                    tickLine={false}
                                                    width={52}
                                                    tick={{
                                                        fill: 'var(--mui-palette-text-secondary)',
                                                        fontSize: 11,
                                                    }}
                                                    tickFormatter={(value) =>
                                                        `${formatNumber(value, 1)}`
                                                    }
                                                />
                                                <Tooltip
                                                    content={
                                                        <WeightTooltip />
                                                    }
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey="weight"
                                                    stroke={weightColor}
                                                    strokeWidth={3}
                                                    dot={{
                                                        r: 4,
                                                        fill: weightColor,
                                                        strokeWidth: 0,
                                                    }}
                                                    activeDot={{ r: 6 }}
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </Box>
                                )}
                            </CardContent>
                        </Card>
                    </Grid>
                </Grid>

                <Card>
                    <CardHeader
                        title={t('weight.history')}
                        subheader={t('weight.history_description')}
                    />
                    <CardContent sx={{ pt: 0 }}>
                        {entries.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <TableContainer>
                                <Table
                                    aria-label={t('weight.history')}
                                    sx={{
                                        '& th:last-of-type, & td:last-of-type': {
                                            paddingRight: '0 !important',
                                        },
                                    }}
                                >
                                    <TableHead>
                                        <TableRow>
                                            <TableCell
                                                sx={{ width: '38%', pl: 0 }}
                                            >
                                                {t('weight.date')}
                                            </TableCell>
                                            <TableCell
                                                sx={{ width: '30%' }}
                                            >
                                                {t('weight.weight')}
                                            </TableCell>
                                            <TableCell
                                                align="right"
                                                sx={{ width: 120 }}
                                            >
                                                {t('weight.actions')}
                                            </TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {entries.map((entry) => (
                                            <TableRow
                                                key={entry.id}
                                                hover
                                                selected={
                                                    editingId === entry.id
                                                }
                                            >
                                                <TableCell sx={{ pl: 0 }}>
                                                    {formatDate(entry.date)}
                                                </TableCell>
                                                <TableCell
                                                    sx={{ whiteSpace: 'nowrap' }}
                                                >
                                                    <Typography
                                                        variant="subtitle2"
                                                        color="primary.main"
                                                    >
                                                        {formatNumber(
                                                            entry.weight,
                                                            2,
                                                        )}{' '}
                                                        kg
                                                    </Typography>
                                                </TableCell>
                                                <TableCell
                                                    align="right"
                                                >
                                                    <Stack
                                                        direction="row"
                                                        justifyContent="flex-end"
                                                        spacing={0.25}
                                                    >
                                                        <IconButton
                                                            size="small"
                                                            sx={{
                                                                width: 'fit-content',
                                                                height: 32,
                                                                py: 0.5,
                                                                pl: 0.5,
                                                                pr: 0,
                                                            }}
                                                            aria-label={t(
                                                                'weight.view_details',
                                                            )}
                                                            onClick={() =>
                                                                setDetailsEntry(
                                                                    entry,
                                                                )
                                                            }
                                                        >
                                                            <InfoOutlined fontSize="small" />
                                                        </IconButton>
                                                        <IconButton
                                                            size="small"
                                                            sx={{
                                                                width: 'fit-content',
                                                                height: 32,
                                                                py: 0.5,
                                                                pl: 0.5,
                                                                pr: 0,
                                                            }}
                                                            aria-label={t(
                                                                'weight.edit_entry',
                                                            )}
                                                            onClick={() =>
                                                                startEditing(
                                                                    entry,
                                                                )
                                                            }
                                                        >
                                                            <EditRounded fontSize="small" />
                                                        </IconButton>
                                                        <IconButton
                                                            size="small"
                                                            color="error"
                                                            sx={{
                                                                width: 'fit-content',
                                                                height: 32,
                                                                py: 0.5,
                                                                pl: 0.5,
                                                                pr: 0,
                                                            }}
                                                            aria-label={t(
                                                                'weight.delete_entry',
                                                            )}
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        t(
                                                                            'weight.delete_confirm',
                                                                        ),
                                                                    )
                                                                ) {
                                                                    router.delete(
                                                                        `/weight/${entry.id}`,
                                                                        {
                                                                            preserveScroll:
                                                                                true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <DeleteOutlineRounded fontSize="small" />
                                                        </IconButton>
                                                    </Stack>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </TableContainer>
                        )}
                    </CardContent>
                </Card>
            </Stack>

            <Dialog
                fullWidth
                maxWidth="xs"
                open={detailsEntry !== null}
                onClose={() => setDetailsEntry(null)}
            >
                <DialogTitle>{t('weight.entry_details')}</DialogTitle>
                <DialogContent dividers>
                    {detailsEntry && (
                        <Stack spacing={2.5}>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    {t('weight.date')}
                                </Typography>
                                <Typography variant="subtitle1">
                                    {formatDate(detailsEntry.date)}
                                </Typography>
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    {t('weight.weight')}
                                </Typography>
                                <Typography
                                    variant="h5"
                                    color="primary.main"
                                >
                                    {formatNumber(detailsEntry.weight, 2)} kg
                                </Typography>
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    {t('weight.note')}
                                </Typography>
                                <Paper
                                    variant="outlined"
                                    sx={{
                                        mt: 0.75,
                                        p: 2,
                                        minHeight: 72,
                                        bgcolor: 'background.default',
                                    }}
                                >
                                    <Typography
                                        variant="body2"
                                        color={
                                            detailsEntry.note
                                                ? 'text.primary'
                                                : 'text.secondary'
                                        }
                                        sx={{ whiteSpace: 'pre-wrap' }}
                                    >
                                        {detailsEntry.note ||
                                            t('weight.no_note')}
                                    </Typography>
                                </Paper>
                            </Box>
                        </Stack>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDetailsEntry(null)}>
                        {t('common.close')}
                    </Button>
                </DialogActions>
            </Dialog>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    context,
    icon,
    compactOnMobile = false,
}: {
    label: string;
    value: string;
    context: string;
    icon: React.ReactNode;
    compactOnMobile?: boolean;
}) {
    return (
        <Card sx={{ height: 1 }}>
            <CardContent
                sx={
                    compactOnMobile
                        ? {
                              p: { xs: 2, sm: 3 },
                              '&:last-child': {
                                  pb: { xs: 2, sm: 3 },
                              },
                          }
                        : undefined
                }
            >
                <Stack direction="row" justifyContent="space-between" spacing={2}>
                    <Box sx={{ minWidth: 0 }}>
                        <Typography variant="body2" color="text.secondary">
                            {label}
                        </Typography>
                        <Typography
                            variant="h4"
                            sx={{
                                mt: 0.5,
                                whiteSpace: 'nowrap',
                                ...(compactOnMobile && {
                                    fontSize: {
                                        xs: '1.5rem',
                                        sm: '2.125rem',
                                    },
                                }),
                            }}
                        >
                            {value}
                        </Typography>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            sx={{
                                display: 'block',
                                lineHeight: 1.4,
                            }}
                        >
                            {context}
                        </Typography>
                    </Box>
                    <Box
                        sx={{
                            display: 'grid',
                            placeItems: 'center',
                            width: 44,
                            height: 44,
                            flexShrink: 0,
                            borderRadius: 2,
                            color: 'primary.main',
                            bgcolor: 'primary.lighter',
                            ...(compactOnMobile && {
                                display: { xs: 'none', sm: 'grid' },
                            }),
                        }}
                    >
                        {icon}
                    </Box>
                </Stack>
            </CardContent>
        </Card>
    );
}

function TrendIcon({ change }: { change: number | null }) {
    if (change === null || change === 0) {
        return <TrendingFlatRounded />;
    }

    return change > 0 ? <TrendingUpRounded /> : <TrendingDownRounded />;
}

function EmptyState() {
    const { t } = useTranslation();

    return (
        <Stack
            alignItems="center"
            spacing={1.5}
            sx={{
                py: 7,
                borderRadius: 2,
                bgcolor: 'background.default',
                color: 'text.secondary',
            }}
        >
            <MonitorWeightOutlined color="primary" />
            <Typography variant="body2">{t('weight.empty')}</Typography>
        </Stack>
    );
}

function WeightTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { value: number }[];
    label?: string;
}) {
    const { t } = useTranslation();

    if (!active || !payload?.length) return null;

    return (
        <Paper elevation={12} sx={{ p: 1.5 }}>
            <Typography variant="subtitle2">{label}</Typography>
            <Typography variant="caption" color="text.secondary">
                {t('weight.weight')}:{' '}
                <Box component="strong" sx={{ color: 'text.primary' }}>
                    {formatNumber(payload[0].value, 2)} kg
                </Box>
            </Typography>
        </Paper>
    );
}
