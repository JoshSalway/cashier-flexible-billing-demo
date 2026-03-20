@extends('demo.layout')

@section('content')
<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Flexible Billing Demo</h1>
        <p class="mt-2 text-gray-600">14 scenarios running against the <strong>real Stripe API</strong>. Each step streams live as it executes.</p>
    </div>
    <div class="flex items-center gap-4">
        {{-- Toggle All Code --}}
        <button id="toggle-code-btn" onclick="toggleAllCode()" class="text-gray-400 hover:text-gray-600 text-sm font-medium transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5"/></svg>
            Show All Code
        </button>

        {{-- Settings --}}
        <button onclick="document.getElementById('settings-panel').classList.toggle('hidden')" class="text-gray-400 hover:text-gray-600 transition-colors" title="Settings">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
        </button>

        <span id="run-all-progress" class="text-sm text-gray-500 hidden"></span>

        <button id="stop-btn" onclick="stopAll()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors hidden">
            Stop
        </button>

        <button id="reset-btn" onclick="resetAll()" class="text-gray-400 hover:text-gray-600 text-sm font-medium transition-colors hidden">
            Reset
        </button>

        <button id="run-all-btn" onclick="runAll(this)" class="bg-gray-900 hover:bg-gray-800 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors shadow-lg flex items-center gap-2">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
            Run All 14 Scenarios
        </button>
    </div>
</div>

{{-- Settings panel (collapsed by default) --}}
<div id="settings-panel" class="hidden mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Settings</h3>
    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Customer Name</label>
            <input type="text" id="setting-name" value="Demo User" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-stripe focus:border-stripe">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Email Domain</label>
            <input type="text" id="setting-domain" value="cashier-demo.test" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-stripe focus:border-stripe">
        </div>
        <div class="flex flex-col gap-2 justify-center">
            <p class="text-xs text-gray-400">Stripe Dashboard links are shown on each step. They require access to the Stripe account that owns the test keys.</p>
        </div>
    </div>
    <p class="text-xs text-gray-400 mt-2">Each scenario creates a unique customer in Stripe. Change the name/domain to organize test data in your dashboard.</p>
</div>

{{-- What Changed: Before & After --}}
<div class="mb-10 bg-gradient-to-r from-gray-900 to-stripe rounded-2xl p-8 text-white shadow-xl">
    <h2 class="text-2xl font-bold mb-2">What Changed</h2>
    <p class="text-white/70 text-sm mb-6">Flexible billing builds on everything Cashier already does well. Here is what it adds.</p>
    <div class="grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-xs">CLASSIC MODE</span>
                What Cashier Has Today
            </h3>
            <div class="space-y-2 text-sm text-gray-300">
                <div class="flex items-start gap-2"><span class="text-white/40 mt-0.5">&mdash;</span> Fixed-price subscriptions with quantity</div>
                <div class="flex items-start gap-2"><span class="text-white/40 mt-0.5">&mdash;</span> Metered billing with manual <code class="bg-white/10 px-1 rounded">clear_usage</code> handling</div>
                <div class="flex items-start gap-2"><span class="text-white/40 mt-0.5">&mdash;</span> Single billing mode, one approach for all</div>
                <div class="flex items-start gap-2"><span class="text-white/40 mt-0.5">&mdash;</span> Proration amounts are net of discounts</div>
            </div>
            <div class="mt-4 bg-black/30 rounded-lg p-3 text-xs font-mono text-gray-300">
                <div class="text-gray-500">// Classic: straightforward subscription</div>
                <div>$user->newSubscription('default', $priceId)</div>
                <div class="pl-4">->create($paymentMethod);</div>
            </div>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-green-500/20 text-green-300 px-2.5 py-0.5 text-xs">FLEXIBLE MODE</span>
                What This PR Adds
            </h3>
            <div class="space-y-2 text-sm text-white">
                <div class="flex items-start gap-2"><span class="text-green-400 mt-0.5">+</span> Hybrid billing: base plan + metered usage on one subscription</div>
                <div class="flex items-start gap-2"><span class="text-green-400 mt-0.5">+</span> Automatic <code class="bg-white/10 px-1 rounded">clear_usage</code> handling in flexible mode</div>
                <div class="flex items-start gap-2"><span class="text-green-400 mt-0.5">+</span> Subscription schedules, quotes, billing credits, rate cards</div>
                <div class="flex items-start gap-2"><span class="text-green-400 mt-0.5">+</span> Itemized proration discounts with transparent line items</div>
            </div>
            <div class="mt-4 bg-black/30 rounded-lg p-3 text-xs font-mono text-green-200">
                <div class="text-green-400/70">// Flexible: hybrid billing in one call</div>
                <div>$user->newSubscription('default')</div>
                <div class="pl-4">->price($basePlan)</div>
                <div class="pl-4">->meteredPrice($apiCallsPrice)</div>
                <div class="pl-4">->withBillingMode('flexible')</div>
                <div class="pl-4">->create($paymentMethod);</div>
            </div>
        </div>
    </div>
    <div class="mt-6 pt-6 border-t border-white/10 text-sm text-white/60">
        <strong class="text-white/80">Migration path:</strong> Existing subscriptions stay on classic mode. Use <code class="bg-white/10 px-1 rounded">$subscription->migrateToFlexibleBillingMode()</code> to upgrade individual subscriptions, or set <code class="bg-white/10 px-1 rounded">Cashier::defaultBillingMode('flexible')</code> in your service provider so all new subscriptions use it automatically.
    </div>
