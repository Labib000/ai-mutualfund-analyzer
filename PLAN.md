# Hisaab — Project Plan

Hisaab is a personal mutual fund tracker for Indian investors. Users add the funds they hold and their SIPs, Hisaab fetches official NAVs, calculates returns (including XIRR), explains the portfolio in plain language using AI, and offers a paid Pro plan through Razorpay.

This is a personal portfolio project built to demonstrate senior-level engineering: correct financial calculations, clean data modelling, safe AI integration, and a production-grade payment flow.

> **Revision 2 (2026-10-08).** Updated after the Phase 0 review: Laravel React starter kit instead of Breeze, PHPUnit, MariaDB, next-business-day NAV for purchases, stamp duty in unit calculation, redemptions entered in units, a `payment_events` log, a Pro-expiry rule, Excel-compatible XIRR, a SIP rule for days 29–31, and deferred `portfolio_snapshots`. Phase 1b added the `holdings` table and the transaction rules below.

---

## Working agreement for Claude Code

- **Discuss before coding.** At the start of each phase, propose the approach (files, tables, classes, trade-offs) and wait for approval before writing code.
- Work one phase at a time. Do not start the next phase until the current one meets its acceptance criteria.
- Ask before adding any Composer or npm package that is not listed in this plan.
- Write tests alongside features, especially for calculations, the payment flow and plan limits.
- Keep commits small and focused, with clear messages.
- If something in this plan is wrong or unclear, say so instead of working around it.

---

## Constraints

- **Hosting: Hostinger Business shared hosting.** No VPS, no Docker in production, no Redis, no long-running processes, no Python.
    - Queues use the **database** driver, drained by the scheduler (`queue:work --stop-when-empty`).
    - Scheduling uses one cron entry running `php artisan schedule:run` every minute.
    - Frontend assets are built locally (`npm run build`) and deployed as compiled files.
- **Money is stored as integers in paise.** Never use floats for money.
- **NAVs and units use fixed-precision decimals** (NAV `DECIMAL(12,4)`, units `DECIMAL(15,3)`). Arithmetic on them uses `bcmath` strings, never floats.
- **No investment advice.** The app analyzes and explains; it never recommends buying, selling or switching. Show a disclaimer wherever AI output appears.

---

## Tech stack

| Layer    | Choice                                                                                                    |
| -------- | --------------------------------------------------------------------------------------------------------- |
| Backend  | Laravel 13, PHP 8.3+ (confirm the Hostinger version in hPanel)                                            |
| Frontend | React + TypeScript via Inertia.js (single deployable app)                                                 |
| Starter  | Laravel React starter kit (Fortify auth, shadcn/Radix UI, Wayfinder)                                      |
| Styling  | Tailwind CSS 4                                                                                            |
| Charts   | Recharts                                                                                                  |
| Database | MariaDB (matches Hostinger); tests run on a real MariaDB database, not SQLite                             |
| Tests    | PHPUnit, Larastan, Pint                                                                                   |
| AI       | LLM API (Claude or similar) behind an interface                                                           |
| Payments | Razorpay (test mode during development), official PHP SDK                                                 |
| NAV data | AMFI daily NAV file; historical NAVs from mfapi.in behind `NavProvider` (AMFI history report as fallback) |

---

## Scope

### In scope (v1)

- Accounts and authentication
- Search and add mutual fund schemes
- Record lump-sum purchases, redemptions and SIPs
- Daily NAV updates
- Portfolio dashboard: invested, current value, gain, absolute return, XIRR (per fund and overall)
- AI portfolio summary, "ask your portfolio" Q&A, fund explainer
- Razorpay Pro plan with usage limits on funds and AI

### Out of scope (later versions)

- CAS PDF upload and parsing
- Capital gains (FIFO, STCG/LTCG)
- Fund overlap analysis
- Family or multiple portfolios
- Direct stocks, ETFs, FDs
- Mobile app
- Recurring Razorpay Subscriptions (v1 uses one-time payments)

---

## Data model (proposal — review before migrating)

