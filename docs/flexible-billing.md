# Laravel Cashier — Flexible Billing Guide

This guide covers everything you need to know to use flexible billing in your Laravel application with Cashier.

## Table of Contents

- [Installation](#installation)
- [Getting Started](#getting-started)
- [Creating Flexible Subscriptions](#creating-flexible-subscriptions)
- [Hybrid Billing (Fixed + Metered)](#hybrid-billing-fixed--metered)
- [Proration Discounts](#proration-discounts)
- [Swapping Plans](#swapping-plans)
- [Cancel and Resume](#cancel-and-resume)
- [Subscription Schedules](#subscription-schedules)
- [Quotes](#quotes)
- [Billing Credits](#billing-credits)
- [Metered Usage Reporting](#metered-usage-reporting)
- [Usage Thresholds](#usage-thresholds)
- [Rate Cards](#rate-cards)
- [Migrating from Classic to Flexible](#migrating-from-classic-to-flexible)
- [Webhook Handling](#webhook-handling)
- [Configuration Reference](#configuration-reference)

---

## Installation

Publish and run the Cashier migrations:

```bash
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

This creates the standard Cashier tables plus:
- `subscription_schedules` — for multi-phase subscription management
- `cashier_quotes` — for quote lifecycle tracking
- `cashier_usage_thresholds` — for usage monitoring
- `cashier_rate_cards` — for local pricing models

Add the `Billable` trait to your User model:

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

## Getting Started

### Setting the Global Default

The simplest way to enable flexible billing for your entire application is to set the global default in your `AppServiceProvider`:

```php
use Laravel\Cashier\Cashier;

public function boot(): void
{
    Cashier::defaultBillingMode('flexible');
}
```

After this, every new subscription created through Cashier will use flexible billing automatically — no other code changes needed.

### Per-Subscription Override

If you prefer to opt in per subscription, use `withBillingMode()`:

```php
$user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->create($paymentMethod);
```

You can also override the global default for a specific subscription:

```php
// Global default is flexible, but this one uses classic
$user->newSubscription('legacy', $priceId)
    ->withBillingMode('classic')
    ->create($paymentMethod);
```

## Creating Flexible Subscriptions

### Basic Subscription

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->create('pm_card_visa');
```

### With a Trial Period

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->trialDays(14)
    ->create('pm_card_visa');
```

### Via Checkout

```php
$checkout = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->checkout([
        'success_url' => route('billing.success'),
        'cancel_url' => route('billing.cancel'),
    ]);

return redirect($checkout->url);
```

### Checking Billing Mode

```php
if ($subscription->usesFlexibleBilling()) {
    // This subscription is on flexible billing
}
```

## Hybrid Billing (Fixed + Metered)

Combine a fixed monthly fee with usage-based charges on a single subscription:

```php
$subscription = $user->newSubscription('default')
    ->price('price_base_plan')           // $29/mo fixed
    ->meteredPrice('price_api_calls')    // $0.01 per API call
    ->withBillingMode('flexible')
    ->create('pm_card_visa');
```

This creates one subscription with two items. The base plan charges monthly, and the metered price charges based on reported usage.

### Removing a Metered Price

In flexible billing mode, you can remove metered prices without the `clear_usage` errors that occur in classic mode:

```php
$subscription->removePrice('price_api_calls');
```

### Adding a Metered Price Later

```php
$subscription->addMeteredPrice('price_storage_gb');
```

## Proration Discounts

Flexible billing gives you control over how prorations appear on invoices:

### Itemized Mode

Discount amounts are shown as separate line items on invoices, giving customers full transparency:

```php
$subscription = $user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->withProrationDiscounts('itemized')
    ->create('pm_card_visa');
```

### Included Mode

Amounts are net of discounts — the traditional behavior:

```php
$subscription = $user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->withProrationDiscounts('included')
    ->create('pm_card_visa');
```

## Swapping Plans

Plan swaps work the same as classic mode. The billing mode is preserved automatically:

```php
// Upgrade
$subscription->swap('price_premium');

// Downgrade
$subscription->swap('price_starter');

// The subscription stays on flexible billing through all swaps
$subscription->usesFlexibleBilling(); // true
```

## Cancel and Resume

Grace periods and resumption work identically to classic mode:

```php
// Cancel at end of billing period (grace period)
$subscription->cancel();

$subscription->onGracePeriod();  // true
$subscription->valid();          // true — still active until period ends

// Resume before grace period expires
$subscription->resume();

$subscription->active();                // true
$subscription->usesFlexibleBilling();   // true — preserved
```

### Cancel Immediately

```php
$subscription->cancelNow();

// Or cancel and invoice for usage
$subscription->cancelNowAndInvoice();
```

## Subscription Schedules

Pre-plan subscription transitions with multi-phase schedules. Useful for:
- Trial-to-paid conversions
- Annual discount periods
- Staged enterprise onboarding

### Creating a Schedule

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->withBillingMode('flexible')
    ->addPhase([
        ['price' => 'price_starter', 'quantity' => 1],
    ], ['iterations' => 3])  // 3 months on starter
    ->addPhase([
        ['price' => 'price_pro', 'quantity' => 1],
    ], ['iterations' => 12]) // Then 12 months on pro
    ->startDate('now')
    ->create();
```

### From an Existing Subscription

Convert a running subscription into a managed schedule:

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->createFromSubscription($subscription);

// The schedule inherits the subscription's billing mode automatically
```

> **Note:** Do not call `withBillingMode()` when creating from an existing subscription — Stripe inherits it from the source and will reject an explicit value.

### Schedule Operations

```php
// Check status
$schedule->active();
$schedule->notStarted();
$schedule->completed();

// Get phases
$phases = $schedule->phases();

// Release — subscription continues independently
$schedule->release();

// Cancel — subscription is canceled
$schedule->cancel();

// Update
$schedule->updateSchedule([
    'end_behavior' => 'cancel',
]);
```

### Querying Schedules

```php
// Get all schedules
$schedules = $user->subscriptionSchedules;

// Find by type
$schedule = $user->subscriptionSchedule('default');

// Find by Stripe ID
$schedule = $user->findSubscriptionSchedule('sub_sched_xxx');
```

### End Behavior

Control what happens when the last phase completes:

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->endBehavior('release')   // Subscription continues (default)
    // ->endBehavior('cancel') // Subscription is canceled
    // ->endBehavior('none')   // No action
    ->addPhase([...])
    ->create();
```

## Quotes

Generate formal quotes for B2B sales workflows:

### Creating a Quote

```php
$quote = $user->newQuote()
    ->addLineItem('price_enterprise', 1)
    ->description('Enterprise plan — annual commitment')
    ->header('Acme Corp Proposal')
    ->footer('Valid for 30 days')
    ->withMetadata(['sales_rep' => 'jane@company.com'])
    ->create();
```

### With an Expiration Date

```php
$quote = $user->newQuote()
    ->addLineItem('price_enterprise', 1)
    ->expiresAt(now()->addDays(30))
    ->create();
```

### With Flexible Billing

When a quote is accepted, the resulting subscription uses flexible billing:

```php
$quote = $user->newQuote()
    ->addLineItem('price_enterprise', 1)
    ->withBillingMode('flexible')
    ->create();
```

### Quote Lifecycle

```php
// Draft → Open
$quote->finalize();

// Open → Accepted (creates the subscription)
$quote->accept();

// Open → Canceled
$quote->cancel();
```

### Check Status

```php
$quote->draft();     // Not yet finalized
$quote->open();      // Finalized, awaiting customer
$quote->accepted();  // Customer accepted
$quote->canceled();  // Quote was canceled
```

### Download PDF

```php
return $quote->downloadPdf();

// Or with a custom filename
return $quote->downloadPdf('proposal-2024.pdf');
```

### Querying Quotes

```php
$quotes = $user->quotes;
$quote = $user->findQuote('qt_xxx');
```

## Billing Credits

Manage customer credit balances for promotional credits, refunds, or prepaid usage.

### Adding Credits

```php
// Add $50 in credits
$user->addBillingCredits(5000, 'Welcome bonus');

// Or use the existing Cashier method
$user->creditBalance(5000, 'Welcome bonus');
```

### Checking Balance

```php
$credits = $user->availableCredits();       // 5000 (in cents)
$hasFunds = $user->hasSufficientCredits(3000); // true
```

### Calculating Credit Application

See how credits would cover a usage charge without actually modifying the balance:

```php
$result = $user->calculateCreditApplication(8000); // $80 usage

// Returns:
// [
//     'applied_credits' => 5000,  // $50 covered by credits
//     'remaining_usage' => 3000,  // $30 still owed
//     'credits_after' => 0,       // $0 credits remaining
// ]
```

### Deducting Credits

```php
$user->deductBillingCredits(2000, 'Monthly usage charge');

// Or use the existing Cashier method
$user->debitBalance(2000, 'Monthly usage charge');
```

### Transaction History

```php
$transactions = $user->balanceTransactions(25);

foreach ($transactions as $transaction) {
    echo $transaction->amount();         // Formatted: -$50.00
    echo $transaction->rawAmount();      // Raw: -5000
    echo $transaction->endingBalance();  // Formatted: -$50.00
}
```

## Metered Usage Reporting

Report usage events to Stripe for metered billing:

```php
// Report 1 event
$user->reportMeterEvent('api_calls');

// Report multiple
$user->reportMeterEvent('api_calls', 100);

// With custom options
$user->reportMeterEvent('api_calls', 50, [
    'timestamp' => now()->subHour()->getTimestamp(),
]);
```

### Getting Usage Summaries

```php
$summaries = $user->meterEventSummaries(
    meterId: 'meter_xxx',
    startTime: now()->subMonth()->getTimestamp(),
    endTime: now()->getTimestamp(),
);

$totalUsage = $summaries->sum('aggregated_value');
```

### Listing Meters

```php
$meters = $user->meters();
```

## Usage Thresholds

Monitor usage against configurable limits. Thresholds are stored in the database (not cache) so they persist reliably.

### Setting a Threshold

```php
$user->setUsageThreshold('meter_api_calls', 10000, 'billing_cycle', [
    'alert_email' => 'billing@company.com',
]);
```

**Valid periods:** `billing_cycle`, `monthly`, `daily`, `weekly`

### Checking Against a Threshold

```php
$threshold = $user->getUsageThreshold('meter_api_calls');

// Check if usage exceeds the threshold
$threshold->isExceeded(15000);  // true

// Get usage as a percentage
$threshold->usagePercentage(7500);  // 75.0

// Calculate overage
$threshold->overage(12000);  // 2000
```

### Removing a Threshold

```php
$user->removeUsageThreshold('meter_api_calls');
```

## Rate Cards

Model pricing locally for display, comparison, or cost estimation without Stripe API calls.

### Tiered Pricing (Graduated)

Each tier prices only the usage within its range:

```php
use Laravel\Cashier\RateCard;

$card = RateCard::create([
    'name' => 'API Calls',
    'product_id' => 'prod_xxx',
    'pricing_type' => 'tiered',
    'rates' => [
        'mode' => 'graduated',
        'tiers' => [
            ['up_to' => 1000,  'unit_amount' => 10, 'flat_amount' => 0],   // $0.10
            ['up_to' => 10000, 'unit_amount' => 5,  'flat_amount' => 0],   // $0.05
            ['up_to' => null,  'unit_amount' => 2,  'flat_amount' => 0],   // $0.02
        ],
    ],
    'currency' => 'usd',
]);

$result = $card->calculatePricing(15000);
// Total: $65.00
// Tier 1: 1,000 x $0.10 = $10.00
// Tier 2: 9,000 x $0.05 = $45.00
// Tier 3: 5,000 x $0.02 = $10.00
```

### Tiered Pricing (Volume)

All units priced at the tier the total falls into:

```php
$card = RateCard::create([
    'name' => 'Storage',
    'product_id' => 'prod_xxx',
    'pricing_type' => 'tiered',
    'rates' => [
        'mode' => 'volume',
        'tiers' => [
            ['up_to' => 100,  'unit_amount' => 50, 'flat_amount' => 0],  // $0.50
            ['up_to' => 1000, 'unit_amount' => 30, 'flat_amount' => 0],  // $0.30
            ['up_to' => null, 'unit_amount' => 10, 'flat_amount' => 0],  // $0.10
        ],
    ],
    'currency' => 'usd',
]);

$result = $card->calculatePricing(500);
// 500 falls in tier 2, so all 500 priced at $0.30
// Total: $150.00
```

### Package Pricing

Usage rounded up to the nearest package:

```php
$card = RateCard::create([
    'name' => 'Messages',
    'product_id' => 'prod_xxx',
    'pricing_type' => 'package',
    'rates' => [
        'package_size' => 1000,
        'package_price' => 500,  // $5.00 per 1,000
    ],
    'currency' => 'usd',
]);

$result = $card->calculatePricing(2500);
// ceil(2500 / 1000) = 3 packages
// Total: $15.00
```

### Flat Rate Pricing

```php
$card = RateCard::create([
    'name' => 'Bandwidth',
    'product_id' => 'prod_xxx',
    'pricing_type' => 'flat',
    'rates' => ['unit_amount' => 1],  // $0.01 per MB
    'currency' => 'usd',
]);

$result = $card->calculatePricing(50000);
// 50,000 x $0.01 = $500.00
```

### Querying Rate Cards

```php
// Active rate cards for a product
$cards = RateCard::active()->forProduct('prod_xxx')->get();

// Deactivate a rate card
$card->deactivate();
```

## Migrating from Classic to Flexible

> **This is a one-way operation.** Once a subscription is migrated to flexible billing, it cannot go back to classic.

### Individual Subscription

```php
$subscription->migrateToFlexibleBillingMode();
```

This uses Stripe's dedicated `/migrate` endpoint. The subscription status is preserved — it continues running without interruption.

### Safe Guards

The method includes safety checks:

```php
// Already flexible — returns immediately (no API call)
$subscription->migrateToFlexibleBillingMode();

// Incomplete subscription — throws SubscriptionUpdateFailure
// Canceled subscription — throws LogicException
```

### All New Subscriptions

Set the global default so new subscriptions automatically use flexible billing. Existing subscriptions are unaffected:

```php
// In AppServiceProvider::boot()
Cashier::defaultBillingMode('flexible');
```

### Migration Strategy

1. Set `Cashier::defaultBillingMode('flexible')` — all new subs use flexible
2. Migrate existing subs gradually: `$subscription->migrateToFlexibleBillingMode()`
3. Use `$subscription->usesFlexibleBilling()` to check which mode a sub is on

## Webhook Handling

The following webhook events are handled automatically:

### Subscription Schedule Events

| Event | What Happens |
|-------|-------------|
| `subscription_schedule.created` | Creates a local schedule record |
| `subscription_schedule.updated` | Updates status, phase timestamps, subscription ID |
| `subscription_schedule.canceled` | Sets status to canceled with timestamp |
| `subscription_schedule.completed` | Sets status to completed with timestamp |
| `subscription_schedule.released` | Sets status to released with timestamp |

### Quote Events

| Event | What Happens |
|-------|-------------|
| `quote.finalized` | Updates status, number, amounts, finalized_at |
| `quote.accepted` | Sets status to accepted with timestamp |
| `quote.canceled` | Sets status to canceled with timestamp |

To receive these events, add them to your Stripe webhook configuration. In your `config/cashier.php`:

```php
'webhook' => [
    'events' => [
        // ... existing events ...
        'subscription_schedule.created',
        'subscription_schedule.updated',
        'subscription_schedule.canceled',
        'subscription_schedule.completed',
        'subscription_schedule.released',
        'quote.finalized',
        'quote.accepted',
        'quote.canceled',
    ],
],
```

## Configuration Reference

### Global Billing Mode

```php
// In AppServiceProvider::boot()
Cashier::defaultBillingMode('flexible'); // or 'classic'
```

### Per-Builder Billing Mode

Available on all builders:

```php
// SubscriptionBuilder
$user->newSubscription('default', $price)->withBillingMode('flexible');

// CheckoutBuilder (inherited from SubscriptionBuilder)
$user->newSubscription('default', $price)->withBillingMode('flexible')->checkout([...]);

// SubscriptionScheduleBuilder
$user->newSubscriptionSchedule('default')->withBillingMode('flexible');

// QuoteBuilder
$user->newQuote()->withBillingMode('flexible');
```

### Incompatibilities

**Billing thresholds** cannot be used with flexible billing mode:

```php
// This will throw InvalidArgumentException
$user->newSubscription('default', $price)
    ->withBillingMode('flexible')
    ->withBillingThresholds(['amount_gte' => 1000])
    ->create($pm);
```

**billing_mode cannot be changed after creation.** Use the dedicated migration method:

```php
// This is the only way to change billing mode
$subscription->migrateToFlexibleBillingMode();

// swap() does NOT change billing mode (it's preserved)
$subscription->swap($newPrice);
```

### Stripe API Version

Flexible billing requires Stripe API version `2025-06-30.basil` or later. Laravel Cashier's bundled `stripe-php` SDK (v17.4.0+) includes support for all flexible billing endpoints.
