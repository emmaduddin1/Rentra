{{-- NFR-UX-002 - success and error states are always visible. --}}
@if (session('status'))
    <div role="status" class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-600" />
        <p>{{ session('status') }}</p>
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <x-icon name="close" class="mt-0.5 size-5 shrink-0 text-red-600" />
        <p>{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    {{-- SRS 12 - a form-wide summary, with the per-field messages still inline. --}}
    <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <p class="flex items-center gap-2 font-medium">
            <x-icon name="close" class="size-4 shrink-0 text-red-600" />
            {{ trans_choice('Please fix :count field.|Please fix :count fields.', $errors->count(), ['count' => $errors->count()]) }}
        </p>
    </div>
@endif
