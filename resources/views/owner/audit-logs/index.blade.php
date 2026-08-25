<x-layouts.app
    :title="__('Audit trail')"
    :heading="__('Audit trail')"
    :subheading="__('Who changed what across your portfolio, and when.')"
>
    <form method="GET" action="{{ route('owner.audit-logs.index') }}" class="mb-6 card p-4">
        <div class="flex flex-wrap items-end gap-3">
            <x-form.field :label="__('Action')" name="action">
                <x-form.select
                    name="action"
                    :placeholder="__('All actions')"
                    :selected="$filters['action'] ?? null"
                    :options="$actions->mapWithKeys(fn ($action) => [$action => $action])->all()"
                />
            </x-form.field>

            <x-form.button type="submit" variant="secondary">{{ __('Filter') }}</x-form.button>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state
            :title="__('Nothing recorded yet.')"
            :description="__('Publication, assignment, approval, rejection, reopening, and maintenance changes are all recorded here.')"
        />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('When') }}</th>
                            <th scope="col">{{ __('Action') }}</th>
                            <th scope="col">{{ __('By') }}</th>
                            <th scope="col">{{ __('Subject') }}</th>
                            <th scope="col">{{ __('Change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="align-top">
                                <td class="whitespace-nowrap text-slate-600">
                                    {{ $log->created_at?->format('d M Y, H:i') }}
                                </td>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $log->action }}</span>
                                    @if ($log->reason)
                                        <span class="mt-1 block text-xs text-slate-500">{{ $log->reason }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="text-slate-600">{{ trim(class_basename($log->subject_type ?? '').($log->subject_id ? ' #'.$log->subject_id : '')) }}</td>
                                <td>
                                    @if ($log->new_values)
                                        <dl class="space-y-0.5 text-xs">
                                            @foreach ($log->new_values as $key => $value)
                                                <div class="flex flex-wrap gap-1">
                                                    <dt class="text-slate-500">{{ $key }}:</dt>
                                                    <dd class="text-slate-800">
                                                        @if (is_array($log->old_values) && array_key_exists($key, $log->old_values))
                                                            <span class="text-slate-400 line-through">{{ \Illuminate\Support\Str::limit((string) $log->old_values[$key], 40) }}</span>
                                                            &rarr;
                                                        @endif
                                                        {{ \Illuminate\Support\Str::limit((string) $value, 40) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @else
                                        <span class="text-xs text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
</x-layouts.app>
