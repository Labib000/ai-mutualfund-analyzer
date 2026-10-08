import { Form } from '@inertiajs/react';
import type { RouteFormDefinition } from '@/wayfinder';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Asks for confirmation, then submits the given Wayfinder form. Validation
 * errors are shown on the page, so the dialog closes either way.
 */
export default function ConfirmDialog({
    form,
    title,
    description,
    confirmLabel,
    destructive = true,
    trigger,
}: {
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onFinish={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant={
                                    destructive ? 'destructive' : 'default'
                                }
                                disabled={processing}
                            >
                                {confirmLabel}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
