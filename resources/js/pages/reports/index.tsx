import { Head, router } from '@inertiajs/react';
import CalendarMonthRounded from '@mui/icons-material/CalendarMonthRounded';
import LocalFireDepartmentOutlined from '@mui/icons-material/LocalFireDepartmentOutlined';
import MonitorWeightOutlined from '@mui/icons-material/MonitorWeightOutlined';
import TrackChangesRounded from '@mui/icons-material/TrackChangesRounded';
import {
    Box,
    Button,
    Card,
    CardContent,
    CardHeader,
    Collapse,
    FormControl,
    Grid,
    InputLabel,
    MenuItem,
    Paper,
    Select,
    Stack,
    ToggleButton,
    ToggleButtonGroup,
    Typography,
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/utils';
import type { NutritionTargets } from '@/types';

const chartColors = {
    calories: 'var(--mui-palette-primary-main)',
    protein: '#8E33FF',
    carbohydrates: '#FFAB00',
    fat: '#FF5630',
    fibre: '#22C55E',
    weight: '#0EA5E9',
    grid: 'var(--mui-palette-divider)',
    muted: 'var(--mui-palette-text-secondary)',
};

type ChartPoint = {
    date: string;
    day: string;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
};

type CalorieChartPoint = Pick<ChartPoint, 'date' | 'day' | 'calories'>;

type WeightPoint = {
    date: string;
    weight: number;
};

type ReportRange = '7' | '30' | '365' | 'custom';

type ReportPeriod = {
    range: ReportRange;
    start: string;
    end: string;
    today: string;
    days: number;
};

export default function ReportsIndex({
    period,
    chart,
    calorieChart,
    averages,
    loggedDays,
    topFoods,
    targets,
    weightChart,
    weightSummary,
}: {
    period: ReportPeriod;
    chart: ChartPoint[];
    calorieChart: CalorieChartPoint[];
    averages: NutritionTargets;
    loggedDays: number;
    topFoods: { name: string; times: number; calories: number }[];
    targets: NutritionTargets;
    weightChart: WeightPoint[];
    weightSummary: {
        current: number | null;
        change: number | null;
        loggedDays: number;
    };
}) {
    const { t } = useTranslation();
    const localizedChart = chart.map((point) => ({
        ...point,
        day:
            period.days <= 14
                ? formatDate(point.date, {
                      weekday: 'short',
                      month: undefined,
                      day: undefined,
                      year: undefined,
                  })
                : formatDate(point.date, {
                      month: 'short',
                      day: period.days <= 90 ? 'numeric' : undefined,
                      year: undefined,
                  }),
    }));
    const localizedCalorieChart = calorieChart.map((point) => ({
        ...point,
        day:
            period.days <= 14
                ? formatDate(point.date, {
                      weekday: 'short',
                      month: undefined,
                      day: undefined,
                      year: undefined,
                  })
                : formatDate(point.date, {
                      month: 'short',
                      day: period.days <= 90 ? 'numeric' : undefined,
                      year: undefined,
                  }),
    }));
    const localizedWeightChart = weightChart.map((point) => ({
        ...point,
        day: formatDate(point.date, { year: undefined }),
    }));

    return (
        <AppLayout title={t('reports.title')} subtitle={t('reports.description')}>
            <Head title={t('common.reports')} />

            <Stack spacing={2}>
                <ReportRangeSelector period={period} />

                <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.daily_average')}
                            value={`${formatNumber(averages.calories)} kcal`}
                            context={t('reports.days_logged', {
                                count: loggedDays,
                                total: period.days,
                            })}
                            color={chartColors.calories}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.protein_average')}
                            value={`${formatNumber(averages.protein, 1)} g`}
                            context={t('reports.target', {
                                target: formatNumber(targets.protein),
                            })}
                            color={chartColors.protein}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.carb_average')}
                            value={`${formatNumber(averages.carbohydrates, 1)} g`}
                            context={t('reports.target', {
                                target: formatNumber(targets.carbohydrates),
                            })}
                            color={chartColors.carbohydrates}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.fat_average')}
                            value={`${formatNumber(averages.fat, 1)} g`}
                            context={t('reports.target', {
                                target: formatNumber(targets.fat),
                            })}
                            color={chartColors.fat}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.fibre_average')}
                            value={`${formatNumber(averages.fibre, 1)} g`}
                            context={t('reports.target', {
                                target: formatNumber(targets.fibre),
                            })}
                            color={chartColors.fibre}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6, lg: 2 }}>
                        <StatCard
                            label={t('reports.current_weight')}
                            value={
                                weightSummary.current === null
                                    ? '—'
                                    : `${formatNumber(weightSummary.current, 2)} kg`
                            }
                            context={
                                weightSummary.change === null
                                    ? t('reports.no_weight_change')
                                    : t('reports.weight_change', {
                                          change: `${weightSummary.change > 0 ? '+' : ''}${formatNumber(weightSummary.change, 2)}`,
                                      })
                            }
                            color={chartColors.weight}
                        />
                    </Grid>
                </Grid>

                <Grid container spacing={2}>
                    <Grid size={{ xs: 12, lg: 8 }}>
                        <Card sx={{ height: 1 }}>
                            <CardHeader
                                title={t('reports.calories_by_day')}
                                subheader={t('reports.calories_comparison', {
                                    target: formatNumber(targets.calories),
                                })}
                                action={
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            placeItems: 'center',
                                            width: 44,
                                            height: 44,
                                            borderRadius: 2,
                                            color: 'primary.main',
                                            bgcolor: 'primary.lighter',
                                        }}
                                    >
                                        <LocalFireDepartmentOutlined />
                                    </Box>
                                }
                            />
                            <CardContent>
                                <Box
                                    role="img"
                                    aria-label={t('reports.calorie_chart')}
                                    sx={{ width: 1, height: 300 }}
                                >
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart
                                            data={localizedCalorieChart}
                                            margin={{
                                                top: 10,
                                                right: 6,
                                                left: -22,
                                                bottom: 0,
                                            }}
                                        >
                                            <defs>
                                                <linearGradient
                                                    id="calorieFill"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor={chartColors.calories}
                                                        stopOpacity={0.3}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor={chartColors.calories}
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid
                                                vertical={false}
                                                stroke={chartColors.grid}
                                                strokeDasharray="3 3"
                                            />
                                            <XAxis
                                                dataKey="day"
                                                axisLine={false}
                                                tickLine={false}
                                                tick={{
                                                    fill: chartColors.muted,
                                                    fontSize: 12,
                                                }}
                                            />
                                            <YAxis
                                                domain={[
                                                    0,
                                                    Math.max(
                                                        targets.calories,
                                                        ...calorieChart.map(
                                                            (point) =>
                                                                point.calories,
                                                        ),
                                                    ),
                                                ]}
                                                axisLine={false}
                                                tickLine={false}
                                                tick={{
                                                    fill: chartColors.muted,
                                                    fontSize: 11,
                                                }}
                                            />
                                            <Tooltip
                                                content={<ChartTooltip unit="kcal" />}
                                            />
                                            <ReferenceLine
                                                y={targets.calories}
                                                stroke={chartColors.muted}
                                                strokeDasharray="4 4"
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="calories"
                                                stroke={chartColors.calories}
                                                strokeWidth={3}
                                                fill="url(#calorieFill)"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </Box>
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid size={{ xs: 12, lg: 4 }}>
                        <Card sx={{ height: 1 }}>
                            <CardHeader title={t('reports.most_logged')} />
                            <CardContent>
                                {topFoods.length === 0 ? (
                                    <Stack
                                        alignItems="center"
                                        spacing={2}
                                        sx={{
                                            py: 5,
                                            borderRadius: 2,
                                            bgcolor: 'background.default',
                                            color: 'text.secondary',
                                        }}
                                    >
                                        <TrackChangesRounded color="primary" />
                                        <Typography variant="body2">
                                            {t('reports.empty_patterns')}
                                        </Typography>
                                    </Stack>
                                ) : (
                                    <Stack spacing={1}>
                                        {topFoods.map((food, index) => (
                                            <Stack
                                                key={food.name}
                                                direction="row"
                                                alignItems="center"
                                                spacing={2}
                                                sx={{
                                                    p: 1,
                                                    borderRadius: 1.5,
                                                    transition: (theme) =>
                                                        theme.transitions.create(
                                                            'background-color',
                                                        ),
                                                    '&:hover': {
                                                        bgcolor: 'action.hover',
                                                    },
                                                }}
                                            >
                                                <Box
                                                    sx={{
                                                        display: 'grid',
                                                        placeItems: 'center',
                                                        flexShrink: 0,
                                                        width: 32,
                                                        height: 32,
                                                        borderRadius: 1.25,
                                                        bgcolor:
                                                            'background.default',
                                                        typography: 'caption',
                                                        fontWeight: 700,
                                                    }}
                                                >
                                                    {index + 1}
                                                </Box>
                                                <Box sx={{ minWidth: 0, flex: 1 }}>
                                                    <Typography
                                                        variant="subtitle2"
                                                        noWrap
                                                    >
                                                        {food.name}
                                                    </Typography>
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                    >
                                                        {t('reports.times', {
                                                            count: food.times,
                                                        })}
                                                    </Typography>
                                                </Box>
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                >
                                                    {formatNumber(food.calories)} kcal
                                                </Typography>
                                            </Stack>
                                        ))}
                                    </Stack>
                                )}
                            </CardContent>
                        </Card>
                    </Grid>
                </Grid>

                <Card>
                    <CardHeader
                        title={t('reports.weight_trend')}
                        subheader={t('reports.weight_trend_description')}
                        action={
                            <Box
                                sx={{
                                    display: 'grid',
                                    placeItems: 'center',
                                    width: 44,
                                    height: 44,
                                    borderRadius: 2,
                                    color: chartColors.weight,
                                    bgcolor: 'primary.lighter',
                                }}
                            >
                                <MonitorWeightOutlined />
                            </Box>
                        }
                    />
                    <CardContent>
                        {localizedWeightChart.length === 0 ? (
                            <Stack
                                alignItems="center"
                                spacing={2}
                                sx={{
                                    py: 6,
                                    borderRadius: 2,
                                    bgcolor: 'background.default',
                                    color: 'text.secondary',
                                }}
                            >
                                <MonitorWeightOutlined color="primary" />
                                <Typography variant="body2">
                                    {t('reports.empty_weight')}
                                </Typography>
                            </Stack>
                        ) : (
                            <Box
                                role="img"
                                aria-label={t('reports.weight_chart')}
                                sx={{ width: 1, height: 300 }}
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart
                                        data={localizedWeightChart}
                                        margin={{
                                            top: 10,
                                            right: 8,
                                            left: -8,
                                            bottom: 0,
                                        }}
                                    >
                                        <CartesianGrid
                                            vertical={false}
                                            stroke={chartColors.grid}
                                            strokeDasharray="3 3"
                                        />
                                        <XAxis
                                            dataKey="day"
                                            axisLine={false}
                                            tickLine={false}
                                            minTickGap={30}
                                            tick={{
                                                fill: chartColors.muted,
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
                                                fill: chartColors.muted,
                                                fontSize: 11,
                                            }}
                                        />
                                        <Tooltip
                                            content={<ChartTooltip unit="kg" />}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="weight"
                                            stroke={chartColors.weight}
                                            strokeWidth={3}
                                            dot={{
                                                r: 4,
                                                fill: chartColors.weight,
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

                <Card>
                    <CardHeader title={t('reports.macros_by_day')} />
                    <CardContent>
                        <Box
                            role="img"
                            aria-label={t('reports.macro_chart')}
                            sx={{ width: 1, height: 300 }}
                        >
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={localizedChart}
                                    margin={{
                                        top: 4,
                                        right: 4,
                                        left: -22,
                                        bottom: 0,
                                    }}
                                >
                                    <CartesianGrid
                                        vertical={false}
                                        stroke={chartColors.grid}
                                        strokeDasharray="3 3"
                                    />
                                    <XAxis
                                        dataKey="day"
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{
                                            fill: chartColors.muted,
                                            fontSize: 12,
                                        }}
                                    />
                                    <YAxis
                                        domain={[
                                            0,
                                            Math.max(
                                                targets.protein,
                                                targets.carbohydrates,
                                                targets.fat,
                                                targets.fibre,
                                                ...chart.flatMap((point) => [
                                                    point.protein,
                                                    point.carbohydrates,
                                                    point.fat,
                                                    point.fibre,
                                                ]),
                                            ),
                                        ]}
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{
                                            fill: chartColors.muted,
                                            fontSize: 11,
                                        }}
                                    />
                                    <Tooltip content={<ChartTooltip unit="g" />} />
                                    <Bar
                                        dataKey="protein"
                                        fill={chartColors.protein}
                                        radius={[8, 8, 2, 2]}
                                    />
                                    <Bar
                                        dataKey="carbohydrates"
                                        fill={chartColors.carbohydrates}
                                        radius={[8, 8, 2, 2]}
                                    />
                                    <Bar
                                        dataKey="fat"
                                        fill={chartColors.fat}
                                        radius={[8, 8, 2, 2]}
                                    />
                                    <Bar
                                        dataKey="fibre"
                                        fill={chartColors.fibre}
                                        radius={[8, 8, 2, 2]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </Box>
                        <Stack
                            direction="row"
                            flexWrap="wrap"
                            justifyContent="center"
                            gap={2.5}
                            sx={{ mt: 2 }}
                        >
                            <Legend
                                color={chartColors.protein}
                                label={t('common.protein')}
                            />
                            <Legend
                                color={chartColors.carbohydrates}
                                label={t('common.carbohydrates')}
                            />
                            <Legend
                                color={chartColors.fat}
                                label={t('common.fat')}
                            />
                            <Legend
                                color={chartColors.fibre}
                                label={t('common.fibre')}
                            />
                        </Stack>
                    </CardContent>
                </Card>
            </Stack>
        </AppLayout>
    );
}

function ReportRangeSelector({ period }: { period: ReportPeriod }) {
    const { t } = useTranslation();
    const [selectedRange, setSelectedRange] =
        useState<ReportRange>(period.range);
    const [startDate, setStartDate] = useState<Dayjs | null>(
        dayjs(period.start),
    );
    const [endDate, setEndDate] = useState<Dayjs | null>(dayjs(period.end));
    const today = dayjs(period.today);
    const rangeOptions: { value: ReportRange; label: string }[] = [
        { value: '7', label: t('reports.last_7_days') },
        { value: '30', label: t('reports.last_30_days') },
        { value: '365', label: t('reports.last_365_days') },
        { value: 'custom', label: t('reports.custom') },
    ];

    useEffect(() => {
        setSelectedRange(period.range);
        setStartDate(dayjs(period.start));
        setEndDate(dayjs(period.end));
    }, [period.end, period.range, period.start]);

    const changeRange = (range: ReportRange) => {
        setSelectedRange(range);

        if (range !== 'custom') {
            router.get('/reports', { range }, { preserveScroll: true });
        }
    };
    const customRangeIsValid = Boolean(
        startDate?.isValid() &&
            endDate?.isValid() &&
            !startDate.isAfter(endDate, 'day') &&
            !endDate.isAfter(today, 'day') &&
            endDate.diff(startDate, 'day') <= 364,
    );
    const applyCustomRange = () => {
        if (!customRangeIsValid || !startDate || !endDate) return;

        router.get(
            '/reports',
            {
                range: 'custom',
                start: startDate.format('YYYY-MM-DD'),
                end: endDate.format('YYYY-MM-DD'),
            },
            { preserveScroll: true },
        );
    };

    return (
        <Paper variant="outlined" sx={{ overflow: 'hidden' }}>
            <Stack spacing={2} sx={{ p: { xs: 1.5, sm: 2 } }}>
                <Stack
                    direction={{ xs: 'column', md: 'row' }}
                    alignItems={{ xs: 'stretch', md: 'center' }}
                    justifyContent="space-between"
                    gap={2}
                >
                    <Stack direction="row" alignItems="center" spacing={1.5}>
                        <Box
                            sx={{
                                display: 'grid',
                                placeItems: 'center',
                                width: 40,
                                height: 40,
                                flexShrink: 0,
                                borderRadius: 1.5,
                                color: 'primary.main',
                                bgcolor: 'primary.lighter',
                            }}
                        >
                            <CalendarMonthRounded />
                        </Box>
                        <Box sx={{ minWidth: 0 }}>
                            <Typography variant="subtitle2">
                                {t(`reports.range_${period.range}`)}
                            </Typography>
                            <Typography variant="caption" color="text.secondary">
                                {formatDate(period.start)} –{' '}
                                {formatDate(period.end)}
                            </Typography>
                        </Box>
                    </Stack>

                    <FormControl
                        fullWidth
                        size="small"
                        sx={{ display: { xs: 'flex', sm: 'none' } }}
                    >
                        <InputLabel id="report-range-label">
                            {t('reports.range')}
                        </InputLabel>
                        <Select
                            labelId="report-range-label"
                            value={selectedRange}
                            label={t('reports.range')}
                            onChange={(event) =>
                                changeRange(event.target.value as ReportRange)
                            }
                        >
                            {rangeOptions.map((option) => (
                                <MenuItem key={option.value} value={option.value}>
                                    {option.label}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>

                    <ToggleButtonGroup
                        exclusive
                        size="small"
                        color="primary"
                        value={selectedRange}
                        aria-label={t('reports.range')}
                        sx={{
                            display: { xs: 'none', sm: 'flex' },
                            alignSelf: { sm: 'stretch', md: 'center' },
                            '& .MuiToggleButton-root': {
                                flex: { sm: 1, md: 'initial' },
                                px: { sm: 1.5, lg: 2 },
                                whiteSpace: 'nowrap',
                            },
                        }}
                        onChange={(_, value: ReportRange | null) => {
                            if (value) changeRange(value);
                        }}
                    >
                        {rangeOptions.map((option) => (
                            <ToggleButton key={option.value} value={option.value}>
                                {option.label}
                            </ToggleButton>
                        ))}
                    </ToggleButtonGroup>
                </Stack>
            </Stack>

            <Collapse in={selectedRange === 'custom'} unmountOnExit>
                <Stack
                    spacing={1.5}
                    sx={{
                        p: { xs: 1.5, sm: 2 },
                        pt: { xs: 1.5, sm: 2 },
                        borderTop: 1,
                        borderColor: 'divider',
                        bgcolor: 'background.default',
                    }}
                >
                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                        <DatePicker
                            label={t('reports.start_date')}
                            format="DD.MM.YYYY"
                            value={startDate}
                            maxDate={endDate ?? today}
                            slotProps={{
                                textField: { fullWidth: true, size: 'small' },
                                actionBar: {
                                    actions: ['today', 'cancel', 'accept'],
                                },
                            }}
                            onChange={setStartDate}
                        />
                        <DatePicker
                            label={t('reports.end_date')}
                            format="DD.MM.YYYY"
                            value={endDate}
                            minDate={startDate ?? undefined}
                            maxDate={today}
                            slotProps={{
                                textField: { fullWidth: true, size: 'small' },
                                actionBar: {
                                    actions: ['today', 'cancel', 'accept'],
                                },
                            }}
                            onChange={setEndDate}
                        />
                        <Button
                            variant="contained"
                            disabled={!customRangeIsValid}
                            sx={{
                                minHeight: 40,
                                flexShrink: 0,
                                px: 3,
                            }}
                            onClick={applyCustomRange}
                        >
                            {t('reports.apply_range')}
                        </Button>
                    </Stack>
                    <Typography variant="caption" color="text.secondary">
                        {t('reports.custom_range_help')}
                    </Typography>
                </Stack>
            </Collapse>
        </Paper>
    );
}

function StatCard({
    label,
    value,
    context,
    color,
}: {
    label: string;
    value: string;
    context: string;
    color: string;
}) {
    return (
        <Card
            sx={{
                height: 1,
                transition: (theme) =>
                    theme.transitions.create(['transform', 'box-shadow']),
                '&:hover': {
                    transform: 'translateY(-2px)',
                    boxShadow: (theme) => theme.shadows[8],
                },
            }}
        >
            <CardContent>
                <Box
                    sx={{
                        width: 36,
                        height: 6,
                        mb: 2,
                        borderRadius: 10,
                        bgcolor: color,
                    }}
                />
                <Typography variant="body2" color="text.secondary">
                    {label}
                </Typography>
                <Typography variant="h4" sx={{ mt: 0.5 }}>
                    {value}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                    {context}
                </Typography>
            </CardContent>
        </Card>
    );
}

function ChartTooltip({
    active,
    payload,
    label,
    unit,
}: {
    active?: boolean;
    payload?: { name: string; value: number; color: string }[];
    label?: string;
    unit: string;
}) {
    const { t } = useTranslation();

    if (!active || !payload?.length) return null;

    return (
        <Paper elevation={12} sx={{ p: 1.5, minWidth: 140 }}>
            <Typography variant="subtitle2" sx={{ mb: 0.75 }}>
                {label}
            </Typography>
            {payload.map((item) => (
                <Typography
                    key={item.name}
                    variant="caption"
                    color="text.secondary"
                    sx={{ display: 'block', mt: 0.5 }}
                >
                    {t(
                        item.name === 'carbohydrates'
                            ? 'common.carbohydrates'
                            : `common.${item.name}`,
                    )}
                    :{' '}
                    <Box component="strong" sx={{ color: 'text.primary' }}>
                        {formatNumber(item.value, 1)} {unit}
                    </Box>
                </Typography>
            ))}
        </Paper>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <Stack direction="row" alignItems="center" spacing={1}>
            <Box
                sx={{
                    width: 10,
                    height: 10,
                    borderRadius: 0.75,
                    bgcolor: color,
                }}
            />
            <Typography variant="caption" color="text.secondary">
                {label}
            </Typography>
        </Stack>
    );
}