</div>

{{-- Section: Core --}}
<h2 class="text-lg font-semibold text-gray-700 mb-3 flex items-center gap-2"><span class="w-1.5 h-6 bg-stripe rounded-full"></span> Core Flexible Billing</h2>
<div class="grid gap-4 mb-10">
    @include('demo.scenario', ['n' => 1, 'id' => 'flexible-sub', 'color' => 'stripe', 'title' => 'Flexible Subscription', 'fyi' => 'The foundation of everything. Any SaaS app that charges a monthly fee can benefit from the improved proration and invoice handling.', 'desc' => 'Create a subscription with <code class="text-stripe">billing_mode: flexible</code>, verify on Stripe, clean up.'])
    @include('demo.scenario', ['n' => 2, 'id' => 'hybrid', 'color' => 'emerald-500', 'title' => 'Hybrid Billing (Fixed + Metered)', 'fyi' => 'The killer feature. Think base plan + API overage charges, or a seat license + storage usage. Most modern SaaS needs this.', 'desc' => 'Combine $29/mo base + $0.01/API call on one flexible subscription.'])
    @include('demo.scenario', ['n' => 3, 'id' => 'proration-discounts', 'color' => 'indigo-500', 'title' => 'Proration Discounts', 'fyi' => 'Controls how mid-cycle upgrades appear on invoices. Itemized mode shows discount line items separately for full transparency.', 'desc' => 'Compare <code class="text-stripe">itemized</code> vs <code class="text-stripe">included</code> proration modes.'])
    @include('demo.scenario', ['n' => 4, 'id' => 'swap', 'color' => 'teal-500', 'title' => 'Swap / Upgrade / Downgrade', 'fyi' => 'Proves billing mode survives plan changes. Users upgrade/downgrade freely and flexible mode stays active throughout.', 'desc' => 'Starter to Pro to Enterprise and back, all in flexible mode.'])
    @include('demo.scenario', ['n' => 5, 'id' => 'global-default', 'color' => 'cyan-500', 'title' => 'Global Default Billing Mode', 'fyi' => 'One line in your AppServiceProvider and every new subscription uses flexible billing automatically. No code changes elsewhere.', 'desc' => '<code class="text-stripe">Cashier::defaultBillingMode(\'flexible\')</code> in action.'])
    @include('demo.scenario', ['n' => 6, 'id' => 'cancel-resume', 'color' => 'pink-500', 'title' => 'Cancel and Resume', 'fyi' => 'Grace periods work exactly the same in flexible mode. Resume preserves the billing mode so nothing breaks.', 'desc' => 'Cancel with grace period, resume, verify billing mode preserved.'])
</div>

