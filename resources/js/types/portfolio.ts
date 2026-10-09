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

export type HistoryPoint = {
    date: string;
    value_paise: number;
    invested_paise: number;
};

export type AssetClassKey =
    | 'equity'
    | 'debt'
    | 'hybrid'
    | 'solution_oriented'
    | 'other';

export type Allocation = {
    classes: {
        key: AssetClassKey;
        label: string;
        value_paise: number;
        pct: number;
    }[];
    categories: {
        label: string;
        asset_class: AssetClassKey;
        value_paise: number;
        pct: number;
    }[];
};

export type FundStats = {
    as_of: string;
    since: string;
    return_1y_pct: number | null;
    cagr_3y_pct: number | null;
    cagr_5y_pct: number | null;
    since_start_pct: number;
    since_start_annualised: boolean;
    volatility_pct: number | null;
    max_drawdown_pct: number;
    drawdown_peak: string | null;
    drawdown_trough: string | null;
};

export type Insight = {
    code: string;
    tone: 'attention' | 'info';
    title: string;
    detail: string;
};

export type ChangePeriod = '7d' | '30d' | 'month';

export type ChangeFund = {
    name: string;
    start_paise: number;
    end_paise: number;
    net_flow_paise: number;
    market_paise: number;
    market_pct: number | null;
};

export type PortfolioChange = {
    period: ChangePeriod;
    label: string;
    from: string;
    to: string;
    start_paise: number;
    end_paise: number;
    net_flow_paise: number;
    market_paise: number;
    market_pct: number | null;
    sip_installments: number;
    best: ChangeFund | null;
    worst: ChangeFund | null;
    funds: ChangeFund[];
};
