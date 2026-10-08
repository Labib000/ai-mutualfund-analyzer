import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import SipController from '@/actions/App/Http/Controllers/Portfolio/SipController';
import FormField from '@/components/portfolio/form-field';
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
import { Input } from '@/components/ui/input';
import { paiseToRupeeInput } from '@/lib/format';
import type { PortfolioSip } from '@/types/portfolio';

/**
 * Create a SIP, or edit one when `sip` is given. Editing changes only future installments.
 */
export default function SipDialog({
    holdingId,
    today,
    sip,
    trigger,
}: {
    holdingId: number;
    today: string;
    sip?: PortfolioSip;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = sip
        ? SipController.update.form([holdingId, sip.id])
        : SipController.store.form(holdingId);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{sip ? 'Edit SIP' : 'Add a SIP'}</DialogTitle>
                <DialogDescription>
                    {sip
                        ? 'Changes apply to future installments only. Installments already recorded stay as they are.'
                        : 'Past installments are recorded straight away. New ones are added automatically once each NAV is published.'}
                </DialogDescription>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess={!sip}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                id="sip-amount"
                                label="Monthly amount (₹)"
                                error={errors.amount}
                            >
                                <Input
                                    id="sip-amount"
                                    name="amount"
                                    inputMode="decimal"
                                    placeholder="5000"
                                    defaultValue={
                                        sip
                                            ? paiseToRupeeInput(
                                                  sip.amount_paise,
                                              )
                                            : undefined
                                    }
                                    required
                                />
                            </FormField>

                            <FormField
                                id="sip-day"
                                label="Day of the month"
                                hint="Days 29–31 fall on the last day of shorter months."
                                error={errors.day_of_month}
                            >
                                <Input
                                    id="sip-day"
                                    name="day_of_month"
                                    type="number"
                                    min={1}
                                    max={31}
                                    defaultValue={sip?.day_of_month ?? 5}
                                    required
                                />
                            </FormField>

                            {!sip && (
                                <FormField
                                    id="sip-start"
                                    label="Start date"
                                    error={errors.start_date}
                                >
                                    <Input
                                        id="sip-start"
                                        name="start_date"
                                        type="date"
                                        defaultValue={today}
                                        required
                                    />
                                </FormField>
                            )}

                            <FormField
                                id="sip-end"
                                label="End date (optional)"
                                error={errors.end_date}
                            >
                                <Input
                                    id="sip-end"
                                    name="end_date"
                                    type="date"
                                    min={sip?.earliest_end_date}
                                    defaultValue={sip?.end_date ?? undefined}
                                />
                            </FormField>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {sip ? 'Save changes' : 'Save SIP'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
