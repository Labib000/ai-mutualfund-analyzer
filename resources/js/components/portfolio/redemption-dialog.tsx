import { faMoneyBillTransfer } from '@fortawesome/free-solid-svg-icons';
import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TransactionController from '@/actions/App/Http/Controllers/Portfolio/TransactionController';
import FormField from '@/components/portfolio/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogBody,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function RedemptionDialog({
    holdingId,
    today,
    disabled,
}: {
    holdingId: number;
    today: string;
    disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [allUnits, setAllUnits] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setAllUnits(false);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" disabled={disabled}>
                    Add redemption
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader icon={faMoneyBillTransfer}>
                    <DialogTitle>Add a redemption</DialogTitle>
                    <DialogDescription>
                        Enter the units you sold, as shown on your statement.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...TransactionController.storeRedemption.form(holdingId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogBody className="space-y-4">
                                <FormField
                                    id="redemption-date"
                                    label="Date"
                                    error={errors.txn_date}
                                >
                                    <Input
                                        id="redemption-date"
                                        name="txn_date"
                                        type="date"
                                        max={today}
                                        defaultValue={today}
                                        required
                                    />
                                </FormField>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="redemption-all"
                                        name="all_units"
                                        value="1"
                                        checked={allUnits}
                                        onCheckedChange={(checked) =>
                                            setAllUnits(checked === true)
                                        }
                                    />
                                    <Label htmlFor="redemption-all">
                                        Redeem all units held on that date
                                    </Label>
                                </div>

                                {!allUnits && (
                                    <FormField
                                        id="redemption-units"
                                        label="Units"
                                        error={errors.units}
                                    >
                                        <Input
                                            id="redemption-units"
                                            name="units"
                                            inputMode="decimal"
                                            placeholder="100.000"
                                            required
                                        />
                                    </FormField>
                                )}
                                {allUnits && errors.units && (
                                    <p className="text-sm text-red-600 dark:text-red-400">
                                        {errors.units}
                                    </p>
                                )}

                                <FormField
                                    id="redemption-amount"
                                    label="Amount received (₹, optional)"
                                    hint="Leave blank to use units × NAV. Enter it if exit load or tax was deducted."
                                    error={errors.amount}
                                >
                                    <Input
                                        id="redemption-amount"
                                        name="amount"
                                        inputMode="decimal"
                                    />
                                </FormField>
                            </DialogBody>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save redemption
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
