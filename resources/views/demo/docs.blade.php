@extends('demo.layout')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Documentation</h1>
        <p class="mt-2 text-gray-600">Complete guide to using flexible billing in your Laravel application.</p>
    </div>
    <a href="/" class="text-stripe hover:text-stripe-dark text-sm font-medium flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to Demo
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-8 lg:p-12 prose prose-stripe max-w-none
        prose-headings:font-bold prose-headings:text-gray-900
        prose-h1:text-3xl prose-h1:mb-6 prose-h1:pb-4 prose-h1:border-b prose-h1:border-gray-200
        prose-h2:text-2xl prose-h2:mt-12 prose-h2:mb-4 prose-h2:pt-8 prose-h2:border-t prose-h2:border-gray-100
        prose-h3:text-lg prose-h3:mt-8 prose-h3:mb-3
        prose-p:text-gray-600 prose-p:leading-relaxed
        prose-a:text-stripe prose-a:no-underline hover:prose-a:underline
        prose-code:text-stripe prose-code:bg-gray-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-sm prose-code:font-normal prose-code:before:content-none prose-code:after:content-none
        prose-pre:bg-gray-900 prose-pre:text-green-200 prose-pre:rounded-xl prose-pre:shadow-lg
        prose-table:text-sm
        prose-th:bg-gray-50 prose-th:px-4 prose-th:py-2 prose-th:text-left prose-th:font-semibold prose-th:text-gray-700
        prose-td:px-4 prose-td:py-2 prose-td:border-t prose-td:border-gray-100
        prose-blockquote:border-l-stripe prose-blockquote:bg-stripe/5 prose-blockquote:rounded-r-lg prose-blockquote:py-1 prose-blockquote:px-4
        prose-strong:text-gray-900
        prose-li:text-gray-600
        prose-ul:space-y-1
    ">
        {!! $content !!}
    </div>
</div>

<div class="mt-8 text-center text-gray-400 text-sm py-4">
    <a href="/" class="text-stripe hover:underline">Back to Demo</a>
    &middot;
    <a href="https://github.com/JoshSalway/cashier-flexible-billing-demo/blob/master/docs/flexible-billing.md" class="text-stripe hover:underline">View on GitHub</a>
</div>
@endsection
