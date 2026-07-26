import FormHelperText from '@mui/material/FormHelperText';

export function FieldError({ message }: { message?: string }) {
    if (!message) return null;

    return <FormHelperText error>{message}</FormHelperText>;
}
