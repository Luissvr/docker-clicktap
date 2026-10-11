<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')->required()->maxLength(255),
            Forms\Components\TextInput::make('nombre_contacto')->label('Persona de contacto')->maxLength(255),
            Forms\Components\TextInput::make('telefono_contacto')->label('Teléfono')->tel()->maxLength(50),
            Forms\Components\TextInput::make('correo_contacto')->label('Correo')->email()->maxLength(255),
            Forms\Components\Textarea::make('notas_internas')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nombre_contacto')->label('Contacto')->searchable(),
                Tables\Columns\TextColumn::make('telefono_contacto')->label('Teléfono'),
                Tables\Columns\TextColumn::make('locales_count')->counts('locales')->label('Locales'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
