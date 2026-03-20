<div class="scenario-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-{{ $color }} text-white text-sm font-bold shrink-0">{{ $n }}</span>
                    <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $title }}</h2>
                    <span id="status-{{ $id }}" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 shrink-0">Ready</span>
                </div>
                <p class="text-gray-500 text-sm mt-1 ml-11">{!! $desc !!}</p>
                @if(isset($fyi) && $fyi)
                    <p class="text-gray-400 text-xs mt-1 ml-11 italic">{{ $fyi }}</p>
                @endif
            </div>
            <button onclick="runScenario('{{ $id }}', this)" class="bg-{{ $color }} hover:opacity-90 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all shrink-0 ml-4">
                Run
            </button>
        </div>
        <div id="log-{{ $id }}" class="mt-3 max-h-[32rem] overflow-y-auto"></div>
    </div>
</div>
