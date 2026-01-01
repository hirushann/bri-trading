<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'My Dashboard';

    protected static ?string $title = 'My Dashboard';

    protected static string $routePath = '/';

    protected static string $view = 'filament.pages.dashboard';
}
