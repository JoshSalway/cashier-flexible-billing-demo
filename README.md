# Cashier Flexible Billing Demo

Interactive demo app showcasing **Laravel Cashier's Flexible Billing** features. Each scenario runs against the **real Stripe API** with test keys, streaming results step-by-step as they execute.

Built with **Laravel 13** using the [Flexible Billing PR](https://github.com/JoshSalway/cashier-stripe/pull/5) for Laravel Cashier.

## Quick Start

```bash
git clone https://github.com/JoshSalway/cashier-flexible-billing-demo.git
cd cashier-flexible-billing-demo
composer install
cp .env.example .env
php artisan key:generate
```

Add your Stripe **test** keys to `.env`:

```env
STRIPE_KEY=pk_test_your_key_here
STRIPE_SECRET=sk_test_your_key_here
```

Then run:

```bash
php artisan migrate
php artisan serve
```

Visit **http://localhost:8000** and click **Run All 14 Scenarios**.

> Get your test keys from [Stripe Dashboard > Developers > API keys](https://dashboard.stripe.com/test/apikeys) (make sure Test mode is enabled).

## 14 Scenarios

### Core Flexible Billing (1-7)

| # | Scenario | What It Proves |
|---|----------|---------------|
| 1 | **Flexible Subscription** | Create a subscription with `billing_mode: flexible` and verify on Stripe |
| 2 | **Classic to Flexible Migration** | Migrate an existing subscription via Stripe's `/migrate` endpoint |
| 3 | **Hybrid Billing** | Fixed $29/mo base + $0.01/API call metered usage on one subscription |
| 4 | **Proration Discounts** | Compare `itemized` vs `included` proration modes |
| 5 | **Swap / Upgrade / Downgrade** | Change plans freely while billing mode stays flexible |
| 6 | **Global Default** | `Cashier::defaultBillingMode('flexible')` — one line, all new subs flexible |
| 7 | **Cancel and Resume** | Grace periods work, resume preserves billing mode |

### Subscription Schedules & Quotes (8-10)

| # | Scenario | What It Proves |
|---|----------|---------------|
| 8 | **Multi-Phase Schedule** | Starter to Pro automatic transition |
| 9 | **Quote Lifecycle** | Create, finalize, accept — subscription auto-created |
| 10 | **Schedule from Subscription** | Convert running sub to managed schedule |

### Usage Billing, Credits & Pricing (11-14)

| # | Scenario | What It Proves |
|---|----------|---------------|
| 11 | **Billing Credits** | Add credits, calculate against usage, deduct |
| 12 | **Metered Usage** | Report consumption events, track estimated charges |
| 13 | **Usage Thresholds** *(DB only)* | Monitor usage at 50%, 100%, 150% of limits |
| 14 | **Rate Card Pricing** *(local only)* | Graduated tiered, volume, package, flat calculations |

## How It Works

- Each scenario creates real Stripe objects (customers, subscriptions, etc.) using **test mode** keys
- Results stream live via Server-Sent Events — you see each step as it completes
- Subscriptions are cleaned up after each scenario (canceled)
- The demo app installs Cashier from the [flexible billing fork](https://github.com/JoshSalway/cashier-stripe/tree/feature/flexible-billing-complete)

## Use Your Own Stripe Keys

This demo is designed to work with **any** Stripe test account. Just update `.env` with your own `pk_test_` and `sk_test_` keys and you'll see the results in your own Stripe dashboard at [dashboard.stripe.com/test](https://dashboard.stripe.com/test).

## Deploy to Laravel Cloud

```bash
# From the Laravel Cloud dashboard, connect this repo and set:
# STRIPE_KEY=pk_test_...
# STRIPE_SECRET=sk_test_...
# Then deploy.
```

## Related

- **Cashier PR**: [JoshSalway/cashier-stripe#5](https://github.com/JoshSalway/cashier-stripe/pull/5)
- **Original PR**: [laravel/cashier-stripe#1772](https://github.com/laravel/cashier-stripe/pull/1772) by [@Diddyy](https://github.com/Diddyy)
- **Stripe Flexible Billing Docs**: [docs.stripe.com/billing/subscriptions/flexible-billing](https://docs.stripe.com/billing/subscriptions/flexible-billing)

## Credits

Based on the original flexible billing work in [PR #1772](https://github.com/laravel/cashier-stripe/pull/1772) by [@Diddyy](https://github.com/Diddyy) (Joshua Allen), with review feedback from [@crynobone](https://github.com/crynobone), [@yoeriboven](https://github.com/yoeriboven), [@j3j5](https://github.com/j3j5), and [@Arkitecht](https://github.com/Arkitecht).
