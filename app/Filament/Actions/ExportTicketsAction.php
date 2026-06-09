<?php

namespace App\Filament\Actions;

use Filament\Schemas\Components\Section;
use App\Exports\TicketsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTicketsAction
{
    public static function make(): Action
    {
        return Action::make('export_tickets')
            ->label(__('app.export_to_excel'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->schema([
                Section::make(__('app.export_columns_section'))
                    ->description(__('app.export_columns_desc'))
                    ->schema([
                        CheckboxList::make('columns')
                            ->label(__('app.columns'))
                            ->options([
                                'uuid' => 'Ticket ID',
                                'name' => 'Title',
                                'description' => 'Description',
                                'status' => 'Status',
                                'assignee' => 'Assignee',
                                'project' => 'Project',
                                'epic' => 'Epic',
                                'due_date' => 'Due Date',
                                'created_at' => 'Created At',
                                'updated_at' => 'Updated At',
                            ])
                            ->default(['uuid', 'name', 'status', 'assignee', 'due_date', 'created_at'])
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->gridDirection('row')
                    ])
            ])
            ->action(function (array $data, $livewire): void {
                $livewire->exportTickets($data['columns'] ?? []);
            });
    }
}