<x-filament-panels::page>

    {{-- Sprint Selector --}}
    @if(!$selectedSprint)
        <div class="mb-6">
            <x-filament::section>
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('app.select_sprint') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('app.choose_sprint_board') }}
                    </p>
                </div>

                @if($sprints->isEmpty())
                    <div class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                        <h3 class="text-base font-medium text-gray-900 dark:text-white mb-1">{{ __('app.no_sprints_available') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.no_access_sprints') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach($sprints as $sprint)
                            @php
                                $statusColor = match($sprint->status) {
                                    \App\Models\Sprint::STATUS_ACTIVE => '#10B981',
                                    \App\Models\Sprint::STATUS_COMPLETED => '#3B82F6',
                                    default => '#6B7280',
                                };
                            @endphp
                            <button
                                wire:click="selectSprint({{ $sprint->id }})"
                                class="relative p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow-md transition-all text-left overflow-hidden"
                                style="border-left: 4px solid {{ $statusColor }};"
                            >
                                <div class="inline-flex px-2.5 py-1 rounded text-xs font-semibold mb-3 text-white"
                                     style="background-color: {{ $statusColor }};">
                                    {{ \App\Models\Sprint::statusOptions()[$sprint->status] ?? $sprint->status }}
                                </div>

                                <h3 class="font-semibold text-base text-gray-900 dark:text-white line-clamp-2">
                                    {{ $sprint->name }}
                                </h3>

                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                    @if($sprint->start_date)
                                        <span>{{ $sprint->start_date->format('d/m/Y') }}</span>
                                    @endif
                                    @if($sprint->end_date)
                                        <span>&rarr; {{ $sprint->end_date->format('d/m/Y') }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    @else
        {{-- Sprint Switcher --}}
        <div class="mb-4" x-data="{ open: false }">
            <div class="relative">
                @php
                    $selectedStatusColor = match($selectedSprint->status) {
                        \App\Models\Sprint::STATUS_ACTIVE => '#10B981',
                        \App\Models\Sprint::STATUS_COMPLETED => '#3B82F6',
                        default => '#6B7280',
                    };
                @endphp
                <button
                    @click="open = !open"
                    @click.away="open = false"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-800 border-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    style="border-color: {{ $selectedStatusColor }};"
                >
                    <span class="px-2 py-0.5 rounded text-xs font-semibold text-white"
                          style="background-color: {{ $selectedStatusColor }};">
                        {{ \App\Models\Sprint::statusOptions()[$selectedSprint->status] ?? $selectedSprint->status }}
                    </span>
                    <span>{{ $selectedSprint->name }}</span>
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute top-full left-0 mt-2 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto"
                    style="display: none;"
                >
                    <div class="p-2">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('app.switch_sprint') }}
                        </div>
                        @foreach($sprints as $sprint)
                            <button
                                wire:click="selectSprint({{ $sprint->id }})"
                                @click="open = false"
                                class="w-full flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left {{ $sprint->id === $selectedSprint->id ? 'bg-gray-50 dark:bg-gray-700' : '' }}"
                            >
                                <div class="flex-1 min-w-0 text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $sprint->name }}
                                </div>
                                @if($sprint->id === $selectedSprint->id)
                                    <svg class="w-4 h-4 flex-shrink-0 text-primary-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($selectedSprint)
        <div
            x-data="{
                draggingTicket: null,

                moveTicketToStatus(ticketId, statusId) {
                    $wire.call('moveTicket', parseInt(ticketId), parseInt(statusId));
                },

                init() {
                    this.$nextTick(() => {
                        this.removeAllEventListeners();
                        this.attachAllEventListeners();
                    });
                },

                removeAllEventListeners() {
                    const tickets = document.querySelectorAll('.ticket-card');
                    tickets.forEach(ticket => {
                        ticket.removeAttribute('draggable');
                        const newTicket = ticket.cloneNode(true);
                        ticket.parentNode.replaceChild(newTicket, ticket);
                    });

                    const columns = document.querySelectorAll('.status-column');
                    columns.forEach(column => {
                        const newColumn = column.cloneNode(false);
                        while (column.firstChild) {
                            newColumn.appendChild(column.firstChild);
                        }
                        if (column.parentNode) {
                            column.parentNode.replaceChild(newColumn, column);
                        }
                    });
                },

                attachAllEventListeners() {
                    @if(!$this->canMoveTickets())
                        return;
                    @endif

                    const tickets = document.querySelectorAll('.ticket-card');
                    tickets.forEach(ticket => {
                        ticket.setAttribute('draggable', true);

                        ticket.addEventListener('dragstart', (e) => {
                            this.draggingTicket = ticket.getAttribute('data-ticket-id');
                            ticket.classList.add('opacity-50');
                            e.dataTransfer.effectAllowed = 'move';
                        });

                        ticket.addEventListener('dragend', () => {
                            ticket.classList.remove('opacity-50');
                            this.draggingTicket = null;
                        });
                    });

                    const columns = document.querySelectorAll('.status-column');
                    columns.forEach(column => {
                        column.addEventListener('dragover', (e) => {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                            column.classList.add('bg-primary-50', 'dark:bg-primary-950');
                        });

                        column.addEventListener('dragleave', () => {
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                        });

                        column.addEventListener('drop', (e) => {
                            e.preventDefault();
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');

                            if (this.draggingTicket) {
                                const statusId = column.getAttribute('data-status-id');
                                const ticketId = this.draggingTicket;
                                this.draggingTicket = null;
                                this.moveTicketToStatus(ticketId, statusId);
                            }
                        });
                    });
                }
            }"
            x-init="init()"
            @ticket-moved.window="init()"
            @ticket-updated.window="init()"
            @refresh-board.window="init()"
            wire:key="sprint-board-container-{{ $selectedSprint->id }}"
            class="relative overflow-x-auto pb-6"
            id="board-container"
        >
            <div class="inline-flex gap-4 pb-2 min-w-full">
                @foreach ($this->sprintStatuses as $status)
                    <div
                        wire:key="sprint-status-column-{{ $status->id }}"
                        class="status-column rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50 dark:bg-gray-900 w-[calc(85vw-2rem)] min-w-[280px] max-w-[350px] h-[700px] sm:w-[calc((100vw-6rem)/2)] sm:h-[750px] lg:w-[calc((100vw-8rem)/3)] lg:h-[800px] xl:w-[calc((100vw-10rem)/4)] xl:h-[850px]"
                        data-status-id="{{ $status->id }}"
                    >
                        <div
                            class="px-4 py-3 rounded-t-xl border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
                            style="background-color: {{ $status->color ?? '#f3f4f6' }};"
                        >
                            <h3 class="font-medium flex items-center gap-2" style="color: white; text-shadow: 0px 0px 1px rgba(0,0,0,0.5);">
                                <span>{{ $status->name }}</span>
                                <span class="text-sm opacity-80">{{ $status->tickets->count() }}</span>
                                @if($status->is_completed)
                                    <div class="flex items-center justify-center w-6 h-6 bg-green-500 rounded-full border-2 border-white shadow-lg" title="Completed Status">
                                        <svg class="w-3 h-3 text-white font-bold" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                @endif
                            </h3>
                        </div>

                        <div class="p-3 flex flex-col gap-3 flex-1 overflow-y-auto" style="max-height: calc(100% - 60px);">
                            @foreach ($status->tickets as $ticket)
                                <div
                                    wire:key="sprint-ticket-{{ $status->id }}-{{ $ticket->id }}"
                                    class="ticket-card bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 cursor-move"
                                    data-ticket-id="{{ $ticket->id }}"
                                >
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400 px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded truncate max-w-[120px] sm:max-w-none">
                                            {{ $ticket->uuid }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            @if ($ticket->priority)
                                                <span class="text-xs px-1.5 py-0.5 rounded whitespace-nowrap text-white font-medium" style="background-color: {{ $ticket->priority->color }};">
                                                    {{ $ticket->priority->name }}
                                                </span>
                                            @endif
                                            @if ($ticket->due_date)
                                                <span class="text-xs px-1.5 py-0.5 rounded whitespace-nowrap {{ $ticket->due_date->isPast() ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' }}">
                                                    {{ $ticket->due_date->translatedFormat('d M') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Project badge: sprint tickets can come from different boards --}}
                                    @if ($ticket->project)
                                        @php
                                            $color = $ticket->project->color ?? '#6B7280';
                                            $hex = ltrim($color, '#');
                                            $r = hexdec(substr($hex, 0, 2));
                                            $g = hexdec(substr($hex, 2, 2));
                                            $b = hexdec(substr($hex, 4, 2));
                                            $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                                            $textColor = $brightness > 155 ? '#1F2937' : '#FFFFFF';
                                        @endphp
                                        <div class="inline-flex px-2 py-0.5 rounded text-xs font-semibold mb-2"
                                             style="background-color: {{ $color }}; color: {{ $textColor }};">
                                            {{ $ticket->project->ticket_prefix ?? $ticket->project->name }}
                                        </div>
                                    @endif

                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $ticket->name }}</h4>

                                    @if ($ticket->description)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3 line-clamp-2">
                                            {{ \Illuminate\Support\Str::limit(strip_tags($ticket->description), 100) }}
                                        </p>
                                    @endif

                                    <div class="flex justify-between items-center mt-2">
                                        @if ($ticket->assignees->isNotEmpty())
                                            <div class="flex flex-wrap gap-1 max-w-[180px]">
                                                @foreach($ticket->assignees->take(2) as $assignee)
                                                    <div class="inline-flex items-center px-2 py-1 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 gap-1">
                                                        <span class="w-4 h-4 rounded-full bg-primary-500 flex items-center justify-center text-xs text-white flex-shrink-0">
                                                            {{ substr($assignee->name, 0, 1) }}
                                                        </span>
                                                        <span class="text-xs font-medium truncate">{{ \Illuminate\Support\Str::limit($assignee->name, 8) }}</span>
                                                    </div>
                                                @endforeach
                                                @if($ticket->assignees->count() > 2)
                                                    <div class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400">
                                                        <span class="text-xs font-medium">+{{ $ticket->assignees->count() - 2 }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 dark:text-gray-500 mr-1 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-xs font-medium">{{ __('app.unassigned') }}</span>
                                            </div>
                                        @endif

                                        <a
                                            href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket->id]) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400 flex-shrink-0"
                                        >
                                            <x-heroicon-m-eye class="w-4 h-4" />
                                        </a>
                                    </div>
                                </div>
                            @endforeach

                            @if ($status->tickets->isEmpty())
                                <div class="flex items-center justify-center h-24 text-gray-500 dark:text-gray-400 text-sm italic border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                                    {{ __('app.no_tickets') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($this->sprintStatuses->isEmpty())
                    <div class="w-full flex items-center justify-center h-40 text-gray-500 dark:text-gray-400">
                        {{ __('app.no_sprint_status_columns') }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
