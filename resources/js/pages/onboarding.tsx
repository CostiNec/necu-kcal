import { Head, useForm, usePage } from '@inertiajs/react';
import ArrowForwardRounded from '@mui/icons-material/ArrowForwardRounded';
import CheckCircleRounded from '@mui/icons-material/CheckCircleRounded';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardHeader from '@mui/material/CardHeader';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Container from '@mui/material/Container';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { motion } from 'framer-motion';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import {
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import type { NutritionTargets, SharedProps } from '@/types';

export default function Onboarding({
    profile,
    targets,
}: {
    profile: { timezone: string } | null;
    targets: NutritionTargets | null;
}) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const form = useForm<{
        name: string;
        calories: NumberInputValue;
        protein: NumberInputValue;
        carbohydrates: NumberInputValue;
        fat: NumberInputValue;
        fibre: NumberInputValue;
        timezone: string;
    }>({
        name: auth.user?.name ?? '',
        calories: targets?.calories ?? 2000,
        protein: targets?.protein ?? 120,
        carbohydrates: targets?.carbohydrates ?? 220,
        fat: targets?.fat ?? 65,
        fibre: targets?.fibre ?? 30,
        timezone: profile?.timezone ?? 'Europe/Bucharest',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/onboarding');
    };

    return (
        <Box component="main" sx={{ minHeight: '100vh', py: { xs: 2, sm: 5 } }}>
            <Head title={t('onboarding.head_title')} />
            <Container maxWidth="md" component={motion.div} initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }}>
                <Stack direction="row" alignItems="center" justifyContent="space-between">
                    <BrandMark />
                    <LanguageSwitcher compact />
                </Stack>
                <Box sx={{ my: { xs: 5, sm: 7 } }}>
                    <Chip label={t('onboarding.step')} color="primary" variant="outlined" />
                    <Typography variant="h3" sx={{ mt: 2 }}>
                        {t('onboarding.title')}
                    </Typography>
                    <Typography color="text.secondary" sx={{ mt: 1.5, maxWidth: 620 }}>
                        {t('onboarding.description')}
                    </Typography>
                </Box>

                <Stack component="form" spacing={2.5} onSubmit={submit}>
                    <Card>
                        <CardHeader title={t('onboarding.card_title')} />
                        <CardContent>
                            <Stack spacing={3}>
                                <TextField
                                    label={t('onboarding.name_question')}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    error={Boolean(form.errors.name)}
                                    helperText={form.errors.name}
                                />
                                <Grid container spacing={2.5}>
                                    <TargetInput
                                        label={t('common.calories')}
                                        suffix="kcal"
                                        value={form.data.calories}
                                        error={form.errors.calories}
                                        onChange={(value) =>
                                            form.setData('calories', value)
                                        }
                                    />
                                    <TargetInput
                                        label={t('common.protein')}
                                        suffix={t('common.grams')}
                                        value={form.data.protein}
                                        error={form.errors.protein}
                                        onChange={(value) =>
                                            form.setData('protein', value)
                                        }
                                    />
                                    <TargetInput
                                        label={t('common.carbohydrates')}
                                        suffix={t('common.grams')}
                                        value={form.data.carbohydrates}
                                        error={form.errors.carbohydrates}
                                        onChange={(value) =>
                                            form.setData('carbohydrates', value)
                                        }
                                    />
                                    <TargetInput
                                        label={t('common.fat')}
                                        suffix={t('common.grams')}
                                        value={form.data.fat}
                                        error={form.errors.fat}
                                        onChange={(value) =>
                                            form.setData('fat', value)
                                        }
                                    />
                                    <TargetInput
                                        label={t('common.fibre')}
                                        suffix={t('common.grams')}
                                        value={form.data.fibre}
                                        error={form.errors.fibre}
                                        onChange={(value) =>
                                            form.setData('fibre', value)
                                        }
                                    />
                                </Grid>
                                <Alert
                                    severity="success"
                                    icon={<CheckCircleRounded />}
                                >
                                    {t('onboarding.hint')}
                                </Alert>
                            </Stack>
                        </CardContent>
                    </Card>
                    <Button
                        size="large"
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                        endIcon={
                            form.processing ? (
                                <CircularProgress size={18} color="inherit" />
                            ) : (
                                <ArrowForwardRounded />
                            )
                        }
                    >
                        {t('onboarding.open_diary')}
                    </Button>
                </Stack>
            </Container>
        </Box>
    );
}

function TargetInput({
    label,
    suffix,
    value,
    error,
    onChange,
}: {
    label: string;
    suffix: string;
    value: NumberInputValue;
    error?: string;
    onChange: (value: NumberInputValue) => void;
}) {
    return (
        <Grid size={{ xs: 12, sm: 6 }}>
            <TextField
                label={label}
                type="number"
                value={value}
                onChange={(event) =>
                    onChange(parseNumberInput(event.target.value))
                }
                error={Boolean(error)}
                helperText={error}
                slotProps={{
                    htmlInput: { min: 0 },
                    input: {
                        endAdornment: (
                            <InputAdornment position="end">{suffix}</InputAdornment>
                        ),
                    },
                }}
            />
        </Grid>
    );
}
