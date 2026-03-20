<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\RateCard;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemoController extends Controller
{
    protected array $steps = [];
    protected float $scenarioStart;
    protected float $lastStepTime;
    protected bool $showDashboardLinks = false;
    protected string $customerName = 'Demo User';
    protected string $customerDomain = 'cashier-demo.test';

    public function index()
    {
        return view('demo.index');
    }

    public function docs()
    {
        $markdown = file_get_contents(base_path('docs/flexible-billing.md'));

        $content = \Illuminate\Support\Str::markdown($markdown);

        // Add id attributes to headings for anchor links
        $content = preg_replace_callback('/<(h[1-6])>(.*?)<\/h[1-6]>/i', function ($matches) {
            $tag = $matches[1];
            $text = strip_tags($matches[2]);
            // Match GitHub/markdown heading slug algorithm
            $id = strtolower($text);
            $id = preg_replace('/[^a-z0-9 -]/', '', $id);
            $id = str_replace(' ', '-', trim($id));

            return "<{$tag} id=\"{$id}\">{$matches[2]}</{$tag}>";
        }, $content);

        return view('demo.docs', ['content' => $content]);
    }

    public function stream(string $scenario, Request $request): StreamedResponse
    {
        return new StreamedResponse(function () use ($scenario, $request) {
            $this->steps = [];
            $this->scenarioStart = microtime(true);
            $this->lastStepTime = $this->scenarioStart;
            $this->showDashboardLinks = $request->boolean('dashboard');
            $this->customerName = $request->input('name', config('demo.customer_name', 'Demo User'));
            $this->customerDomain = $request->input('domain', config('demo.customer_email_domain', 'cashier-demo.test'));

            try {
                $method = 'scenario'.str_replace('-', '', ucwords($scenario, '-'));

                if (! method_exists($this, $method)) {
                    $this->step('Unknown scenario: '.$scenario, false);
                    $this->sendEvent('done', ['success' => false]);

                    return;
                }

                $this->$method();

                $allPassed = collect($this->steps)->every(fn ($s) => $s['success']);
                $totalMs = round((microtime(true) - $this->scenarioStart) * 1000);
                $this->sendEvent('done', ['success' => $allPassed, 'totalMs' => $totalMs]);
            } catch (\Stripe\Exception\ApiConnectionException $e) {
                $this->step('Network Error — Could not connect to Stripe', false, html: '
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
                        <div class="font-medium text-amber-800 mb-1">Connection timed out</div>
                        <div class="text-amber-700">Your internet connection to api.stripe.com failed. This is a network issue, not a code bug. Try again when your connection is stable.</div>
                        <div class="text-amber-600 text-xs mt-2 font-mono">'.e($e->getMessage()).'</div>
                    </div>
                ');
                $this->sendEvent('done', ['success' => false]);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->step('Stripe API Error', false, html: '
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm">
                        <div class="font-medium text-red-800 mb-1">'.e($e->getMessage()).'</div>
                    </div>
                ');
                $this->sendEvent('done', ['success' => false]);
            } catch (\Throwable $e) {
                $this->step('Unexpected error', false, error: get_class($e).': '.$e->getMessage());
                $this->sendEvent('done', ['success' => false]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // =========================================================================
    // Scenario 1: Flexible Subscription
    // =========================================================================

    protected function scenarioFlexibleSub(): void
    {
        $user = $this->freshUser('flex-sub');
        $this->step('Created test customer', true, ['email' => $user->email], code: '$user = User::create([\'name\' => \'Demo User\', \'email\' => $email]);');

        $product = $this->stripe()->products->create(['name' => 'Pro Plan '.time()]);
        $price = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month'], 'unit_amount' => 2900,
        ]);
        $this->step('Created product and price on Stripe', true, ['price' => '$29.00/mo'], code: '$price = $stripe->prices->create([
    \'product\' => $product->id, \'currency\' => \'usd\',
    \'recurring\' => [\'interval\' => \'month\'], \'unit_amount\' => 2900,
]);');

        $subscription = $user->newSubscription('default', $price->id)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $stripe = $subscription->asStripeSubscription();
        $this->step('Created subscription with flexible billing', $stripe->billing_mode->type === 'flexible', [
            'stripe_id' => $subscription->stripe_id,
            'billing_mode' => $stripe->billing_mode->type,
            'status' => $subscription->stripe_status,
        ], links: $this->stripeLinks([
            'Subscription' => $subscription->stripe_id,
            'Customer' => $user->stripeId(),
        ]), code: '$subscription = $user->newSubscription(\'default\', $priceId)
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');

$subscription->usesFlexibleBilling(); // true');

        $subscription->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 2: Classic → Flexible Migration
    // =========================================================================

    protected function scenarioMigration(): void
    {
        $user = $this->freshUser('migrate');

        $product = $this->stripe()->products->create(['name' => 'Migration Test '.time()]);
        $price = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month'], 'unit_amount' => 1000,
        ]);
        $premium = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month'], 'unit_amount' => 2500,
        ]);

        $subscription = $user->newSubscription('default', $price->id)->create('pm_card_visa');
        $this->step('Created classic subscription ($10/mo)', ! $subscription->usesFlexibleBilling(), [
            'billing_mode' => 'classic',
        ], code: '// Classic mode (default)
$subscription = $user->newSubscription(\'default\', $priceId)
    ->create(\'pm_card_visa\');');

        $subscription->migrateToFlexibleBillingMode();
        $this->step('Migrated via POST /v1/subscriptions/{id}/migrate', $subscription->usesFlexibleBilling(), [
            'billing_mode' => 'flexible',
        ], links: $this->stripeLinks(['Subscription' => $subscription->stripe_id]),
        code: '// One-way migration (cannot go back to classic)
$subscription->migrateToFlexibleBillingMode();');

        $subscription->swap($premium->id);
        $this->step('Swapped to premium ($25/mo) — billing mode preserved', $subscription->usesFlexibleBilling() && $subscription->stripe_price === $premium->id, [
            'new_price' => '$25.00/mo',
            'billing_mode' => 'flexible',
        ], code: '$subscription->swap($premiumPrice);');

        $subscription->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 3: Hybrid Billing
    // =========================================================================

    protected function scenarioHybrid(): void
    {
        $user = $this->freshUser('hybrid');

        $product = $this->stripe()->products->create(['name' => 'Hybrid Plan '.time()]);
        $base = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month'], 'unit_amount' => 2900,
        ]);

        $meter = $this->stripe()->billing->meters->create([
            'display_name' => 'API Calls '.time(),
            'event_name' => 'api_calls_'.time(),
            'default_aggregation' => ['formula' => 'sum'],
        ]);

        $metered = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month', 'usage_type' => 'metered', 'meter' => $meter->id],
            'unit_amount' => 1,
        ]);

        $this->step('Set up hybrid pricing', true, [
            'base' => '$29.00/mo fixed', 'metered' => '$0.01 per API call',
        ], code: '$base = $stripe->prices->create([\'unit_amount\' => 2900, ...]);
$metered = $stripe->prices->create([\'unit_amount\' => 1, \'recurring\' => [\'usage_type\' => \'metered\', \'meter\' => $meter->id], ...]);');

        $subscription = $user->newSubscription('default')
            ->price($base->id)
            ->meteredPrice($metered->id)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->step('Created hybrid subscription', $subscription->hasMultiplePrices() && $subscription->usesFlexibleBilling(), [
            'items' => $subscription->items->count(),
            'billing_mode' => 'flexible',
        ], links: $this->stripeLinks(['Subscription' => $subscription->stripe_id]),
        code: '$subscription = $user->newSubscription(\'default\')
    ->price(\'price_base_plan\')        // $29/mo fixed
    ->meteredPrice(\'price_api_calls\') // $0.01 per call
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');');

        $subscription->removePrice($metered->id);
        $this->step('Removed metered price (no clear_usage error in flexible mode)', $subscription->hasSinglePrice(), [
            'remaining' => $subscription->stripe_price,
        ], code: '$subscription->removePrice($meteredPrice);');

        $subscription->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 4: Proration Discounts
    // =========================================================================

    protected function scenarioProrationDiscounts(): void
    {
        $user = $this->freshUser('proration');

        $product = $this->stripe()->products->create(['name' => 'Proration Test '.time()]);
        $price = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month'], 'unit_amount' => 4900,
        ]);

        $sub = $user->newSubscription('default', $price->id)
            ->withBillingMode('flexible')
            ->withProrationDiscounts('itemized')
            ->create('pm_card_visa');

        $this->step('Created with itemized proration discounts', $sub->usesFlexibleBilling(), [
            'mode' => 'itemized',
            'effect' => 'Discount amounts shown as separate line items on invoices',
        ], links: $this->stripeLinks(['Subscription' => $sub->stripe_id]),
        code: '$subscription = $user->newSubscription(\'default\', $priceId)
    ->withBillingMode(\'flexible\')
    ->withProrationDiscounts(\'itemized\')
    ->create(\'pm_card_visa\');');

        $sub->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 5: Swap / Upgrade / Downgrade
    // =========================================================================

    protected function scenarioSwap(): void
    {
        $user = $this->freshUser('swap');

        $product = $this->stripe()->products->create(['name' => 'Swap Test '.time()]);
        $starter = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 900]);
        $pro = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 2900]);
        $enterprise = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 9900]);

        $sub = $user->newSubscription('default', $starter->id)->withBillingMode('flexible')->create('pm_card_visa');
        $this->step('Started on Starter ($9/mo)', true, code: '$sub = $user->newSubscription(\'default\', $starterPrice)
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');');

        $sub->swap($pro->id);
        $this->step('Upgraded to Pro ($29/mo)', $sub->stripe_price === $pro->id, code: '$subscription->swap($proPrice);');

        $sub->swap($enterprise->id);
        $this->step('Upgraded to Enterprise ($99/mo)', $sub->stripe_price === $enterprise->id, code: '$subscription->swap($enterprisePrice);');

        $sub->swap($starter->id);
        $this->step('Downgraded to Starter ($9/mo) — billing mode preserved', $sub->usesFlexibleBilling() && $sub->stripe_price === $starter->id, code: '$subscription->swap($starterPrice);
$subscription->usesFlexibleBilling(); // still true');

        $sub->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 6: Global Default
    // =========================================================================

    protected function scenarioGlobalDefault(): void
    {
        $original = Cashier::$defaultBillingMode;
        $this->step('Current default: '.$original, true, code: 'Cashier::$defaultBillingMode; // \''.$original.'\'');

        Cashier::defaultBillingMode('flexible');
        $this->step('Set Cashier::defaultBillingMode("flexible")', Cashier::$defaultBillingMode === 'flexible', code: '// In AppServiceProvider::boot()
Cashier::defaultBillingMode(\'flexible\');');

        $user = $this->freshUser('global');
        $product = $this->stripe()->products->create(['name' => 'Global Test '.time()]);
        $price = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 1500]);

        $sub = $user->newSubscription('default', $price->id)->create('pm_card_visa');
        $this->step('Created subscription WITHOUT withBillingMode() — uses global default', $sub->usesFlexibleBilling(), [
            'billing_mode' => 'flexible',
            'called_withBillingMode' => 'No',
        ], code: '// No withBillingMode() needed — global default applies
$sub = $user->newSubscription(\'default\', $priceId)
    ->create(\'pm_card_visa\');

$sub->usesFlexibleBilling(); // true');

        $sub->cancelNow();
        Cashier::$defaultBillingMode = $original;
        $this->step('Reset global default to "'.$original.'"', true, code: 'Cashier::$defaultBillingMode = \''.$original.'\';');
    }

    // =========================================================================
    // Scenario 7: Cancel and Resume
    // =========================================================================

    protected function scenarioCancelResume(): void
    {
        $user = $this->freshUser('cancel');

        $product = $this->stripe()->products->create(['name' => 'Cancel Test '.time()]);
        $price = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 1900]);

        $sub = $user->newSubscription('default', $price->id)->withBillingMode('flexible')->create('pm_card_visa');
        $this->step('Created flexible subscription', $sub->active(), code: '$sub = $user->newSubscription(\'default\', $priceId)
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');');

        $sub->cancel();
        $this->step('Canceled — on grace period', $sub->onGracePeriod() && $sub->valid(), [
            'ends_at' => $sub->ends_at?->toDateTimeString(),
        ], code: '$subscription->cancel();
$subscription->onGracePeriod(); // true
$subscription->valid();         // true');

        $sub->resume();
        $this->step('Resumed — active again, billing mode preserved', $sub->active() && ! $sub->canceled() && $sub->usesFlexibleBilling(), code: '$subscription->resume();
$subscription->active();                // true
$subscription->usesFlexibleBilling();   // true');

        $sub->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 8: Multi-Phase Schedule
    // =========================================================================

    protected function scenarioSchedule(): void
    {
        $user = $this->freshUser('schedule');

        $product = $this->stripe()->products->create(['name' => 'Schedule Test '.time()]);
        $starter = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 900]);
        $pro = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 2900]);

        $schedule = $user->newSubscriptionSchedule('default')
            ->withBillingMode('flexible')
            ->addPhase([['price' => $starter->id, 'quantity' => 1]], ['iterations' => 1])
            ->addPhase([['price' => $pro->id, 'quantity' => 1]], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $phases = $schedule->phases();
        $this->step('Created 2-phase schedule', $schedule->active() && count($phases) === 2, [
            'phase_1' => 'Starter $9/mo (1 cycle)',
            'phase_2' => 'Pro $29/mo (1 cycle)',
        ], links: $this->stripeLinks(['Schedule' => $schedule->stripe_id]),
        code: '$schedule = $user->newSubscriptionSchedule(\'default\')
    ->withBillingMode(\'flexible\')
    ->addPhase([
        [\'price\' => $starterPrice, \'quantity\' => 1],
    ], [\'iterations\' => 1])
    ->addPhase([
        [\'price\' => $proPrice, \'quantity\' => 1],
    ], [\'iterations\' => 1])
    ->startDate(\'now\')
    ->create();');

        $schedule->release();
        $this->step('Released — subscription continues independently', $schedule->released(), code: '$schedule->release();');
    }

    // =========================================================================
    // Scenario 9: Quote Lifecycle
    // =========================================================================

    protected function scenarioQuote(): void
    {
        $user = $this->freshUser('quote');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $product = $this->stripe()->products->create(['name' => 'Quote Test '.time()]);
        $price = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 4900]);

        $quote = $user->newQuote()->addLineItem($price->id, 1)->description('Enterprise plan proposal')->create();
        $this->step('Created quote (draft)', $quote->draft(), ['amount' => '$'.number_format($quote->amount_total / 100, 2)],
            links: $this->stripeLinks(['Quote' => $quote->stripe_id]),
            code: '$quote = $user->newQuote()
    ->addLineItem($priceId, 1)
    ->description(\'Enterprise plan proposal\')
    ->create();');

        $quote->finalize();
        $this->step('Finalized (open — awaiting acceptance)', $quote->open(), code: '$quote->finalize();');

        $quote->accept();
        $this->step('Accepted — subscription created', $quote->accepted(), code: '$quote->accept();
// Stripe auto-creates a subscription from the quote');
    }

    // =========================================================================
    // Scenario 10: Schedule from Existing Subscription
    // =========================================================================

    protected function scenarioScheduleFromSub(): void
    {
        $user = $this->freshUser('sched-sub');

        $product = $this->stripe()->products->create(['name' => 'SchedSub Test '.time()]);
        $price = $this->stripe()->prices->create(['product' => $product->id, 'currency' => 'usd', 'recurring' => ['interval' => 'month'], 'unit_amount' => 1900]);

        $sub = $user->newSubscription('default', $price->id)->withBillingMode('flexible')->create('pm_card_visa');
        $this->step('Created flexible subscription', $sub->usesFlexibleBilling(), code: '$sub = $user->newSubscription(\'default\', $priceId)
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');');

        $schedule = $user->newSubscriptionSchedule('default')->createFromSubscription($sub);
        $this->step('Created schedule from subscription — billing mode inherited', $schedule->active(), [
            'billing_mode_set_explicitly' => 'No — inherited from subscription',
        ], links: $this->stripeLinks(['Schedule' => $schedule->stripe_id, 'Subscription' => $sub->stripe_id]),
        code: '// Do NOT call withBillingMode() here — inherited from sub
$schedule = $user->newSubscriptionSchedule(\'default\')
    ->createFromSubscription($subscription);');

        $schedule->cancel();
        $this->step('Cleaned up', true, code: '$schedule->cancel();');
    }

    // =========================================================================
    // Scenario 11: Billing Credits
    // =========================================================================

    protected function scenarioCredits(): void
    {
        $user = $this->freshUser('credits');
        $user->createAsStripeCustomer();
        $this->step('Starting balance: $0.00', $user->availableCredits() === 0, code: '$user->availableCredits(); // 0');

        $user->addBillingCredits(10000, 'Welcome bonus');
        $this->step('Added $100.00 in credits', $user->availableCredits() === 10000, [
            'balance' => '$'.number_format($user->availableCredits() / 100, 2),
        ], code: '$user->addBillingCredits(10000, \'Welcome bonus\');');

        $calc = $user->calculateCreditApplication(15000);
        $this->step('Applied to $150 usage: $100 covered, $50 remaining', $calc['applied_credits'] === 10000, [
            'applied' => '$'.number_format($calc['applied_credits'] / 100, 2),
            'remaining' => '$'.number_format($calc['remaining_usage'] / 100, 2),
        ], code: '$result = $user->calculateCreditApplication(15000);
// applied_credits: 10000, remaining_usage: 5000');

        $user->deductBillingCredits(3000, 'Usage charge');
        $this->step('Deducted $30.00', $user->availableCredits() === 7000, [
            'balance' => '$'.number_format($user->availableCredits() / 100, 2),
        ], code: '$user->deductBillingCredits(3000, \'Usage charge\');');
    }

    // =========================================================================
    // Scenario 12: Metered Usage
    // =========================================================================

    protected function scenarioMeteredUsage(): void
    {
        $user = $this->freshUser('metered');

        $product = $this->stripe()->products->create(['name' => 'Metered Test '.time()]);
        $eventName = 'usage_'.time();

        $meter = $this->stripe()->billing->meters->create([
            'display_name' => 'Events '.time(),
            'event_name' => $eventName,
            'default_aggregation' => ['formula' => 'sum'],
        ]);

        $price = $this->stripe()->prices->create([
            'product' => $product->id, 'currency' => 'usd',
            'recurring' => ['interval' => 'month', 'usage_type' => 'metered', 'meter' => $meter->id],
            'unit_amount' => 5,
        ]);

        $sub = $user->newSubscription('default', $price->id)
            ->meteredPrice($price->id)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');
        $this->step('Created metered subscription ($0.05/event)', $sub->usesFlexibleBilling(), code: '$sub = $user->newSubscription(\'default\', $meteredPrice)
    ->meteredPrice($meteredPrice)
    ->withBillingMode(\'flexible\')
    ->create(\'pm_card_visa\');');

        $user->reportMeterEvent($eventName, 100);
        $this->step('Reported 100 events', true, ['estimated' => '$5.00'], code: '$user->reportMeterEvent(\'api_calls\', 100);');

        $user->reportMeterEvent($eventName, 250);
        $this->step('Reported 250 more events (350 total)', true, ['estimated' => '$17.50'], code: '$user->reportMeterEvent(\'api_calls\', 250);');

        $sub->cancelNow();
        $this->step('Cleaned up', true, code: '$subscription->cancelNow();');
    }

    // =========================================================================
    // Scenario 13: Usage Thresholds
    // =========================================================================

    protected function scenarioUsageThresholds(): void
    {
        $user = $this->freshUser('thresholds');
        $user->createAsStripeCustomer();

        $threshold = $user->setUsageThreshold('meter_api_calls', 10000, 'billing_cycle', [
            'alert_email' => 'billing@example.com',
        ]);
        $this->step('Set threshold: 10,000 API calls/cycle', $threshold->threshold === 10000, code: '$user->setUsageThreshold(\'meter_api_calls\', 10000, \'billing_cycle\', [
    \'alert_email\' => \'billing@example.com\',
]);');

        $pct50 = $threshold->usagePercentage(5000);
        $pct100 = $threshold->usagePercentage(10000);
        $pct150 = $threshold->usagePercentage(15000);

        $this->step('Usage monitoring', true, html: '
            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100"><tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Usage</th>
                    <th class="text-center px-3 py-2 font-medium text-gray-600">% of Threshold</th>
                    <th class="text-center px-3 py-2 font-medium text-gray-600">Exceeded?</th>
                    <th class="text-right px-3 py-2 font-medium text-gray-600">Overage</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Visual</th>
                </tr></thead>
                <tbody>
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2 font-mono">5,000</td>
                        <td class="text-center px-3 py-2"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800">'.$pct50.'%</span></td>
                        <td class="text-center px-3 py-2 text-green-600">No</td>
                        <td class="text-right px-3 py-2 font-mono">0</td>
                        <td class="px-3 py-2"><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-green-500 h-2 rounded-full" style="width: 50%"></div></div></td>
                    </tr>
                    <tr class="border-t border-gray-100 bg-gray-50">
                        <td class="px-3 py-2 font-mono">10,000</td>
                        <td class="text-center px-3 py-2"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800">'.$pct100.'%</span></td>
                        <td class="text-center px-3 py-2 text-yellow-600">At limit</td>
                        <td class="text-right px-3 py-2 font-mono">0</td>
                        <td class="px-3 py-2"><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-yellow-500 h-2 rounded-full" style="width: 100%"></div></div></td>
                    </tr>
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2 font-mono">15,000</td>
                        <td class="text-center px-3 py-2"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800">'.$pct150.'%</span></td>
                        <td class="text-center px-3 py-2 text-red-600 font-bold">Yes</td>
                        <td class="text-right px-3 py-2 font-mono font-semibold text-red-600">'.number_format($threshold->overage(15000)).'</td>
                        <td class="px-3 py-2"><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-red-500 h-2 rounded-full" style="width: 100%"></div></div></td>
                    </tr>
                </tbody>
            </table>
        ', code: '$threshold->usagePercentage($currentUsage);
$threshold->exceeded($currentUsage);
$threshold->overage($currentUsage);');

        $user->removeUsageThreshold('meter_api_calls');
        $this->step('Cleaned up', $user->getUsageThreshold('meter_api_calls') === null, code: '$user->removeUsageThreshold(\'meter_api_calls\');');
    }

    // =========================================================================
    // Scenario 14: Rate Card Pricing
    // =========================================================================

    protected function scenarioRatecard(): void
    {
        $graduated = new RateCard([
            'pricing_type' => 'tiered', 'currency' => 'usd',
            'rates' => ['mode' => 'graduated', 'tiers' => [
                ['up_to' => 1000, 'unit_amount' => 10, 'flat_amount' => 0],
                ['up_to' => 10000, 'unit_amount' => 5, 'flat_amount' => 0],
                ['up_to' => null, 'unit_amount' => 2, 'flat_amount' => 0],
            ]],
        ]);
        $result = $graduated->calculatePricing(15000);
        $this->step('Graduated tiered: 15,000 API calls', $result['total_amount'] === 65000, html: '
            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100"><tr><th class="text-left px-3 py-2 font-medium text-gray-600">Tier</th><th class="text-left px-3 py-2 font-medium text-gray-600">Range</th><th class="text-right px-3 py-2 font-medium text-gray-600">Rate</th><th class="text-right px-3 py-2 font-medium text-gray-600">Units</th><th class="text-right px-3 py-2 font-medium text-gray-600">Subtotal</th></tr></thead>
                <tbody>
                    <tr class="border-t"><td class="px-3 py-2">1</td><td class="px-3 py-2">1 — 1,000</td><td class="text-right px-3 py-2">$0.10</td><td class="text-right px-3 py-2">1,000</td><td class="text-right px-3 py-2">$10.00</td></tr>
                    <tr class="border-t bg-gray-50"><td class="px-3 py-2">2</td><td class="px-3 py-2">1,001 — 10,000</td><td class="text-right px-3 py-2">$0.05</td><td class="text-right px-3 py-2">9,000</td><td class="text-right px-3 py-2">$45.00</td></tr>
                    <tr class="border-t"><td class="px-3 py-2">3</td><td class="px-3 py-2">10,001+</td><td class="text-right px-3 py-2">$0.02</td><td class="text-right px-3 py-2">5,000</td><td class="text-right px-3 py-2">$10.00</td></tr>
                </tbody>
                <tfoot class="bg-gray-100 font-semibold"><tr><td colspan="4" class="text-right px-3 py-2">Total</td><td class="text-right px-3 py-2 text-green-700">$'.number_format($result['total_amount'] / 100, 2).'</td></tr></tfoot>
            </table>
        ', code: '$rateCard = new RateCard([
    \'pricing_type\' => \'tiered\',
    \'rates\' => [\'mode\' => \'graduated\', \'tiers\' => [...]],
]);
$result = $rateCard->calculatePricing(15000);');

        $package = new RateCard([
            'pricing_type' => 'package', 'currency' => 'usd',
            'rates' => ['package_size' => 1000, 'package_price' => 500],
        ]);
        $result = $package->calculatePricing(2500);
        $this->step('Package: 2,500 messages (1,000/pack at $5)', $result['packages_used'] === 3, [
            'packages' => 3, 'total' => '$'.number_format($result['total_amount'] / 100, 2),
        ], code: '$rateCard = new RateCard([
    \'pricing_type\' => \'package\',
    \'rates\' => [\'package_size\' => 1000, \'package_price\' => 500],
]);
$result = $rateCard->calculatePricing(2500);');

        $flat = new RateCard([
            'pricing_type' => 'flat', 'currency' => 'usd',
            'rates' => ['unit_amount' => 1],
        ]);
        $result = $flat->calculatePricing(50000);
        $this->step('Flat: 50,000 MB at $0.01/MB', $result['total_amount'] === 50000, [
            'total' => '$'.number_format($result['total_amount'] / 100, 2),
        ], code: '$rateCard = new RateCard([
    \'pricing_type\' => \'flat\',
    \'rates\' => [\'unit_amount\' => 1],
]);
$result = $rateCard->calculatePricing(50000);');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    protected function step(string $label, bool $success, ?array $detail = null, ?string $error = null, ?string $html = null, array $links = [], ?string $code = null): void
    {
        $now = microtime(true);
        $stepMs = round(($now - $this->lastStepTime) * 1000, 1);
        $totalMs = round(($now - $this->scenarioStart) * 1000);
        $this->lastStepTime = $now;

        $stepData = array_filter([
            'label' => $label,
            'success' => $success,
            'detail' => $detail,
            'error' => $error,
            'html' => $html,
            'code' => $code,
            'links' => $this->showDashboardLinks && ! empty($links) ? $links : null,
            'duration' => $stepMs < 1 ? '<1ms' : round($stepMs).'ms',
            'total' => $totalMs.'ms',
        ], fn ($v) => ! is_null($v));

        $this->steps[] = $stepData;
        $this->sendEvent('step', $stepData);
    }

    /**
     * Generate Stripe test dashboard links for various object types.
     */
    protected function stripeLinks(array $objects): array
    {
        $base = 'https://dashboard.stripe.com/test';
        $links = [];

        foreach ($objects as $label => $id) {
            if (str_starts_with($id, 'cus_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/customers/{$id}"];
            } elseif (str_starts_with($id, 'sub_sched_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/subscription-schedules/{$id}"];
            } elseif (str_starts_with($id, 'sub_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/subscriptions/{$id}"];
            } elseif (str_starts_with($id, 'qt_') || str_starts_with($id, 'quo_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/quotes/{$id}"];
            } elseif (str_starts_with($id, 'prod_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/products/{$id}"];
            } elseif (str_starts_with($id, 'price_')) {
                $links[] = ['label' => $label, 'url' => "{$base}/prices/{$id}"];
            } else {
                $links[] = ['label' => $label, 'url' => "{$base}/search?query={$id}"];
            }
        }

        return $links;
    }

    protected function freshUser(string $suffix): User
    {
        return User::forceCreate([
            'name' => $this->customerName,
            'email' => "demo-{$suffix}-".time()."@{$this->customerDomain}",
            'password' => bcrypt('password'),
        ]);
    }

    protected function stripe()
    {
        return Cashier::stripe();
    }
}
