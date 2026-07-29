import { Head, router, useForm } from '@inertiajs/react';
import AddRounded from '@mui/icons-material/AddRounded';
import AutoAwesomeRounded from '@mui/icons-material/AutoAwesomeRounded';
import CookieRounded from '@mui/icons-material/CookieRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import DinnerDiningRounded from '@mui/icons-material/DinnerDiningRounded';
import EditNoteOutlined from '@mui/icons-material/EditNoteOutlined';
import EditOutlined from '@mui/icons-material/EditOutlined';
import FreeBreakfastRounded from '@mui/icons-material/FreeBreakfastRounded';
import LunchDiningRounded from '@mui/icons-material/LunchDiningRounded';
import MenuBookOutlined from '@mui/icons-material/MenuBookOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardHeader from '@mui/material/CardHeader';
import CircularProgress from '@mui/material/CircularProgress';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import {
    useEffect,
    useRef,
    useState,
    type FormEvent,
    type MouseEvent,
    type TouchEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { ResponsiveDialog } from '@/components/responsive-dialog';
import { AppLayout } from '@/layouts/app-layout';
import { MacroProgress } from '@/components/macro-progress';
import { PeriodNavigator } from '@/components/period-navigator';
import type { DiaryEntry, NutritionTargets } from '@/types';
import {
    formatDate,
    formatNumber,
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import {
    basisForUnit,
    unitsForBasis,
    type MeasurementUnit,
} from '@/lib/measurement-units';

const meals = [
    { key: 'breakfast', labelKey: 'diary.breakfast', icon: FreeBreakfastRounded },
    { key: 'lunch', labelKey: 'diary.lunch', icon: LunchDiningRounded },
    { key: 'dinner', labelKey: 'diary.dinner', icon: DinnerDiningRounded },
    { key: 'snacks', labelKey: 'diary.snacks', icon: CookieRounded },
] as const;

type Totals = {
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
};

type MealKey = (typeof meals)[number]['key'];

export default function DiaryShow({
    date,
    isToday,
    previousDate,
    nextDate,
    entries,
    totals,
    targets,
    notes,
}: {
    date: string;
    isToday: boolean;
    previousDate: string;
    nextDate: string;
    entries: DiaryEntry[];
    totals: Totals;
    targets: NutritionTargets;
    notes: string | null;
}) {
    const { t } = useTranslation();
    const remaining = targets.calories - totals.calories;
    const calorieProgress =
        targets.calories > 0
            ? Math.min(100, (totals.calories / targets.calories) * 100)
            : 0;
    const [quickEntryMeal, setQuickEntryMeal] = useState<MealKey | null>(null);
    const swipeStart = useRef<{ x: number; y: number } | null>(null);
    const swipeNavigationPending = useRef(false);

    useEffect(() => {
        const focusMeal = new URLSearchParams(window.location.search).get(
            'focus_meal',
        );

        if (!meals.some(({ key }) => key === focusMeal)) return;

        const frame = window.requestAnimationFrame(() => {
            document
                .getElementById(`meal-${focusMeal}`)
                ?.scrollIntoView({ block: 'start', behavior: 'auto' });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [date]);

    const handleTouchStart = (event: TouchEvent<HTMLDivElement>) => {
        if (event.touches.length !== 1) {
            swipeStart.current = null;
            return;
        }

        const target = event.target as HTMLElement;
        if (
            target.closest(
                'a, button, input, textarea, select, [role="button"], [role="dialog"]',
            )
        ) {
            swipeStart.current = null;
            return;
        }

        const touch = event.touches[0];
        swipeStart.current = { x: touch.clientX, y: touch.clientY };
    };

    const handleTouchEnd = (event: TouchEvent<HTMLDivElement>) => {
        const start = swipeStart.current;
        swipeStart.current = null;

        if (
            !start ||
            swipeNavigationPending.current ||
            event.changedTouches.length !== 1
        ) {
            return;
        }

        const touch = event.changedTouches[0];
        const deltaX = touch.clientX - start.x;
        const deltaY = touch.clientY - start.y;

        if (
            Math.abs(deltaX) < 60 ||
            Math.abs(deltaX) <= Math.abs(deltaY) * 1.25
        ) {
            return;
        }

        swipeNavigationPending.current = true;
        const targetDate = deltaX < 0 ? nextDate : previousDate;

        router.visit(`/diary/${targetDate}`, {
            preserveScroll: true,
            onFinish: () => {
                swipeNavigationPending.current = false;
            },
        });
    };

    return (
        <AppLayout
            title={t('diary.title')}
            subtitle={formatDate(date, { weekday: 'long' })}
            actions={
                !isToday ? (
                    <Button
                        size="small"
                        variant="outlined"
                        onClick={() => router.visit('/today')}
                    >
                        {t('common.today')}
                    </Button>
                ) : undefined
            }
        >
            <Head title={isToday ? t('common.today') : formatDate(date)} />
            <Box
                onTouchStart={handleTouchStart}
                onTouchEnd={handleTouchEnd}
                onTouchCancel={() => {
                    swipeStart.current = null;
                }}
                sx={{
                    touchAction: 'pan-y',
                    overscrollBehaviorX: 'contain',
                }}
            >
                <PeriodNavigator
                    title={
                        isToday
                            ? t('common.today')
                            : formatDate(date, { weekday: 'long' })
                    }
                    subtitle={formatDate(date, { year: undefined })}
                    previousHref={`/diary/${previousDate}`}
                    nextHref={`/diary/${nextDate}`}
                    previousLabel={t('common.previous_day')}
                    nextLabel={t('common.next_day')}
                />

                <Grid container spacing={2}>
                    <Grid size={{ xs: 12, lg: 5 }}>
                        <Card sx={{ height: '100%' }}>
                            <CardContent>
                                <Stack spacing={2}>
                                    <Box
                                        sx={{
                                            p: 2,
                                            textAlign: 'center',
                                            borderRadius: 2,
                                            bgcolor: 'action.hover',
                                        }}
                                    >
                                        <Typography
                                            variant="overline"
                                            color="text.secondary"
                                        >
                                            {remaining >= 0
                                                ? t('diary.remaining')
                                                : t('diary.over_target')}
                                        </Typography>
                                        <Typography variant="h5">
                                            {formatNumber(
                                                Math.abs(remaining),
                                            )}{' '}
                                            kcal
                                        </Typography>
                                    </Box>
                                    <Box
                                        sx={{
                                            display: 'flex',
                                            width: 1,
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <CalorieRing
                                            value={totals.calories}
                                            target={targets.calories}
                                            progress={calorieProgress}
                                        />
                                    </Box>
                                    <MacroProgress
                                        label={t('common.protein')}
                                        type="protein"
                                        value={totals.protein}
                                        target={targets.protein}
                                    />
                                    <MacroProgress
                                        label={t('common.carbohydrates')}
                                        type="carbohydrates"
                                        value={totals.carbohydrates}
                                        target={targets.carbohydrates}
                                    />
                                    <MacroProgress
                                        label={t('common.fat')}
                                        type="fat"
                                        value={totals.fat}
                                        target={targets.fat}
                                    />
                                    <MacroProgress
                                        label={t('common.fibre')}
                                        type="fibre"
                                        value={totals.fibre}
                                        target={targets.fibre}
                                    />
                                </Stack>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid size={{ xs: 12, lg: 7 }}>
                        <Stack spacing={2}>
                            {meals.map((meal) => (
                                <MealCard
                                    key={meal.key}
                                    date={date}
                                    meal={meal}
                                    entries={entries.filter(
                                        (entry) => entry.meal === meal.key,
                                    )}
                                    onQuickAdd={() =>
                                        setQuickEntryMeal(meal.key)
                                    }
                                />
                            ))}
                        </Stack>
                    </Grid>
                </Grid>
                <DailyNotes date={date} notes={notes ?? ''} />
            </Box>
            {quickEntryMeal && (
                <QuickEntryDialog
                    key={`${date}-${quickEntryMeal}`}
                    date={date}
                    meal={quickEntryMeal}
                    mealLabel={t(
                        meals.find(({ key }) => key === quickEntryMeal)!
                            .labelKey,
                    )}
                    onClose={() => setQuickEntryMeal(null)}
                />
            )}
        </AppLayout>
    );
}

function CalorieRing({
    value,
    target,
    progress,
}: {
    value: number;
    target: number;
    progress: number;
}) {
    const { t } = useTranslation();

    return (
        <Box
            role="img"
            aria-label={t('diary.calorie_progress', { value, target })}
            sx={{
                position: 'relative',
                display: 'grid',
                width: 200,
                height: 200,
                flexShrink: 0,
                placeItems: 'center',
            }}
        >
            <CircularProgress
                variant="determinate"
                value={100}
                size={200}
                thickness={4}
                sx={{
                    position: 'absolute',
                    inset: 0,
                    m: 'auto',
                    color: 'primary.lighter',
                }}
            />
            <CircularProgress
                variant="determinate"
                value={progress}
                size={200}
                thickness={4}
                sx={{
                    color: 'primary.main',
                    position: 'absolute',
                    inset: 0,
                    m: 'auto',
                    filter: 'drop-shadow(0 6px 8px rgba(0,167,111,0.18))',
                }}
            />
            <Stack
                alignItems="center"
                justifyContent="center"
                sx={{ position: 'absolute', inset: 0 }}
            >
                <Typography variant="h3">{formatNumber(value)}</Typography>
                <Typography variant="caption" color="text.secondary">
                    {t('diary.of_calories', { target: formatNumber(target) })}
                </Typography>
            </Stack>
        </Box>
    );
}

function MealCard({
    date,
    meal,
    entries,
    onQuickAdd,
}: {
    date: string;
    meal: (typeof meals)[number];
    entries: DiaryEntry[];
    onQuickAdd: () => void;
}) {
    const { t } = useTranslation();
    const Icon = meal.icon;
    const total = entries.reduce((sum, entry) => sum + entry.calories, 0);
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const closeMenu = () => setAnchorEl(null);
    const openMenu = (event: MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };

    return (
        <Card
            id={`meal-${meal.key}`}
            sx={{ scrollMarginTop: { xs: 88, sm: 104 } }}
        >
            <CardHeader
                avatar={
                    <Box
                        sx={{
                            width: 40,
                            height: 40,
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: 1.5,
                            color: 'primary.main',
                            bgcolor: 'primary.lighter',
                        }}
                    >
                        <Icon />
                    </Box>
                }
                title={t(meal.labelKey)}
                subheader={`${formatNumber(total)} kcal`}
                action={
                    <Button
                        size="small"
                        variant="outlined"
                        startIcon={<AddRounded />}
                        onClick={openMenu}
                        aria-haspopup="menu"
                        aria-expanded={Boolean(anchorEl)}
                    >
                        {t('common.add')}
                    </Button>
                }
                sx={{ pb: 2 }}
            />
            <Menu
                anchorEl={anchorEl}
                open={Boolean(anchorEl)}
                onClose={closeMenu}
            >
                <MenuItem
                    onClick={() => {
                        closeMenu();
                        router.visit(
                            `/foods?date=${date}&meal=${meal.key}`,
                        );
                    }}
                >
                    <ListItemIcon>
                        <MenuBookOutlined fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>{t('diary.add_food')}</ListItemText>
                </MenuItem>
                <MenuItem
                    onClick={() => {
                        closeMenu();
                        onQuickAdd();
                    }}
                >
                    <ListItemIcon>
                        <EditNoteOutlined fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>{t('diary.quick_entry')}</ListItemText>
                </MenuItem>
                <MenuItem
                    onClick={() => {
                        closeMenu();
                        router.visit(
                            `/diary/${date}/ai-entry?meal=${meal.key}`,
                        );
                    }}
                >
                    <ListItemIcon>
                        <AutoAwesomeRounded fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>{t('diary.ai_entry')}</ListItemText>
                </MenuItem>
            </Menu>
            <Divider />
            {entries.length === 0 ? (
                <Box sx={{ p: 2, textAlign: 'center' }}>
                    <Typography variant="body2" color="text.secondary">
                        {t('diary.empty_meal')}
                    </Typography>
                </Box>
            ) : (
                <Stack divider={<Divider flexItem />}>
                    {entries.map((entry) => (
                        <DiaryEntryRow key={entry.id} entry={entry} />
                    ))}
                </Stack>
            )}
        </Card>
    );
}

function QuickEntryDialog({
    date,
    meal,
    mealLabel,
    onClose,
}: {
    date: string;
    meal: MealKey;
    mealLabel: string;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{
        date: string;
        meal: MealKey;
        name: string;
        calories: NumberInputValue;
        protein: NumberInputValue;
        carbohydrates: NumberInputValue;
        fat: NumberInputValue;
        fibre: NumberInputValue;
    }>({
        date,
        meal,
        name: '',
        calories: '',
        protein: '',
        carbohydrates: '',
        fat: '',
        fibre: '',
    });
    const numberValue = (value: string): NumberInputValue =>
        parseNumberInput(value);

    return (
        <ResponsiveDialog open onClose={onClose} maxWidth="sm" fullWidth>
            <Box
                component="form"
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    form.post('/diary-entries/quick', {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <DialogTitle>
                    {t('diary.quick_entry_title', { meal: mealLabel })}
                </DialogTitle>
                <DialogContent>
                    <Stack spacing={2}>
                        <Typography variant="body2" color="text.secondary">
                            {t('diary.quick_entry_description')}
                        </Typography>
                        <TextField
                            label={t('diary.entry_name')}
                            placeholder={t('diary.entry_name_placeholder')}
                            value={form.data.name}
                            error={Boolean(form.errors.name)}
                            helperText={
                                form.errors.name ??
                                t('diary.entry_name_help')
                            }
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <TextField
                            required
                            autoFocus
                            type="text"
                            label={t('common.calories')}
                            value={form.data.calories}
                            error={Boolean(form.errors.calories)}
                            helperText={form.errors.calories}
                            slotProps={{
                                input: {
                                    endAdornment: (
                                        <InputAdornment position="end">
                                            kcal
                                        </InputAdornment>
                                    ),
                                },
                                htmlInput: {
                                    inputMode: 'decimal',
                                    min: 0.01,
                                    max: 100000,
                                    step: 0.01,
                                },
                            }}
                            onChange={(event) =>
                                form.setData(
                                    'calories',
                                    numberValue(event.target.value),
                                )
                            }
                        />
                        <Box>
                            <Typography variant="subtitle2">
                                {t('diary.optional_macros')}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                            >
                                {t('diary.optional_macros_help')}
                            </Typography>
                        </Box>
                        <Grid container spacing={2}>
                            {(
                                [
                                    ['protein', t('common.protein')],
                                    [
                                        'carbohydrates',
                                        t('common.carbohydrates'),
                                    ],
                                    ['fat', t('common.fat')],
                                    ['fibre', t('common.fibre')],
                                ] as const
                            ).map(([key, label]) => (
                                <Grid key={key} size={{ xs: 12, sm: 6 }}>
                                    <TextField
                                        type="text"
                                        label={label}
                                        value={form.data[key]}
                                        error={Boolean(form.errors[key])}
                                        helperText={form.errors[key]}
                                        slotProps={{
                                            input: {
                                                endAdornment: (
                                                    <InputAdornment position="end">
                                                        g
                                                    </InputAdornment>
                                                ),
                                            },
                                            htmlInput: {
                                                inputMode: 'decimal',
                                                min: 0,
                                                max: 10000,
                                                step: 0.01,
                                            },
                                        }}
                                        onChange={(event) =>
                                            form.setData(
                                                key,
                                                numberValue(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                    />
                                </Grid>
                            ))}
                        </Grid>
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button
                        color="inherit"
                        disabled={form.processing}
                        onClick={onClose}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                    >
                        {t('diary.add_quick_entry')}
                    </Button>
                </DialogActions>
            </Box>
        </ResponsiveDialog>
    );
}

function DiaryEntryRow({ entry }: { entry: DiaryEntry }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);
    const form = useForm<{
        unit: MeasurementUnit;
        amount: NumberInputValue;
        quantity: NumberInputValue;
    }>({
        unit: entry.unit,
        amount: entry.amount,
        quantity: entry.quantity,
    });
    const hasMeasurement =
        entry.total_grams !== null || entry.total_milliliters !== null;
    const compatibleUnits = unitsForBasis(basisForUnit(entry.unit));
    const openEditor = () => {
        form.setData({
            unit: entry.unit,
            amount: entry.amount,
            quantity: entry.quantity,
        });
        form.clearErrors();
        setEditing(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/diary-entries/${entry.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <>
            <Stack spacing={2} sx={{ p: 2 }}>
                <Box sx={{ minWidth: 0 }}>
                    <Typography variant="subtitle2">
                        {entry.food_name} – {formatNumber(entry.calories)} kcal{' '}
                        <Typography
                            component="span"
                            variant="caption"
                            color="text.secondary"
                            sx={{ whiteSpace: 'nowrap' }}
                        >
                            (
                            {t('diary.macro_short', {
                                protein: formatNumber(entry.protein, 1),
                                carbs: formatNumber(
                                    entry.carbohydrates,
                                    1,
                                ),
                                fat: formatNumber(entry.fat, 1),
                                fibre: formatNumber(entry.fibre, 1),
                            })}
                            )
                        </Typography>
                    </Typography>
                    <Typography
                        variant="caption"
                        color="text.secondary"
                        sx={{ display: 'block', mt: 0.5 }}
                    >
                        {hasMeasurement
                            ? `${formatNumber(entry.quantity, 2)} × ${formatNumber(entry.amount, 2)} ${entry.unit}`
                            : t('diary.manual_entry')}
                        {entry.brand ? ` · ${entry.brand}` : ''}
                    </Typography>
                </Box>
                <Stack direction="row" spacing={1}>
                    {hasMeasurement && (
                        <Button
                            size="small"
                            variant="outlined"
                            startIcon={<EditOutlined />}
                            onClick={openEditor}
                        >
                            {t('diary.edit_amount')}
                        </Button>
                    )}
                    <Button
                        size="small"
                        color="error"
                        variant="text"
                        startIcon={<DeleteOutlineRounded />}
                        onClick={() =>
                            router.delete(`/diary-entries/${entry.id}`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        {t('diary.delete_entry')}
                    </Button>
                </Stack>
            </Stack>
            <ResponsiveDialog
                open={editing}
                onClose={() => setEditing(false)}
                maxWidth="xs"
                fullWidth
            >
                <Box component="form" onSubmit={submit}>
                    <DialogTitle>{t('diary.edit_amount')}</DialogTitle>
                    <DialogContent>
                        <Stack spacing={2}>
                            <Typography variant="body2">
                                {entry.food_name}
                            </Typography>
                            <TextField
                                select
                                label={t('common.unit')}
                                value={form.data.unit}
                                error={Boolean(form.errors.unit)}
                                helperText={form.errors.unit}
                                onChange={(event) =>
                                    form.setData(
                                        'unit',
                                        event.target
                                            .value as MeasurementUnit,
                                    )
                                }
                            >
                                {compatibleUnits.map((unit) => (
                                    <MenuItem key={unit} value={unit}>
                                        {unit}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                required
                                autoFocus
                                type="text"
                                label={t('common.amount')}
                                value={form.data.amount}
                                error={Boolean(form.errors.amount)}
                                helperText={form.errors.amount}
                                slotProps={{
                                    htmlInput: {
                                        inputMode: 'decimal',
                                        min: 0.01,
                                        max: 1000000,
                                        step: 0.01,
                                    },
                                }}
                                onChange={(event) =>
                                    form.setData(
                                        'amount',
                                        parseNumberInput(event.target.value),
                                    )
                                }
                            />
                            <TextField
                                required
                                type="text"
                                label={t('common.quantity')}
                                value={form.data.quantity}
                                error={Boolean(form.errors.quantity)}
                                helperText={
                                    form.errors.quantity ??
                                    t('diary.quantity_help')
                                }
                                slotProps={{
                                    htmlInput: {
                                        inputMode: 'decimal',
                                        min: 0.01,
                                        max: 1000,
                                        step: 0.01,
                                    },
                                }}
                                onChange={(event) =>
                                    form.setData(
                                        'quantity',
                                        parseNumberInput(event.target.value),
                                    )
                                }
                            />
                        </Stack>
                    </DialogContent>
                    <DialogActions>
                        <Button
                            color="inherit"
                            disabled={form.processing}
                            onClick={() => setEditing(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={
                                form.processing ||
                                form.data.amount === '' ||
                                form.data.quantity === ''
                            }
                        >
                            {t('diary.save_amount')}
                        </Button>
                    </DialogActions>
                </Box>
            </ResponsiveDialog>
        </>
    );
}

function DailyNotes({ date, notes }: { date: string; notes: string }) {
    const { t } = useTranslation();
    const form = useForm({ notes });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/diary/${date}/notes`, { preserveScroll: true });
    };

    return (
        <Card sx={{ mt: 2 }}>
            <CardHeader title={t('diary.daily_note')} />
            <CardContent>
                <Stack component="form" spacing={2} onSubmit={submit}>
                    <TextField
                        multiline
                        minRows={4}
                        placeholder={t('diary.note_placeholder')}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                        sx={{ alignSelf: 'flex-end' }}
                    >
                        {t('diary.save_note')}
                    </Button>
                </Stack>
            </CardContent>
        </Card>
    );
}
