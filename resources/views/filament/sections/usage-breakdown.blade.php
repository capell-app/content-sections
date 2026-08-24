@php
    /** @var \Capell\ContentSections\Data\SectionUsageSummaryData $usage */
    $grouped = $usage->groupedDestinations();
    $attachmentDestinations = $grouped['attachment'] ?? [];
    $widgetDestinations = $grouped['widget'] ?? [];
@endphp

<div class="space-y-4 text-sm">
    <p class="font-medium">{{ $usage->impactSummary() }}</p>

    @unless ($usage->isAuthoritative)
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('capell-content-sections::message.usage_partial_notice') }}
        </p>
    @endunless

    @if (! $usage->isUsed())
        <p class="text-gray-500 dark:text-gray-400">{{ __('capell-content-sections::message.usage_none_detail') }}</p>
    @else
        @if ($widgetDestinations !== [])
            <div class="space-y-2">
                <h3 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    {{ __('capell-content-sections::heading.usage_widget_placements') }}
                </h3>
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($widgetDestinations as $destination)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    @if ($destination->url)
                                        <a href="{{ $destination->url }}" class="underline hover:no-underline" target="_blank" rel="noopener noreferrer">{{ $destination->title }}</a>
                                    @else
                                        {{ $destination->title }}
                                    @endif
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $destination->type }}
                                    @if ($destination->context)
                                        &middot; {{ $destination->context }}
                                    @endif
                                    @if ($destination->site)
                                        &middot; {{ $destination->site }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attachmentDestinations !== [])
            <div class="space-y-2">
                <h3 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    {{ __('capell-content-sections::heading.usage_attachments') }}
                </h3>
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($attachmentDestinations as $destination)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    @if ($destination->url)
                                        <a href="{{ $destination->url }}" class="underline hover:no-underline" target="_blank" rel="noopener noreferrer">{{ $destination->title }}</a>
                                    @else
                                        {{ $destination->title }}
                                    @endif
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $destination->type }}
                                    @if ($destination->site)
                                        &middot; {{ $destination->site }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($usage->totalCount() > count($usage->destinations))
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice('capell-content-sections::message.usage_destinations_more', $usage->totalCount() - count($usage->destinations), ['count' => $usage->totalCount() - count($usage->destinations)]) }}
            </p>
        @endif
    @endif
</div>
