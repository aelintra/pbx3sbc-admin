<tr class="hover:bg-gray-50 destination-row" data-destination-id="{{ $destination->id }}">
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
            <button type="button" 
                    class="edit-destination-btn text-primary-600 hover:text-primary-900 text-xs font-medium underline bg-transparent border-0 p-0 cursor-pointer"
                    data-destination-id="{{ $destination->id }}">
                Edit
            </button>
            <span class="text-gray-400">|</span>
            <form method="POST" 
                  action="{{ route('admin.dispatchers.destroy', $destination) }}"
                  onsubmit="return confirm('Are you sure you want to delete this destination? This will reload OpenSIPS modules.');"
                  class="inline delete-destination-form"
                  data-destination-id="{{ $destination->id }}">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="text-danger-600 hover:text-danger-900 text-xs font-medium underline bg-transparent border-0 p-0 cursor-pointer">
                    Delete
                </button>
            </form>
        </div>
    </td>
</tr>