- **users** — standard Laravel users.
- **schemes** — `amfi_code` (unique), `isin`, `name`, `amc`, `category`, `is_active`.
- **nav_history** — `scheme_id`, `nav_date`, `nav` DECIMAL(12,4). Unique on (`scheme_id`, `nav_date`). Store history only for schemes users actually hold.
- **holdings** — `user_id`, `scheme_id`; unique per user and scheme. A "fund" in the portfolio: transactions and SIPs belong to it, ownership is checked on it, and Free-plan fund limits count it.
- **sips** — `holding_id`, `amount_paise`, `day_of_month` (1–31), `start_date`, `end_date` (nullable; stopping sets it to today), `generated_until` (date of the last installment recorded, so edits only affect future installments).
- **transactions** — `holding_id`, `sip_id` (nullable; unique with `txn_date`), `type` (purchase, sip_installment, redemption), `txn_date`, `nav_date` (the date whose NAV was applied), `amount_paise`, `stamp_duty_paise`, `nav` DECIMAL(12,4), `units` DECIMAL(15,3), `units_overridden` (bool).
- **portfolio_snapshots** — _not needed._ Returns are calculated per request (about 80 ms for 20 funds with 10-year SIPs). Only the dashboard's weekly value history is cached, as one cache entry per user replaced whenever a transaction or a held scheme's NAV changes.
- **plans** — `code` (free, pro), `price_paise`, `duration_days`, `max_funds` (nullable = unlimited), `ai_requests_per_month`.
- **user_plans** — `user_id`, `plan_id`, `starts_at`, `ends_at`, `status`.
- **payments** — one row per Razorpay order: `user_id`, `plan_id`, `razorpay_order_id` (unique), `razorpay_payment_id`, `amount_paise`, `status` (current state, for fast queries).
- **payment_events** — append-only log of every status change: `payment_id`, `status`, `source` (checkout_callback, webhook, system), `payload` (JSON), `created_at`. Never updated or deleted. This is the audit trail; `payments.status` is derived from it.
- **webhook_events** — `event_id` (unique), `event_type`, `payload` (JSON), `processed_at`. Used for idempotency.
- **ai_usage** — `user_id`, `feature`, `input_tokens`, `output_tokens`, `created_at`. Used for limits and cost tracking.

### Calculation rules

