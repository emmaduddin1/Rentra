@props(['heading' => null, 'subheading' => null, 'back' => null])

<x-nav.authenticated-app-bar :heading="$heading ?? null" :subheading="$subheading ?? null" :back="$back ?? null" />
