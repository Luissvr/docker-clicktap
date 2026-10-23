<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalNegocioResource\Pages;
use App\Models\Cliente;
use App\Models\LocalNegocio;
use App\Support\CatalogConnection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class LocalNegocioResource extends Resource
{
    protected static ?string $model = LocalNegocio::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Locales de negocio';

    protected static ?int $navigationSort = 3;

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
                ->createOptionForm(fn (Form $form): Form => ClienteResource::form($form))
                ->createOptionUsing(function (array $data): string {
                    Gate::authorize('create', Cliente::class);

                    return CatalogConnection::transaction(fn (): string => Cliente::create($data)->getKey());
                })
                ->createOptionAction(fn (Forms\Components\Actions\Action $action): Forms\Components\Actions\Action => $action
                    ->label('Crear cliente')
                    ->modalHeading('Crear cliente')
                    ->modalSubmitActionLabel('Crear y seleccionar')
                    ->authorize('create', Cliente::class))
                ->helperText('Opcional: selecciona un cliente o pulsa + para escribir uno nuevo sin salir del local.'),
            Forms\Components\TextInput::make('nombre_interno')->label('Nombre interno')->required()->maxLength(255),
            Forms\Components\TextInput::make('direccion_operativa')
                ->label('Dirección')
                ->placeholder('Ej.: Av. Providencia 1234, local 5, Santiago')
                ->helperText('Escribe la dirección manualmente; puedes completarla o corregirla después.')
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notas_internas')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Grid::make(['default' => 1, 'md' => 3])
                    ->extraAttributes(['class' => 'ct-record-grid'])
                    ->schema([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('nombre_interno')->label('Local')->weight('semibold')->searchable()->sortable()->wrap(),
                            Tables\Columns\TextColumn::make('cliente.nombre')->label('Cliente')->prefix('Cliente: ')->default('Sin asignar')->searchable()->sortable()->wrap(),
                        ])->space(1),
                        Tables\Columns\TextColumn::make('direccion_operativa')->label('Dirección')->prefix('Dirección: ')->default('Sin completar')->wrap(),
                        Tables\Columns\TextColumn::make('pagina.id')
                            ->label('Página')
                            ->default(0)
                            ->formatStateUsing(fn (mixed $state): string => $state ? 'Página: Creada' : 'Página: Sin crear')
                            ->wrap(),
                    ]),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('Editar datos')]);
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