- **NAV applied to purchases, redemptions and SIP installments:** the NAV of the transaction date if it's a business day, otherwise the **next** business day's NAV. Weekend NAVs that some funds publish at month-end are skipped for transactions (they're used only for valuation). A transaction whose NAV isn't published yet is rejected; SIP installments wait for it.
- **NAV used to value a holding on a date:** the latest NAV on or **before** that date.
- **Units on purchase:** stamp duty = 0.005% of the amount (rounded to the nearest paisa); units = (amount − stamp duty) ÷ NAV, rounded to 3 decimals. Users may override units to match their statement; overridden rows are flagged.
- **Redemptions** are entered in **units**, with an "all units" option, since exit load and TDS make the amount received differ from units × NAV. The amount received is optional and is used as the cash flow when given; otherwise units × NAV.
- **SIP day 29–31:** in shorter months the installment falls on the last day of the month.
- **Corrections:** transactions are created and deleted, not edited. Deleting a transaction or SIP is blocked if a later redemption would then sell more units than were held.
- **Time zone:** the app runs on Asia/Kolkata time, so "today" and all date rules follow IST.

---

## Phase 0 — Setup

- Laravel React starter kit, Tailwind, MariaDB.
- Configure the database queue driver and the scheduler.
- PHPUnit, Larastan and Pint; GitHub Actions CI (install, lint, type-check, test) against a MariaDB service; README skeleton.
- `.env.example` with all required keys (DB, AI provider, Razorpay test keys).

**Done when:** the app runs locally, auth works, tests run in CI.

---

## Phase 1 — Core tracker

### 1a. Scheme master and NAVs

- Command to import the AMFI scheme list and latest NAVs from the AMFI daily NAV file.
- Scheduled job to refresh latest NAVs daily (AMFI publishes late evening IST; schedule a nightly run plus a morning retry).
- Historical NAV lookup through a `NavProvider` interface, fetched on demand when a user adds a past transaction, then cached in `nav_history`.
- Non-trading days follow the calculation rules above (next NAV for purchases, previous NAV for valuation), and the transaction records which date was used (`nav_date`).

### 1b. Portfolio entry

- Search schemes by name.
- Add a lump-sum purchase (date + amount; units calculated, with optional override) or a redemption (date + units, or "all units").
- Add a SIP (amount, day of month, start date, optional end date). A daily scheduled job generates installment transactions up to today. Editing or stopping a SIP affects only future installments.
- Validation: no future dates, redemptions cannot exceed units held as of the redemption date.

### 1c. Calculations

- Per fund and overall: invested, units held, current value, unrealised and realised gain, absolute return %, XIRR.
- **Invested** is the **FIFO cost of units still held** (oldest units are sold first, as on registrar statements). A redemption's proceeds minus the FIFO cost of the units it sold is its **realised gain**. Absolute return % = unrealised gain ÷ invested.
- **XIRR:** cash flows are negative for purchases, positive for redemptions, plus current value as a positive flow on the latest NAV date. Use Excel's convention (each year is 365 days, time measured from the first cash flow) so results match spreadsheet `XIRR()`. Solve with Newton-Raphson, with a bisection fallback when it doesn't converge.
- **When XIRR is shown:** "—" until the cash flows span 30 days; from 30 days to a year it's shown and marked as annualised from a short period; from a year it's shown plainly. The portfolio XIRR runs over all funds' flows together.
- Calculation logic lives in plain, framework-independent classes so it can be unit tested thoroughly.

### 1d. Dashboard

- Summary cards (invested, current value, gain, XIRR).
- Top holdings table (full list on the Portfolio page).
- Value-over-time chart: weekly current value against FIFO invested, with 3M / 6M / 1Y / 3Y / All ranges and a table view.
- Allocation by asset class (Equity, Debt, Hybrid, Solution oriented, Other, mapped from AMFI categories including legacy names) and by sub-category.
- Chart colours come from a palette validated for colour-blind separation in light and dark mode.

**Status: Phase 1 complete (October 2026).**

**Done when:** a user can add funds and SIPs, see accurate values and XIRR that match a spreadsheet `XIRR()` for the same cash flows, and NAVs update automatically. XIRR and SIP generation have unit tests including edge cases.

---

## Phase 2 — AI features

- Define an `AiProvider` interface; one concrete implementation; a fake implementation for tests.
- **Portfolio summary:** send only computed figures (fund names, categories, invested, value, returns) — never personal identifiers. Returns a short plain-language summary.
- **Ask your portfolio:** a chat box. The model answers using a structured summary of the user's own portfolio data passed in the prompt. The model does not write or run database queries.
- **Fund explainer:** plain-language explanation of a scheme (category, what it invests in, risk level, what the expense ratio means).
- System prompt rules: explain and analyze only; never recommend buying, selling or switching; say clearly when data is insufficient.
- Disclaimer shown on every AI output.
- Log each call to `ai_usage`. Timeouts and provider errors show a friendly message, not a crash.
- Requests run synchronously with a strict timeout and a loading state (shared hosting can't hold long connections or streams reliably).

**Done when:** all three features work, usage is recorded, failures are handled gracefully, and tests use the fake provider.

**As built:**

- **Provider:** Groq's free tier (`openai/gpt-oss-120b`, low reasoning effort) through `GroqProvider`, using Laravel's HTTP client and no SDK. Claude Opus 5.5 is the planned upgrade: add a `ClaudeProvider` and set `AI_PROVIDER`.
- **Free-tier limits:** about 8K tokens a minute across the app, roughly two requests a minute. A busy provider shows a friendly "try again in a minute".
- **Limits per user:** 20 successful AI requests per IST calendar month (`AI_MONTHLY_REQUESTS_PER_USER`) and 5 per minute. Failed calls and cached answers don't count.
- **Caching:**
    - the summary is cached per user until the portfolio, its SIPs or the model change (or for 24 hours);
    - fund explanations are cached per scheme for 30 days and shared by all users.
- **Chat history** lives only in the browser; the last 6 turns go with each question.
- **Rendering:** answers are plain text, shown with a fixed disclaimer; nothing is rendered as HTML.

### 2b. Richer AI input (October 2026)

The first AI version only saw per-fund totals, so its answers restated numbers. Phase 2b gives it real facts. **Our code computes every figure and finding** (unit tested in `app/Portfolio/`), **and the AI only narrates.** The deterministic parts show without AI and use no quota.

- **Fund facts** (`FundStats`, cached per scheme by `SchemeStats`):
    - Computed from the stored NAV history:
        - trailing 1Y, 3Y and 5Y returns (CAGR over a year);
        - the return since the first stored NAV;
        - volatility (annualised spread of weekly returns, last 3 years);
        - the largest fall from a peak.
    - NAV ratios use bcmath; only the resulting rates are floats.
    - The figures appear on the holding page and in the fund explainer and portfolio context.
- **Insights** (`Insights`): fixed rules that find:
    - funds below cost;
    - Regular plans (stated as a fact about commission and expense ratio);
    - one fund above 40% of value, or one fund house above 50%;
    - a single asset class;
    - funds sharing a category;
    - funds idle for over 12 months;
    - a short XIRR history.

    They're shown on the dashboard, sent to the AI summary as observations, and turned into suggested questions on the Ask page.
- **What changed** (`PortfolioChange`):
    - Splits the change over 7 days, 30 days or month to date into new money (purchases and SIPs minus redemptions) and market movement, per fund and in total.
    - Values each end of the period at the latest NAV on or before it.
    - A dashboard card shows the figures; "Explain this" calls `ai/digest`, which is cached per user and period.
- **Smarter Ask:**
    - The context adds fund facts, observations, cash flow by financial year (April to March), and the last 30 days' change (totals and biggest movers only).
    - The system prompt asks the model to quote figures.
    - A unit test keeps a 20-fund portfolio's context under 13,000 characters (about 3.2K tokens) to fit Groq's free-tier limit.

**Status: Phase 2 complete (October 2026).**

---

## Phase 3 — Razorpay Pro plan

### Plans

- **Free:** up to 3 funds, a small monthly AI quota.
- **Pro:** unlimited funds, higher AI quota, downloadable PDF portfolio report. One-time payment for a fixed duration (e.g. 30 or 365 days).
- Limits are enforced server-side (policies or middleware), not only hidden in the UI.
- **When Pro expires** and the user holds more funds than the Free limit: existing funds stay visible and editable (transactions, SIPs), but the user cannot add new funds until they're within the limit or renew.

### Payment flow

1. User clicks Upgrade → server creates a Razorpay Order with the amount in paise, stores a `payments` row and a `created` event.
2. Razorpay Checkout opens in the browser.
3. On success, the browser sends the payment ID, order ID and signature to the server, which **verifies the signature server-side**, records an event and shows a "processing" state.
4. The **webhook** (`payment.captured`) is the source of truth: verify the webhook signature, check `webhook_events` for the event ID (idempotency), then activate the plan in `user_plans` inside a database transaction.
5. Handle failed, abandoned and duplicate payments, and webhooks that arrive before or after the browser callback.

- Webhook route excluded from CSRF and requires HTTPS.
- Scheduled job expires plans past `ends_at`.
- Billing history page from `payments` and `payment_events`.

**Done when:** a full test-mode purchase activates Pro, limits change accordingly, replayed webhooks don't double-activate, plans expire correctly, and the flow has feature tests with mocked Razorpay calls.

---

## Phase 4 — Polish and deploy

- PDF portfolio report (Pro only), generated through the queue.
- Empty states, loading states, error pages, mobile-responsive layout.
- Security pass: authorization on every user-owned record, rate limits on AI and payment endpoints, secrets only in `.env`.
- Deploy to Hostinger: upload build, set `.env`, run migrations over SSH, storage link, cron entry for `schedule:run`, Razorpay webhook URL set in the dashboard.
- README: what it does, screenshots, architecture diagram, setup steps, and a **Decisions** section explaining key trade-offs.

**Done when:** the app is live, the README explains the design, and a test-mode payment works end to end in production.

---

## Open questions

1. ~~Historical NAV source~~ — mfapi.in first, AMFI history report as fallback (verify both before Phase 1a).
2. Pro plan pricing and duration.
3. Free-tier limits (number of funds, AI requests per month).
4. ~~Unit override~~ — allowed, flagged with `units_overridden`.
5. ~~AI provider and budget~~: Groq free tier for now; Claude Opus 5.5 once there's a budget.
6. Hostinger's PHP and MariaDB versions (pin CI to match).
