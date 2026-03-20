<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flexible Billing Demo — Laravel Cashier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { stripe: '#635BFF', 'stripe-dark': '#4B45C6' } } } }</script>
    <style>
        .scenario-card { transition: all 0.2s; }
        .scenario-card:hover { transform: translateY(-1px); box-shadow: 0 8px 20px -5px rgba(0,0,0,0.08); }
        .log-entry { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body class="h-full">
    <nav class="bg-stripe shadow-lg">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center space-x-3">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                    </svg>
                    <span class="text-white font-bold text-lg">Flexible Billing Demo</span>
                    <span class="text-white/60 text-sm">Laravel Cashier &middot; Laravel 13</span>
                </div>
                <div class="text-white/80 text-sm">
                    Stripe SDK <span class="font-mono text-green-300">{{ \Stripe\Stripe::VERSION }}</span>
                    &middot; API <span class="font-mono text-green-300">{{ \Stripe\Util\ApiVersion::CURRENT }}</span>
                </div>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    <script>
        function appendStep(logEl, step) {
            const div = document.createElement('div');
            div.className = 'log-entry py-2 border-b border-gray-100 last:border-0';
            const icon = step.success ? '&#10004;' : '&#10008;';
            const color = step.success ? 'text-green-600' : 'text-red-600';
            let detail = '';
            if (step.html) { detail = `<div class="mt-2">${step.html}</div>`; }
            else if (step.detail) { detail = `<pre class="text-xs text-gray-500 mt-1 bg-gray-50 rounded p-2">${JSON.stringify(step.detail, null, 2)}</pre>`; }
            div.innerHTML = `<div class="flex items-start gap-2"><span class="${color} font-bold text-sm mt-0.5">${icon}</span><div class="flex-1"><div class="font-medium text-sm text-gray-900">${step.label}</div>${detail}${step.error ? `<div class="text-xs text-red-600 mt-1">${step.error}</div>` : ''}</div><span class="text-xs text-gray-400 shrink-0">${step.duration || ''}</span></div>`;
            logEl.appendChild(div);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function runScenario(id, button) {
            return new Promise(resolve => {
                const logEl = document.getElementById('log-' + id);
                const statusEl = document.getElementById('status-' + id);
                button.disabled = true;
                button.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Running...';
                logEl.innerHTML = '';
                statusEl.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800';
                statusEl.textContent = 'Running';

                const evtSource = new EventSource('/stream/' + id);
                let ok = true;

                evtSource.addEventListener('step', e => {
                    const step = JSON.parse(e.data);
                    appendStep(logEl, step);
                    if (!step.success) ok = false;
                });

                evtSource.addEventListener('done', e => {
                    evtSource.close();
                    const data = JSON.parse(e.data);
                    const success = data.success && ok;
                    statusEl.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + (success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                    statusEl.textContent = success ? 'Passed' : 'Failed';
                    const t = document.createElement('div');
                    t.className = 'mt-3 pt-3 border-t border-gray-200 text-right text-xs text-gray-400';
                    t.textContent = 'Total: ' + (data.totalMs || '?') + 'ms';
                    logEl.appendChild(t);
                    button.disabled = false;
                    button.innerHTML = 'Run';
                    resolve(success);
                });

                evtSource.onerror = () => {
                    evtSource.close();
                    statusEl.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800';
                    statusEl.textContent = 'Error';
                    button.disabled = false;
                    button.innerHTML = 'Run';
                    resolve(false);
                };
            });
        }

        const ALL = ['flexible-sub','migration','hybrid','proration-discounts','swap','global-default','cancel-resume','schedule','quote','schedule-from-sub','credits','metered-usage','usage-thresholds','ratecard'];

        async function runAll(btn) {
            const prog = document.getElementById('run-all-progress');
            prog.classList.remove('hidden');
            const t0 = performance.now();
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Running All...';
            let p = 0, f = 0;
            for (let i = 0; i < ALL.length; i++) {
                prog.textContent = `${i+1}/${ALL.length} (${((performance.now()-t0)/1000).toFixed(1)}s)`;
                const b = document.querySelector(`[onclick*="'${ALL[i]}'"]`);
                if (b) { const ok = await runScenario(ALL[i], b); ok ? p++ : f++; }
            }
            const sec = ((performance.now()-t0)/1000).toFixed(1);
            btn.disabled = false;
            if (f === 0) {
                btn.innerHTML = `All ${p} Passed`;
                btn.className = 'bg-green-600 text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-lg flex items-center gap-2 cursor-default';
            } else {
                btn.innerHTML = `${p} Passed, ${f} Failed`;
                btn.className = 'bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-lg flex items-center gap-2';
                btn.onclick = () => runAll(btn);
            }
            prog.textContent = `${p}/${ALL.length} in ${sec}s`;
        }
    </script>
</body>
</html>
