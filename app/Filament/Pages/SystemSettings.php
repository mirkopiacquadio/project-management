<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use App\Models\Setting;
use App\Support\ColorPalette;
use App\Services\SystemResetService;
use Illuminate\Support\Facades\Auth;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;
    protected static string | UnitEnum | null $navigationGroup = 'Settings';
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
        ]);
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
            ])
            ->statePath('data');
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