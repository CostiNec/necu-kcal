export function FieldError({ message }: { message?: string }) {
    if (!message) return null;

    return (
        <p className="text-xs font-semibold leading-5 text-destructive">
            {message}
        </p>
    );
}
