<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
        <x-pulse::card-header name="Schedule">
            <x-slot:icon>
                <x-pulse::icons.command-line />
            </x-slot:icon>
            <x-slot:actions>
                <x-pulse::select wire:model.live="selectedcountry" label="Show" :options="['ALL' => 'All', 'UGA' => 'UGA', 'RWA' => 'RWA', 'MDG' => 'MDG']" />
            </x-slot:actions>
        </x-pulse::card-header>
        <x-pulse::scroll :expand="$expand" wire:poll.5s="">
            @if($events->isEmpty())
                <x-pulse::no-results />
            @else
                <x-pulse::table>
                    <x-pulse::thead>
                        <tr>
                            <x-pulse::th>Task</x-pulse::th>
                            <x-pulse::th>Next Due</x-pulse::th>
                            <x-pulse::th>Expression</x-pulse::th>
                            <x-pulse::th>Created At</x-pulse::th>

                        </tr>
                    </x-pulse::thead>
                    <tbody>
                    @foreach($events as $event)
                        <tr class="h-2 first:h-0" wire:key="{{ $event['command'] }}-spacer"></tr>
                            <tr wire:key="{{ $event['command'] }}-row">
                                <x-pulse::td>{{ $event['command'] }}</x-pulse::td>
                                <x-pulse::td>{{ $event['next_due'] }}</x-pulse::td>
                                <x-pulse::td>{{ $event['expression'] }}</x-pulse::td>
                                <x-pulse::td>{{ $event['created_at'] }}</x-pulse::td>

                            </tr>
                        @endforeach
                    </tbody>
                </x-pulse::table>
            @endempty
        </x-pulse::scroll>
    </x-pulse::card>
</div>
