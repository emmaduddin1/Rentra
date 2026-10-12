{{-- SRS FR-PROP-001 / BR-013 - a separately rentable floor of a house. --}}
<x-layouts.app
    :title="__('Add floor')"
    :heading="__('Add floor')"
    :subheading="__('A floor of :house, rented and listed on its own.', ['house' => $house->title])"
    :back="['href' => route('owner.properties.show', $house), 'label' => $house->title]"
>
    <form method="POST" action="{{ route('owner.properties.floors.store', $house) }}" enctype="multipart/form-data">
        @csrf

        <x-property-form
            :property="$floor"
            :house="$house"
            :amenities="$amenities"
            :area-units="$areaUnits"
            :furnishing-statuses="$furnishingStatuses"
            :statuses="$statuses"
            :publications="$publications"
            :max-images="$maxImages"
            :min-images="$minImages"
            :max-videos="$maxVideos"
        >
            <x-slot:actions>
                <x-form.button type="submit" class="w-full">{{ __('Save floor') }}</x-form.button>
                <a href="{{ route('owner.properties.show', $house) }}" class="w-full btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
            </x-slot:actions>
        </x-property-form>
    </form>
</x-layouts.app>
