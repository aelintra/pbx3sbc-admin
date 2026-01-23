<div class="p-4">
    <div class="mb-4">
        <p class="text-sm text-gray-600 mb-2">
            {{ $destinations->count() }} destination(s) configured for this domain.
        </p>
        <p class="text-xs text-gray-500">
            Use the "Add Destination" action button in the table to add new destinations. 
            Click on a destination below to edit or delete it.
        </p>
    </div>

    @if($destinations->isEmpty())
        <div class="text-center py-8 text-gray-500">
            <p class="mb-4">No destinations configured.</p>
            <p class="text-sm">Use the "Add Destination" action button in the table to add one.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 font-medium text-gray-700">Destination</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Weight</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Priority</th>
                        <th class="px-4 py-2 font-medium text-gray-700">State</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Description</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($destinations as $destination)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-sm">{{ $destination->destination }}</td>
                            <td class="px-4 py-2">{{ $destination->weight }}</td>
                            <td class="px-4 py-2">{{ $destination->priority }}</td>
                            <td class="px-4 py-2">
                                @if($destination->state == 0)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $destination->description ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('filament.admin.resources.dispatchers.edit', $destination) }}" 
                                       target="_blank"
                                       class="text-primary-600 hover:text-primary-900 text-xs font-medium underline">
                                        Edit
                                    </a>
                                    <span class="text-gray-400">|</span>
                                    <a href="{{ route('filament.admin.resources.dispatchers.index') }}?tableFilters[setid][value]={{ $domain->setid }}" 
                                       target="_blank"
                                       class="text-primary-600 hover:text-primary-900 text-xs font-medium underline">
                                        View All
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
