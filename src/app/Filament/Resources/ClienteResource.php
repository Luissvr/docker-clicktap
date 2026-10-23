<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

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
            ->searchPlaceholder('Buscar clientes…')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nombre_contacto')->label('Contacto')->placeholder('Sin contacto')->searchable(),
                Tables\Columns\TextColumn::make('telefono_contacto')->label('Teléfono')->placeholder('Sin teléfono')->searchable(),
                Tables\Columns\TextColumn::make('locales_count')->counts('locales')->label('Locales'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('contacto_completo')
                    ->native(false)
                    ->label('Datos de contacto')
                    ->placeholder('Todos los clientes')
                    ->trueLabel('Con contacto y teléfono')
                    ->falseLabel('Contacto o teléfono pendiente')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('nombre_contacto')->where('nombre_contacto', '!=', '')
                            ->whereNotNull('telefono_contacto')->where('telefono_contacto', '!=', ''),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->whereNull('nombre_contacto')->orWhere('nombre_contacto', '')
                            ->orWhereNull('telefono_contacto')->orWhere('telefono_contacto', '')),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('con_locales')
                    ->native(false)
                    ->label('Locales asociados')
                    ->placeholder('Todos los clientes')
                    ->trueLabel('Con locales')
                    ->falseLabel('Sin locales')
                    ->queries(
                        true: fn (Builder $query) => $query->has('locales'),
                        false: fn (Builder $query) => $query->doesntHave('locales'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->filtersFormWidth('sm')
            ->filtersTriggerAction(fn (Tables\Actions\Action $action) => $action->button()->label('Filtros')->color('gray'))
            ->actions([
                Tables\Actions\EditAction::make()->label(fn (Cliente $record): string => blank($record->nombre_contacto) || blank($record->telefono_contacto) ? 'Añadir contacto' : 'Editar'
                ),
            ]);
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