{{-- Section: Schedules & Quotes --}}
<h2 class="text-lg font-semibold text-gray-700 mb-3 flex items-center gap-2"><span class="w-1.5 h-6 bg-blue-500 rounded-full"></span> Subscription Schedules &amp; Quotes</h2>
<div class="grid gap-4 mb-10">
    @include('demo.scenario', ['n' => 7, 'id' => 'schedule', 'color' => 'blue-500', 'title' => 'Multi-Phase Schedule', 'fyi' => 'Pre-plan subscription transitions. Useful for trial-to-paid, annual discount periods, or staged enterprise onboarding.', 'desc' => 'Create 2-phase schedule (Starter to Pro), then release.'])
    @include('demo.scenario', ['n' => 8, 'id' => 'quote', 'color' => 'purple-500', 'title' => 'Quote Lifecycle', 'fyi' => 'For B2B sales workflows. Generate a formal quote, let the customer review it, and when they accept it auto-creates the subscription.', 'desc' => 'Create, finalize, accept a quote — subscription created automatically.'])
    @include('demo.scenario', ['n' => 9, 'id' => 'schedule-from-sub', 'color' => 'sky-500', 'title' => 'Schedule from Subscription', 'fyi' => 'Convert a running subscription into a managed schedule. Useful when a customer commits to a plan change at their next billing cycle.', 'desc' => 'Convert running flexible sub into a schedule (billing mode inherited).'])
</div>

{{-- Section: Usage & Pricing --}}
<h2 class="text-lg font-semibold text-gray-700 mb-3 flex items-center gap-2"><span class="w-1.5 h-6 bg-amber-500 rounded-full"></span> Usage Billing, Credits &amp; Pricing</h2>
<div class="grid gap-4 mb-10">
    @include('demo.scenario', ['n' => 10, 'id' => 'credits', 'color' => 'amber-500', 'title' => 'Billing Credits', 'fyi' => 'Issue promotional credits, handle refunds as balance adjustments, or pre-pay for usage. Works with any subscription model.', 'desc' => 'Add $100 credit, calculate against $150 usage, deduct $30.'])
    @include('demo.scenario', ['n' => 11, 'id' => 'metered-usage', 'color' => 'lime-600', 'title' => 'Metered Usage Reporting', 'fyi' => 'Report consumption events to Stripe in real-time. Stripe aggregates them and includes the charges on the next invoice automatically.', 'desc' => 'Create meter, report 350 usage events, track estimated charges.'])
    @include('demo.scenario', ['n' => 12, 'id' => 'usage-thresholds', 'color' => 'violet-500', 'title' => 'Usage Thresholds', 'fyi' => 'Monitor consumption against limits. Useful for alerting customers approaching their plan limits or calculating overage charges.', 'desc' => '<span class="inline-flex items-center rounded-full bg-violet-100 text-violet-700 px-1.5 py-0.5 text-xs font-medium mr-1">DB only</span> Set threshold, check at 50/100/150%, track overage.'])
    @include('demo.scenario', ['n' => 13, 'id' => 'ratecard', 'color' => 'rose-500', 'title' => 'Rate Card Pricing', 'fyi' => 'Model your pricing locally for display, comparison, or cost estimation without making Stripe API calls. Supports all common pricing models.', 'desc' => '<span class="inline-flex items-center rounded-full bg-rose-100 text-rose-700 px-1.5 py-0.5 text-xs font-medium mr-1">Local only</span> Graduated tiered, volume, package, and flat rate calculations.'])
</div>

{{-- Section: Migration --}}
<h2 class="text-lg font-semibold text-gray-700 mb-3 flex items-center gap-2"><span class="w-1.5 h-6 bg-orange-500 rounded-full"></span> Migration (One-Way)</h2>
<div class="grid gap-4 mb-10">
    @include('demo.scenario', ['n' => 14, 'id' => 'migration', 'color' => 'orange-500', 'title' => 'Classic to Flexible Migration', 'fyi' => 'This is a one-way operation — once migrated, a subscription cannot go back to classic mode. Use this when you are ready to commit. Stripe provides a dedicated /migrate endpoint so you can upgrade subscriptions individually.', 'desc' => '<span class="inline-flex items-center rounded-full bg-orange-100 text-orange-700 px-1.5 py-0.5 text-xs font-medium mr-1">One-way</span> Start classic, migrate via <code class="text-stripe">/migrate</code>, swap price, cancel.'])
</div>

<div class="text-center text-gray-400 text-sm py-4">
    Built with Laravel 13 &middot; Laravel Cashier &middot; Stripe Flexible Billing
    <br>
    <a href="https://github.com/JoshSalway/cashier-stripe/pull/5" class="text-stripe hover:underline">View the PR</a>
    &middot; Based on <a href="https://github.com/laravel/cashier-stripe/pull/1772" class="text-stripe hover:underline">PR #1772</a> by @Diddyy
</div>
@endsection
