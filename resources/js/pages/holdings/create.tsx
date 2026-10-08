import { Form, Head, router } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faMagnifyingGlass } from '@fortawesome/free-solid-svg-icons';
import { useEffect, useRef, useState } from 'react';
import HoldingController from '@/actions/App/Http/Controllers/Portfolio/HoldingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatNav } from '@/lib/format';
import { create, index } from '@/routes/holdings';
import type { SchemeSearchResult } from '@/types/portfolio';

export default function HoldingsCreate({
    query,
    results,
}: {
    query: string;
    results: SchemeSearchResult[];
}) {
    const [search, setSearch] = useState(query);
    const [searching, setSearching] = useState(false);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(
                create.url(),
                search.trim() ? { q: search.trim() } : {},
                {
                    only: ['query', 'results'],
                    preserveState: true,
                    replace: true,
                    onStart: () => setSearching(true),
                    onFinish: () => setSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    const tooShort = search.trim().length < 2;

    return (
        <>
            <Head title="Add a fund" />

            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add a fund"
                    description="Search by scheme name, AMC or category words, e.g. “parag flexi direct growth”."
                />

                <div className="relative">
                    <FontAwesomeIcon
                        icon={faMagnifyingGlass}
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        autoFocus
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search mutual fund schemes"
                        className="pl-9"
                        aria-label="Search mutual fund schemes"
                    />
                    {searching && (
                        <Spinner className="absolute top-1/2 right-3 -translate-y-1/2" />
                    )}
                </div>

                {tooShort ? (
                    <p className="text-sm text-muted-foreground">
                        Type at least 2 characters.
                    </p>
                ) : results.length === 0 && !searching ? (
                    <p className="text-sm text-muted-foreground">
                        No active schemes match “{query}”.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {results.map((scheme) => (
                            <li
                                key={scheme.id}
                                className="flex flex-wrap items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="font-medium">{scheme.name}</p>
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span>{scheme.amc}</span>
                                        <span>·</span>
                                        <span>{scheme.category}</span>
                                        {scheme.plan && (
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {scheme.plan}
                                            </Badge>
                                        )}
                                    </div>
                                    {scheme.latest_nav &&
                                        scheme.latest_nav_date && (
                                            <p className="text-xs text-muted-foreground">
                                                NAV{' '}
                                                {formatNav(scheme.latest_nav)}{' '}
                                                on{' '}
                                                {formatDate(
                                                    scheme.latest_nav_date,
                                                )}
                                            </p>
                                        )}
                                </div>

                                {scheme.is_held ? (
                                    <Badge variant="secondary">
                                        <FontAwesomeIcon icon={faCheck} /> In
                                        portfolio
                                    </Badge>
                                ) : (
                                    <Form {...HoldingController.store.form()}>
                                        {({ processing, errors }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="scheme_id"
                                                    value={scheme.id}
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Add
                                                </Button>
                                                <InputError
                                                    message={errors.scheme_id}
                                                />
                                            </>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

HoldingsCreate.layout = {
    breadcrumbs: [
        { title: 'Portfolio', href: index() },
        { title: 'Add a fund', href: create() },
    ],
};
