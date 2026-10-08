export default function RemainingNote({
    remaining,
}: {
    remaining: number | null;
}) {
    if (remaining === null) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            {remaining === 1
                ? '1 AI request left this month'
                : `${remaining} AI requests left this month`}
        </p>
    );
}
