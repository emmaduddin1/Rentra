<x-layouts.app
    :title="__('Edit :title', ['title' => $property->title])"
    :heading="$property->isFloor() ? __('Edit floor') : __('Edit property')"
    :subheading="$property->title"
    :back="['href' => route('owner.properties.show', $property), 'label' => __('Back to property')]"
>
    <form method="POST" action="{{ route('owner.properties.update', $property) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <x-property-form
            :property="$property"
            :types="$types"
            :statuses="$statuses"
            :publications="$publications"
            :modes="$modes"
            :max-images="$maxImages"
            :min-images="$minImages"
            :max-videos="$maxVideos"
            :image-slots="$remainingImageSlots"
            :video-slots="$remainingVideoSlots"
        >
            <x-slot:actions>
                <x-form.button type="submit" class="w-full">{{ __('Save changes') }}</x-form.button>
                <a href="{{ route('owner.properties.show', $property) }}" class="w-full btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
            </x-slot:actions>
        </x-property-form>
    </form>

    {{-- FR-PROP-003 - the existing gallery is managed on the property page. --}}
    @if ($property->images->isNotEmpty())
        <p class="mt-6 text-sm text-slate-600">
            {{ __('Manage existing photos on the') }}
            <a href="{{ route('owner.properties.show', $property) }}" class="font-medium underline underline-offset-4">{{ __('property page') }}</a>.
        </p>
    @endif
</x-layouts.app>
