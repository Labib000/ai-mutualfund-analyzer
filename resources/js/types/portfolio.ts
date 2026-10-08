export type SchemeSummary = {
    id: number;
    name: string;
    category: string;
    plan: 'direct' | 'regular' | null;
    latest_nav: string | null;
    latest_nav_date: string | null;
};

export type SchemeSearchResult = SchemeSummary & {
    amc: string;
    is_held: boolean;
};

export type XirrStatus =
    | 'ok'
    | 'short_period'
    | 'too_recent'
    | 'not_meaningful';

export type Performance = {
    units_held: string | null;
    invested_paise: number;
    value_paise: number;
    unrealised_gain_paise: number;
    realised_gain_paise: number;
    total_gain_paise: number;
    absolute_return_pct: number | null;
    xirr_pct: number | null;
    xirr_status: XirrStatus;
    valued_on: string | null;
};

export type HoldingSummary = {
    id: number;
    scheme: SchemeSummary;
    performance: Performance;
};

export type TransactionType = 'purchase' | 'sip_installment' | 'redemption';

export type PortfolioTransaction = {
    id: number;
    type: TransactionType;
    txn_date: string;
    nav_date: string;
    nav: string;
    amount_paise: number;
    stamp_duty_paise: number;
    units: string;
    units_overridden: boolean;
    from_sip: boolean;
};

export type PortfolioSip = {
    id: number;
    amount_paise: number;
    day_of_month: number;
    start_date: string;
    end_date: string | null;
    is_running: boolean;
    next_due: string | null;
    earliest_end_date: string;
};
