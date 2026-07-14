<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\SystemResetService;
use App\Support\ColorPalette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = null;

    protected string $view = 'filament.pages.system-settings';

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('app.ui_settings');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveBoardStatuses')
                ->label(__('app.save_board_statuses'))
                ->icon('heroicon-o-check')
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('super_admin'))
                ->action(fn () => $this->saveBoardStatuses()),

            Action::make('resetDatabase')
                ->label(__('app.reset_database'))
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                // migrate:fresh issues DDL (DROP TABLE) which auto-commits in MySQL,
                // so it must not run inside the panel's wrapping transaction.
                ->databaseTransaction(false)
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('super_admin'))
                ->requiresConfirmation()
                ->modalHeading(__('app.reset_database_modal_heading'))
                ->modalDescription(__('app.reset_database_modal_desc'))
                ->modalIconColor('danger')
                ->modalSubmitActionLabel(__('app.reset_database_submit'))
                ->schema([
                    TextInput::make('confirmation')
                        ->label(__('app.reset_database_confirm_label'))
                        ->helperText(__('app.reset_database_confirm_help'))
                        ->required()
                        ->rule('in:RESET')
                        ->autocomplete(false),
                ])
                ->action(fn () => $this->resetDatabase()),
        ];
    }

    public function resetDatabase(): void
    {
        // Only a super admin may wipe and restore the database.
        if (! auth()->user()?->hasRole('super_admin')) {
            abort(403);
        }

        // Snapshot the current admin so we are not locked out after the wipe.
        $current = auth()->user();
        $name = $current?->name ?? 'Administrator';
        $email = $current?->email ?? 'admin@admin.it';
        $hashedPassword = $current?->getAuthPassword(); // already hashed

        try {
            // Shared with the `app:reset` console command.
            $service = app(SystemResetService::class);
            $service->restore();

            // Recreate the admin that triggered the reset and keep them signed in.
            if ($hashedPassword) {
                $user = $service->ensureSuperAdmin($name, $email, $hashedPassword, passwordIsHashed: true);
                Auth::login($user);
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('app.reset_database_error_title'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('app.reset_database_success_title'))
            ->body(__('app.reset_database_success_body'))
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    public ?array $data = [];

    public function mount(): void
    {
        $userId = auth()->id();

        $this->form->fill([
            'navigation_style' => Setting::getUserValue('filament_navigation_style', 'sidebar', $userId),
            'panel_color' => Setting::getUserValue('filament_primary_color', 'blue', $userId),
            'allow_multiple_sprints' => Sprint::allowsMultiple(),
            'board_statuses' => $this->boardStatusesFormState(),
        ]);
    }

    /** Map the global board statuses into the repeater's array shape. */
    protected function boardStatusesFormState(): array
    {
        return TicketStatus::globalStatuses()
            ->map(fn (TicketStatus $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'is_completed' => (bool) $status->is_completed,
            ])
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.nav_layout_section'))
                    ->description(__('app.nav_layout_section_desc'))
                    ->icon('heroicon-o-bars-3')
                    ->schema([
                        Radio::make('navigation_style')
                            ->label(__('app.layout_style_label'))
                            ->options([
                                'sidebar' => __('app.nav_sidebar'),
                                'top' => __('app.nav_top'),
                            ])
                            ->descriptions([
                                'sidebar' => __('app.nav_sidebar_desc'),
                                'top' => __('app.nav_top_desc'),
                            ])
                            ->inline(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->updateNavigationStyle($state);
                            }),
                    ]),

                Section::make(__('app.color_theme_section'))
                    ->description(__('app.color_theme_section_desc'))
                    ->icon('heroicon-o-swatch')
                    ->schema([
                        Select::make('panel_color')
                            ->label(__('app.primary_color_label'))
                            ->options(ColorPalette::options())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->updateColorTheme($state);
                            }),
                    ]),

                Section::make(__('app.board_statuses_section'))
                    ->description(__('app.board_statuses_section_desc'))
                    ->icon('heroicon-o-view-columns')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasRole('super_admin'))
                    ->schema([
                        Repeater::make('board_statuses')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')
                                    ->label(__('app.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                ColorPicker::make('color')
                                    ->label(__('app.color'))
                                    ->required(),
                                Toggle::make('is_completed')
                                    ->label(__('app.completed_status'))
                                    ->helperText(__('app.completed_status_help'))
                                    ->inline(false),
                            ])
                            ->columns(4)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->addActionLabel(__('app.add_status'))
                            ->minItems(1),
                    ]),

                Section::make(__('app.sprint_settings_section'))
                    ->description(__('app.sprint_settings_section_desc'))
                    ->icon('heroicon-o-bolt')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasRole('super_admin'))
                    ->schema([
                        Toggle::make('allow_multiple_sprints')
                            ->label(__('app.allow_multiple_sprints'))
                            ->helperText(__('app.allow_multiple_sprints_help'))
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->updateAllowMultipleSprints((bool) $state);
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    protected function updateAllowMultipleSprints(bool $state): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            abort(403);
        }

        Setting::setValue('allow_multiple_sprints', $state ? '1' : '0', 'sprints');

        Notification::make()
            ->title(__('app.settings_saved_title'))
            ->body($state ? __('app.multiple_sprints_enabled') : __('app.multiple_sprints_disabled'))
            ->success()
            ->send();
    }

    public function saveBoardStatuses(): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            abort(403);
        }

        $rows = array_values($this->data['board_statuses'] ?? []);

        if (empty($rows)) {
            Notification::make()
                ->title(__('app.board_statuses_empty'))
                ->warning()
                ->send();

            return;
        }

        // Upsert each row, keeping order from the repeater.
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $attributes = [
                'name' => trim($row['name'] ?? ''),
                'color' => $row['color'] ?? '#6B7280',
                'is_completed' => (bool) ($row['is_completed'] ?? false),
                'sort_order' => $index,
                'project_id' => null,
            ];

            if ($attributes['name'] === '') {
                continue;
            }

            $status = ! empty($row['id'])
                ? TicketStatus::query()->global()->find($row['id'])
                : null;

            if ($status) {
                $status->update($attributes);
            } else {
                $status = TicketStatus::create($attributes);
            }

            $keptIds[] = $status->id;
        }

        // Remove deleted statuses. Move any tickets that pointed at them to the
        // first (default/Backlog) status so the FK cascade does not delete them.
        $fallbackId = $keptIds[0] ?? null;

        $removed = TicketStatus::query()->global()->whereNotIn('id', $keptIds)->get();

        foreach ($removed as $status) {
            if ($fallbackId) {
                Ticket::where('ticket_status_id', $status->id)
                    ->update(['ticket_status_id' => $fallbackId]);
            }

            $status->delete();
        }

        // Refresh the form so new ids/order are reflected.
        $this->data['board_statuses'] = $this->boardStatusesFormState();

        Notification::make()
            ->title(__('app.board_statuses_saved'))
            ->success()
            ->send();
    }

    protected function updateNavigationStyle(string $style): void
    {
        Setting::setUserValue('filament_navigation_style', $style, 'ui', auth()->id());

        $this->dispatch('navigation-style-updated', style: $style);

        Notification::make()
            ->title(__('app.nav_updated_title'))
            ->body($style === 'top'
                ? __('app.nav_updated_top')
                : __('app.nav_updated_sidebar'))
            ->success()
            ->send();
    }

    protected function updateColorTheme(string $color): void
    {
        Setting::setUserValue('filament_primary_color', $color, 'ui', auth()->id());

        $this->applyColorChange($color);

        $this->dispatch('color-theme-updated', color: $color);

        Notification::make()
            ->title(__('app.color_updated_title'))
            ->body(__('app.color_updated_body', ['color' => $color]))
            ->success()
            ->send();
    }

    protected function applyColorChange(string $colorName): void
    {
        FilamentColor::register([
            'primary' => ColorPalette::constantFor($colorName),
        ]);
    }

    public function save(): void
    {
        $this->updateNavigationStyle($this->data['navigation_style']);
        $this->updateColorTheme($this->data['panel_color']);

        Notification::make()
            ->title(__('app.settings_saved_title'))
            ->body(__('app.settings_saved_body'))
            ->success()
            ->send();

        $this->dispatch('settings-saved');
    }
}
