<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalNegocioResource\Pages;
use App\Models\LocalNegocio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocalNegocioResource extends Resource
{
    protected static ?string $model = LocalNegocio::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'local';

    protected static ?string $pluralModelLabel = 'locales';

    protected static ?string $recordTitleAttribute = 'nombre_interno';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('cliente_id')
                ->label('Cliente')
                ->relationship('cliente', 'nombre')
                ->searchable()
                ->preload()
                ->helperText('Opcional: se puede asignar después de mostrar la página.'),
            Forms\Components\TextInput::make('nombre_interno')->label('Nombre interno')->required()->maxLength(255),
            Forms\Components\TextInput::make('direccion_operativa')->label('Dirección')->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('notas_internas')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_interno')->label('Local')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cliente.nombre')->label('Cliente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('direccion_operativa')->label('Dirección')->limit(40),
                Tables\Columns\IconColumn::make('pagina.id')->label('Página')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocales::route('/'),
            'create' => Pages\CreateLocal::route('/create'),
            'edit' => Pages\EditLocal::route('/{record}/edit'),
        ];
    }
}
