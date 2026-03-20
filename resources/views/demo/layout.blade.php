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
        // Restore settings and results from localStorage on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Restore settings
            const savedName = localStorage.getItem('demoSettingName');
            const savedDomain = localStorage.getItem('demoSettingDomain');
            const savedDashboard = localStorage.getItem('demoSettingDashboard');
            if (savedName) document.getElementById('setting-name').value = savedName;
            if (savedDomain) document.getElementById('setting-domain').value = savedDomain;
            if (savedDashboard === '1') document.getElementById('dashboard-toggle').checked = true;

            // Auto-save settings on change
            document.getElementById('setting-name')?.addEventListener('input', e => localStorage.setItem('demoSettingName', e.target.value));
            document.getElementById('setting-domain')?.addEventListener('input', e => localStorage.setItem('demoSettingDomain', e.target.value));
            document.getElementById('dashboard-toggle')?.addEventListener('change', e => localStorage.setItem('demoSettingDashboard', e.target.checked ? '1' : '0'));
            const saved = localStorage.getItem('demoResults');
            if (saved) {
                const results = JSON.parse(saved);
                Object.entries(results).forEach(([id, data]) => {
                    const logEl = document.getElementById('log-' + id);
                    const statusEl = document.getElementById('status-' + id);
                    if (!logEl || !statusEl) return;

                    data.steps.forEach(step => appendStep(logEl, step));

                    const t = document.createElement('div');
                    t.className = 'mt-3 pt-3 border-t border-gray-200 text-right text-xs text-gray-400';
                    t.textContent = 'Total: ' + (data.totalMs || '?') + 'ms';
                    logEl.appendChild(t);

                    statusEl.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + (data.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                    statusEl.textContent = data.success ? 'Passed' : 'Failed';
                });
                document.getElementById('reset-btn')?.classList.remove('hidden');
            }
        });

        function saveResult(id, steps, success, totalMs) {
            const saved = JSON.parse(localStorage.getItem('demoResults') || '{}');
            saved[id] = { steps, success, totalMs };
            localStorage.setItem('demoResults', JSON.stringify(saved));
            document.getElementById('reset-btn')?.classList.remove('hidden');
        }

        function resetAll() {
            localStorage.removeItem('demoResults');
            localStorage.removeItem('demoSettingName');
            localStorage.removeItem('demoSettingDomain');
            localStorage.removeItem('demoSettingDashboard');
            const nameEl = document.getElementById('setting-name');
            const domainEl = document.getElementById('setting-domain');
            const dashEl = document.getElementById('dashboard-toggle');
            if (nameEl) nameEl.value = 'Demo User';
            if (domainEl) domainEl.value = 'cashier-demo.test';
            if (dashEl) dashEl.checked = false;
            document.querySelectorAll('[id^="log-"]').forEach(el => el.innerHTML = '');
            document.querySelectorAll('[id^="status-"]').forEach(el => {
                el.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600';
                el.textContent = 'Ready';
            });
            document.getElementById('reset-btn')?.classList.add('hidden');
            const prog = document.getElementById('run-all-progress');
            if (prog) { prog.classList.add('hidden'); prog.textContent = ''; }
            const btn = document.getElementById('run-all-btn');
            if (btn) {
                btn.className = 'bg-gray-900 hover:bg-gray-800 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors shadow-lg flex items-center gap-2';
                btn.innerHTML = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>Run All 14 Scenarios';
                btn.onclick = () => runAll(btn);
            }
        }

        function appendStep(logEl, step) {
            const div = document.createElement('div');
            div.className = 'log-entry py-2 border-b border-gray-100 last:border-0';
            const icon = step.success ? '&#10004;' : '&#10008;';
            const color = step.success ? 'text-green-600' : 'text-red-600';
            let detail = '';
            if (step.html) { detail = `<div class="mt-2">${step.html}</div>`; }
            else if (step.detail) { detail = `<pre class="text-xs text-gray-500 mt-1 bg-gray-50 rounded p-2">${JSON.stringify(step.detail, null, 2)}</pre>`; }

            let linksHtml = '';
            if (step.links && step.links.length) {
                linksHtml = '<div class="mt-1.5 flex gap-2 flex-wrap">' + step.links.map(l =>
                    `<a href="${l.url}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs text-stripe hover:text-stripe-dark font-medium"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>${l.label}</a>`
                ).join('') + '</div>';
            }

            div.innerHTML = `<div class="flex items-start gap-2"><span class="${color} font-bold text-sm mt-0.5">${icon}</span><div class="flex-1"><div class="font-medium text-sm text-gray-900">${step.label}</div>${detail}${linksHtml}${step.error ? `<div class="text-xs text-red-600 mt-1">${step.error}</div>` : ''}</div><span class="text-xs text-gray-400 shrink-0">${step.duration || ''}</span></div>`;
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

                const params = new URLSearchParams();
                if (document.getElementById('dashboard-toggle')?.checked) params.set('dashboard', '1');
                const nameEl = document.getElementById('setting-name');
                const domainEl = document.getElementById('setting-domain');
                if (nameEl?.value && nameEl.value !== 'Demo User') params.set('name', nameEl.value);
                if (domainEl?.value && domainEl.value !== 'cashier-demo.test') params.set('domain', domainEl.value);
                const qs = params.toString() ? '?' + params.toString() : '';
                const evtSource = new EventSource('/stream/' + id + qs);
                let ok = true;
                const collectedSteps = [];

                evtSource.addEventListener('step', e => {
                    const step = JSON.parse(e.data);
                    collectedSteps.push(step);
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
                    saveResult(id, collectedSteps, success, data.totalMs);
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

        const ALL = [
            'flexible-sub','hybrid','proration-discounts','swap','global-default','cancel-resume',
            'schedule','quote','schedule-from-sub',
            'credits','metered-usage','usage-thresholds','ratecard',
            'migration',
        ];

        let stopRequested = false;

        async function runAll(btn) {
            stopRequested = false;
            const prog = document.getElementById('run-all-progress');
            const stopBtn = document.getElementById('stop-btn');
            prog.classList.remove('hidden');
            stopBtn.classList.remove('hidden');
            const t0 = performance.now();
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Running All...';
            let p = 0, f = 0, ran = 0;
            for (let i = 0; i < ALL.length; i++) {
                if (stopRequested) break;
                prog.textContent = `${i+1}/${ALL.length} (${((performance.now()-t0)/1000).toFixed(1)}s)`;
                const b = document.querySelector(`[onclick*="'${ALL[i]}'"]`);
                if (b) { const ok = await runScenario(ALL[i], b); ok ? p++ : f++; ran++; }
            }
            stopBtn.classList.add('hidden');
            const sec = ((performance.now()-t0)/1000).toFixed(1);
            const stopped = stopRequested ? ` (stopped after ${ran})` : '';
            btn.disabled = false;
            if (f === 0 && !stopRequested) {
                btn.innerHTML = `All ${p} Passed`;
                btn.className = 'bg-green-600 text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-lg flex items-center gap-2 cursor-default';
            } else if (stopRequested) {
                btn.innerHTML = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>Resume';
                btn.className = 'bg-gray-900 hover:bg-gray-800 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors shadow-lg flex items-center gap-2';
                btn.onclick = () => runAll(btn);
            } else {
                btn.innerHTML = `${p} Passed, ${f} Failed`;
                btn.className = 'bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-lg flex items-center gap-2';
                btn.onclick = () => runAll(btn);
            }
            prog.textContent = `${p}/${ran} passed in ${sec}s${stopped}`;
        }

        function stopAll() {
            stopRequested = true;
            const stopBtn = document.getElementById('stop-btn');
            stopBtn.textContent = 'Stopping after current...';
            stopBtn.disabled = true;
        }
    </script>
</body>
</html>
