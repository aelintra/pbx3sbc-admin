@if($dispatchers->isEmpty())
    <p class="text-sm text-gray-500">No existing destinations</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 font-medium text-gray-700">Destination</th>
                    <th class="px-4 py-2 font-medium text-gray-700">Weight</th>
                    <th class="px-4 py-2 font-medium text-gray-700">Priority</th>
                    <th class="px-4 py-2 font-medium text-gray-700">State</th>
                    <th class="px-4 py-2 font-medium text-gray-700">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($dispatchers as $dispatcher)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-sm">{{ $dispatcher->destination }}</td>
                        <td class="px-4 py-2">{{ $dispatcher->weight }}</td>
                        <td class="px-4 py-2">{{ $dispatcher->priority }}</td>
                        <td class="px-4 py-2">
                            @if($dispatcher->state == 0)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ $dispatcher->description ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
