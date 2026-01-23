<div class="p-4">
    @if($destinations->isEmpty())
        <div class="text-center py-8 text-gray-500">
            <p class="mb-4">No destinations configured for this domain.</p>
            <a href="{{ route('filament.admin.resources.call-routes.edit', $domain) }}" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add First Destination
            </a>
        </div>
    @else
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">{{ $destinations->count() }} destination(s) configured</p>
            <a href="{{ route('filament.admin.resources.call-routes.edit', $domain) }}" 
               class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Destination
            </a>
        </div>
    @endif

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
                                <span class="text-gray-400 text-xs">(opens in new tab)</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Delete via <a href="{{ route('filament.admin.resources.dispatchers.index') }}" target="_blank" class="text-primary-600 hover:underline">Destinations</a> panel
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
