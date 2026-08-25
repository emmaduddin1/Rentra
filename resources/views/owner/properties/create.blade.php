<x-layouts.app
    :title="__('Add property')"
    :heading="__('Add property')"
    :subheading="__('Save it as a draft first, or publish it straight away.')"
    :back="['href' => route('owner.properties.index'), 'label' => __('Properties')]"
>
    <form method="POST" action="{{ route('owner.properties.store') }}" enctype="multipart/form-data">
        @csrf

        <x-property-form
            :property="$property"
            :types="$types"
            :statuses="$statuses"
            :publications="$publications"
            :modes="$modes"
            :max-images="$maxImages"
            :min-images="$minImages"
            :max-videos="$maxVideos"
        >
            <x-slot:actions>
                <x-form.button type="submit" class="w-full">{{ __('Save property') }}</x-form.button>
                <a href="{{ route('owner.properties.index') }}" class="w-full btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
            </x-slot:actions>
        </x-property-form>
    </form>
</x-layouts.app>
