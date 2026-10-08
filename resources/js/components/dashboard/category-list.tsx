import { ASSET_CLASS_COLORS } from '@/components/dashboard/asset-class-colors';
import { formatPaise, formatPercent } from '@/lib/format';
import type { Allocation } from '@/types/portfolio';

/**
 * Sub-categories ranked by value, each with a thin bar in its asset class's colour.
 */
export default function CategoryList({
    categories,
}: {
    categories: Allocation['categories'];
}) {
    return (
        <ul className="space-y-3">
            {categories.map((category) => (
                <li
                    key={`${category.asset_class}-${category.label}`}
                    className="space-y-1.5 text-sm"
                >
                    <div className="flex items-baseline gap-2">
                        <span className="min-w-0 truncate">
                            {category.label}
                        </span>
                        <span className="ml-auto font-medium tabular-nums">
                            {formatPercent(category.pct)}
                        </span>
                        <span className="w-28 shrink-0 text-right text-muted-foreground tabular-nums">
                            {formatPaise(category.value_paise)}
                        </span>
                    </div>
                    <div className="h-1.5 w-full rounded-full bg-muted">
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${category.pct}%`,
                                backgroundColor:
                                    ASSET_CLASS_COLORS[category.asset_class],
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
