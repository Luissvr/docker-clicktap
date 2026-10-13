<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaginaResource\Pages;
use App\Models\Pagina;
use App\Support\LogoPalette;
use App\Support\PublicPagePublisher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

class PaginaResource extends Resource
{
    protected static ?string $model = Pagina::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'páginas';

    protected static ?string $recordTitleAttribute = 'nombre_publico';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Página pública')
                ->schema([
                    Forms\Components\TextInput::make('nombre_publico')
                        ->label('Nombre del local')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, string $operation): void {
                            if ($operation === 'create') {
                                $set('slug', Str::slug($state ?? ''));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Ruta pública')
                        ->prefix('clicktap.app/')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                    Forms\Components\Textarea::make('descripcion')->label('Descripción')->rows(3)->maxLength(1000)->columnSpanFull(),
                    Forms\Components\TextInput::make('direccion_publica')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('telefono_publico')->label('Teléfono')->tel()->maxLength(50),
                    Forms\Components\TextInput::make('correo_publico')->label('Correo')->email()->maxLength(255),
                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Logo o imagen principal')
                        ->image()
                        ->disk(fn (?Pagina $record): string => $record?->logo_disk ?? config('clicktap.media_disk', 'public'))
                        ->visibility(fn (?Pagina $record): string => ($record?->logo_disk ?? config('clicktap.media_disk', 'public')) === 'public' ? 'public' : 'private')
                        ->directory('logos')
                        ->imageEditor()
                        ->live()
                        ->helperText('Al cargar un logo se sugerirá una paleta; puedes ajustarla después.')
                        ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                            $palette = LogoPalette::from($state);

                            if ($palette === null) {
                                return;
                            }

                            foreach ($palette as $field => $value) {
                                $set($field, $value);
                            }
                        }),
                    Forms\Components\Toggle::make('activo')->label('Lista para publicar')->default(false),
                ])->columns(2),
            Forms\Components\Section::make('Apariencia')
                ->schema([
                    Forms\Components\Select::make('tema')
                        ->options(['default' => 'Predeterminado', 'barber' => 'Barbería', 'floral' => 'Floral'])
                        ->default('default')
                        ->required(),
                    Forms\Components\ColorPicker::make('color_fondo')->label('Fondo')->default('#ffffff'),
                    Forms\Components\ColorPicker::make('color_texto')->label('Texto')->default('#000000'),
                    Forms\Components\ColorPicker::make('color_boton')->label('Botones')->default('#111827'),
                    Forms\Components\ColorPicker::make('color_texto_boton')->label('Texto de botones')->default('#ffffff'),
                ])->columns(5),
            Forms\Components\Section::make('Enlaces')
                ->schema([
                    Forms\Components\Repeater::make('enlaces')
                        ->relationship()
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Select::make('tipo')
                                ->options([
                                    'web' => 'Página web',
                                    'whatsapp' => 'WhatsApp',
                                    'instagram' => 'Instagram',
                                    'facebook' => 'Facebook',
                                    'review' => 'Reseñas',
                                    'location' => 'Ubicación',
                                    'transferencia' => 'Datos de transferencia',
                                    'otro' => 'Otro',
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('etiqueta')
                                ->label('Texto del botón')
                                ->helperText('Opcional mientras completas la página.')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('url')
                                ->label('Destino')
                                ->helperText('Opcional mientras completas la información.')
                                ->url()
                                ->maxLength(2048),
                            Forms\Components\Toggle::make('copiar_texto')
                                ->label('Copiar texto al pulsar')
                                ->live(),
                            Forms\Components\Textarea::make('texto_copia')
                                ->label('Texto a copiar')
                                ->placeholder('Escribe el texto que se copiará')
                                ->helperText('Puedes completarlo más adelante.')
                                ->maxLength(5000)
                                ->rows(3)
                                ->visible(fn (Forms\Get $get): bool => (bool) $get('copiar_texto'))
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('activo')->default(true),
                        ])
                        ->columns(4)
                        ->orderColumn('posicion')
                        ->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => $state['etiqueta'] ?? null)
                        ->collapsible()
                        ->addActionLabel('Agregar enlace')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_publico')->label('Página')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('local.nombre_interno')->label('Local')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Ruta')->copyable(),
                Tables\Columns\TextColumn::make('enlaces_count')->counts('enlaces')->label('Enlaces'),
                Tables\Columns\IconColumn::make('activo')->boolean(),
                Tables\Columns\TextColumn::make('publicacion_estado')
                    ->label('Publicación')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'enviada' => 'Enviada',
                        'publicando' => 'Enviando',
                        'fallida' => 'Fallida',
                        'legado' => 'Activa en el sitio actual',
                        default => 'Sin publicar',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'enviada' => 'success',
                        'publicando' => 'warning',
                        'fallida' => 'danger',
                        'legado' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')->label('Última edición')->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Vista previa')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Pagina $record): string => route('paginas.preview', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('mobile_preview')
                    ->label('Vista móvil')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->url(fn (Pagina $record): string => route('paginas.preview', ['pagina' => $record, 'device' => 'mobile']))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publicar página')
                    ->modalDescription('Se enviará el contenido activo de todas las páginas al repositorio conectado.')
                    ->visible(fn (Pagina $record): bool => $record->activo)
                    ->action(function (Pagina $record): void {
                        try {
                            $commit = app(PublicPagePublisher::class)->publish($record);

                            Notification::make()
                                ->title('Publicación enviada')
                                ->body("Commit {$commit} enviado al repositorio.")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('No se pudo publicar')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaginas::route('/'),
            'create' => Pages\CreatePagina::route('/create'),
            'edit' => Pages\EditPagina::route('/{record}/edit'),
        ];
    }
}
