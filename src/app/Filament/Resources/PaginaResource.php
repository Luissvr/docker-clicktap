<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaginaResource\Pages;
use App\Models\Pagina;
use App\Support\LinkTypeHelper;
use App\Support\LogoPalette;
use App\Support\PageDescription;
use App\Support\PublicPagePublisher;
use App\Support\TypographyHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class PaginaResource extends Resource
{
    protected static ?string $model = Pagina::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Páginas';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'páginas';

    protected static ?string $recordTitleAttribute = 'nombre_publico';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(['default' => 1, 'xl' => 12])
                ->schema([
                    Forms\Components\Group::make([
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
                                Forms\Components\Textarea::make('descripcion')
                                    ->label('Descripción')
                                    ->rows(3)
                                    ->helperText('Máximo 150 palabras. Se mostrará con texto justificado.')
                                    ->rules([new PageDescription])
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('direccion_publica')
                                    ->label('Dirección')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('telefono_publico')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('correo_publico')
                                    ->label('Correo')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('logo_path')
                                    ->label('Logo o imagen principal')
                                    ->image()
                                    ->disk(fn (?Pagina $record): string => $record?->logo_disk ?? config('clicktap.media_disk', 'public'))
                                    ->visibility(fn (?Pagina $record): string => ($record?->logo_disk ?? config('clicktap.media_disk', 'public')) === 'public' ? 'public' : 'private')
                                    ->directory('logos')
                                    ->live()
                                    ->helperText('Se conserva la imagen completa por defecto. Define la forma y el tamaño debajo.')
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, mixed $state): void {
                                        if ($get('paleta_personalizada')) {
                                            return;
                                        }

                                        $palette = LogoPalette::from($state);

                                        if ($palette === null) {
                                            return;
                                        }

                                        foreach ($palette as $field => $value) {
                                            $set($field, $value);
                                        }
                                    }),
                                Forms\Components\Toggle::make('paleta_personalizada')
                                    ->label('Conservar los colores al cambiar el logo')
                                    ->helperText('Al activarlo, un nuevo logo no sustituye la paleta elegida.')
                                    ->default(false)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('activo')
                                    ->label('Lista para publicar')
                                    ->default(false),
                            ])->columns(2),

                        Forms\Components\Section::make('Logo')
                            ->description('Ajusta el formato de presentación sin deformar ni cortar tu imagen.')
                            ->schema([
                                Forms\Components\Select::make('logo_layout')
                                    ->label('Forma')
                                    ->options([
                                        'original' => 'Original, imagen completa',
                                        'rounded' => 'Cuadrado con esquinas suaves',
                                        'round' => 'Circular con recorte',
                                    ])
                                    ->default('original')
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('logo_width')
                                    ->label('Tamaño')
                                    ->options([96 => 'Pequeño · 96 px', 128 => 'Mediano · 128 px', 160 => 'Estándar · 160 px', 176 => 'Grande · 176 px', 240 => 'Extra grande · 240 px'])
                                    ->default(160)
                                    ->required()
                                    ->live(),
                            ])->columns(2),

                        Forms\Components\Section::make('Apariencia')
                            ->headerActions([
                                Forms\Components\Actions\Action::make('restaurarColoresRecomendados')
                                    ->label('Restaurar colores recomendados')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Forms\Get $get, Forms\Set $set, ?Pagina $record): void {
                                        $logoState = $get('logo_path');
                                        $logo = is_array($logoState) ? reset($logoState) : $logoState;
                                        $palette = LogoPalette::from($logo);

                                        if ($palette === null && is_string($logo)) {
                                            try {
                                                $contents = Storage::disk($record?->logo_disk ?? config('clicktap.media_disk', 'public'))->get($logo);
                                                $palette = is_string($contents) ? LogoPalette::fromContents($contents) : null;
                                            } catch (Throwable $exception) {
                                                report($exception);
                                            }
                                        }

                                        if ($palette === null) {
                                            Notification::make()
                                                ->title('No se pudo recuperar la recomendación')
                                                ->body('Carga un logo válido para generar los colores recomendados.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        foreach ($palette as $field => $value) {
                                            $set($field, $value);
                                        }

                                        Notification::make()
                                            ->title('Colores recomendados restaurados')
                                            ->success()
                                            ->send();
                                    }),
                            ])
                            ->schema([
                                Forms\Components\Select::make('tema')
                                    ->label('Tema visual')
                                    ->options([
                                        'default' => 'Predeterminado',
                                        'barber' => 'Barbería',
                                        'floral' => 'Floral',
                                        'china' => 'La Dinastía China',
                                        'dona-flor' => 'Doña Flor',
                                        'fulldrink' => 'Full Drink',
                                        'marley' => 'El Bajón del Marley',
                                        'recargas-ac' => 'Recargas A/C',
                                    ])
                                    ->default('default')
                                    ->required(),
                                Forms\Components\Select::make('tipografia')
                                    ->label('Tipografía')
                                    ->options(TypographyHelper::options())
                                    ->default('inter')
                                    ->required(),
                                Forms\Components\ColorPicker::make('color_fondo')
                                    ->label('Fondo')
                                    ->default('#ffffff')
                                    ->helperText('El texto se ajusta automáticamente para mantener contraste.'),
                                Forms\Components\ColorPicker::make('color_texto')
                                    ->label('Texto')
                                    ->default('#000000')
                                    ->helperText('Se recomienda un contraste mínimo de 4,5:1.'),
                                Forms\Components\ColorPicker::make('color_boton')
                                    ->label('Botones')
                                    ->default('#111827')
                                    ->helperText('Los enlaces se muestran con este color de fondo.'),
                                Forms\Components\ColorPicker::make('color_texto_boton')
                                    ->label('Texto de botones')
                                    ->default('#ffffff')
                                    ->helperText('Se corrige automáticamente si no contrasta con el botón.'),
                            ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),

                        Forms\Components\Section::make('Enlaces')
                            ->key('data.pagina-enlaces-section')
                            ->headerActions([
                                Forms\Components\Actions\Action::make('ordenarEnlaces')
                                    ->label(fn (Component $livewire): string => $livewire->isOrderingLinks ? 'Terminar orden' : 'Ordenar')
                                    ->icon('heroicon-o-arrows-up-down')
                                    ->color(fn (Component $livewire): string => $livewire->isOrderingLinks ? 'primary' : 'gray')
                                    ->extraAttributes([
                                        'class' => 'ct-order-links-action',
                                        'x-on:click' => 'selectedLink = null; $dispatch(`repeater-collapse`, `data.enlaces`)',
                                    ])
                                    ->disabled(fn (Component $livewire): bool => ! $livewire->isOrderingLinks && count($livewire->linkOrderOptions()) < 2)
                                    ->action(fn (Component $livewire) => $livewire->toggleLinkOrdering()),
                                Forms\Components\Actions\Action::make('subirEnlace')
                                    ->label('Subir enlace seleccionado')
                                    ->icon('heroicon-o-arrow-up')
                                    ->iconButton()
                                    ->color('gray')
                                    ->disabled(fn (Component $livewire): bool => ! $livewire->isOrderingLinks)
                                    ->livewireClickHandlerEnabled(false)
                                    ->extraAttributes([
                                        'x-bind:disabled' => 'selectedLink === null',
                                        'x-on:click' => 'moveLink(-1)',
                                    ]),
                                Forms\Components\Actions\Action::make('bajarEnlace')
                                    ->label('Bajar enlace seleccionado')
                                    ->icon('heroicon-o-arrow-down')
                                    ->iconButton()
                                    ->color('gray')
                                    ->disabled(fn (Component $livewire): bool => ! $livewire->isOrderingLinks)
                                    ->livewireClickHandlerEnabled(false)
                                    ->extraAttributes([
                                        'x-bind:disabled' => 'selectedLink === null',
                                        'x-on:click' => 'moveLink(1)',
                                    ]),
                                Forms\Components\Actions\Action::make('eliminarEnlaces')
                                    ->label('Eliminar')
                                    ->icon('heroicon-o-trash')
                                    ->color('danger')
                                    ->disabled(fn (Component $livewire): bool => $livewire->linkOrderOptions() === [])
                                    ->modalHeading('Eliminar enlaces')
                                    ->modalDescription('Selecciona uno o varios enlaces para eliminarlos de esta página.')
                                    ->modalSubmitActionLabel('Eliminar seleccionados')
                                    ->form([
                                        Forms\Components\CheckboxList::make('enlaces')
                                            ->label('Enlaces')
                                            ->options(fn (Component $livewire): array => $livewire->linkOrderOptions())
                                            ->required()
                                            ->bulkToggleable(),
                                    ])
                                    ->action(fn (array $data, Component $livewire) => $livewire->deleteSelectedLinks($data['enlaces'])),
                            ])
                            ->schema([
                                Forms\Components\Repeater::make('enlaces')
                                    ->label('')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Forms\Components\Select::make('tipo')
                                            ->label('Tipo de enlace')
                                            ->options(LinkTypeHelper::options())
                                            ->prefixIcon(fn (Forms\Get $get): string => LinkTypeHelper::heroicon($get('tipo')))
                                            ->required()
                                            ->live()
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\TextInput::make('nombre')
                                            ->label('Nombre del enlace')
                                            ->placeholder('Ej. WhatsApp Ventas, Instagram...')
                                            ->helperText('Identificador para la lista interna.')
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\TextInput::make('etiqueta')
                                            ->label('Texto del botón')
                                            ->placeholder('Ej. Escríbenos, Síguenos...')
                                            ->helperText('Visible en el botón público (opcional).')
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\TextInput::make('url')
                                            ->label('Destino (URL)')
                                            ->placeholder('https://...')
                                            ->url()
                                            ->maxLength(2048)
                                            ->helperText('Opcional si es botón de solo copiar.')
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\Toggle::make('copiar_texto')
                                            ->label('Copiar texto al pulsar')
                                            ->live(),
                                        Forms\Components\Toggle::make('activo')
                                            ->label('Activo')
                                            ->default(true),
                                        Forms\Components\Textarea::make('texto_copia')
                                            ->label('Texto a copiar')
                                            ->placeholder('Escribe los datos que se copiarán en el portapapeles')
                                            ->helperText('Puedes completarlo más adelante.')
                                            ->maxLength(5000)
                                            ->rows(3)
                                            ->visible(fn (Forms\Get $get): bool => (bool) $get('copiar_texto'))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                                    ->orderColumn('posicion')
                                    ->reorderableWithDragAndDrop(false)
                                    ->extraAttributes(fn (Component $livewire): array => [
                                        'class' => $livewire->isOrderingLinks ? 'ct-link-ordering' : '',
                                    ])
                                    ->deletable(false)
                                    ->collapsed()
                                    ->collapsible(fn (Component $livewire): bool => ! $livewire->isOrderingLinks)
                                    ->collapseAllAction(fn (Forms\Components\Actions\Action $action): Forms\Components\Actions\Action => $action->hidden())
                                    ->expandAllAction(fn (Forms\Components\Actions\Action $action): Forms\Components\Actions\Action => $action->hidden())
                                    ->extraItemActions([
                                        Forms\Components\Actions\Action::make('seleccionarEnlaceOrden')
                                            ->label('Seleccionar enlace para ordenar')
                                            ->icon('heroicon-s-check')
                                            ->color('gray')
                                            ->extraAttributes(function (array $arguments): array {
                                                $key = 'atob(`'.base64_encode((string) ($arguments['item'] ?? '')).'`)';

                                                return [
                                                    'class' => 'ct-link-order-checkbox',
                                                    'role' => 'checkbox',
                                                    'x-on:click' => "selectedLink = selectedLink === {$key} ? null : {$key}",
                                                    'x-bind:aria-checked' => "selectedLink === {$key} ? `true` : `false`",
                                                    'x-bind:class' => "{ [`ct-link-order-checkbox--selected`]: selectedLink === {$key} }",
                                                    'x-bind:aria-label' => "selectedLink === {$key} ? `Deseleccionar enlace` : `Seleccionar enlace para ordenar`",
                                                ];
                                            })
                                            ->visible(fn (Component $livewire): bool => $livewire->isOrderingLinks)
                                            ->livewireClickHandlerEnabled(false),
                                    ])
                                    ->itemLabel(fn (array $state): HtmlString => LinkTypeHelper::itemLabel($state))
                                    ->addActionLabel('Agregar enlace')
                                    ->columnSpanFull(),
                            ]),
                    ])
                        ->extraAttributes([
                            'x-data' => <<<'JS'
                                {
                                    selectedLink: null,
                                    async moveLink(direction) {
                                        if (this.selectedLink === null) return;

                                        await this.$wire.moveSelectedLink(this.selectedLink, direction);
                                        this.$dispatch(`repeater-collapse`, `data.enlaces`);
                                    },
                                }
                                JS,
                        ])
                        ->columnSpan(['default' => 12, 'xl' => 7]),

                    Forms\Components\Group::make([
                        Forms\Components\Section::make('Vista previa')
                            ->description('Se actualiza al cambiar el contenido y la apariencia.')
                            ->key('data.vista-previa-section')
                            ->headerActions([
                                Forms\Components\Actions\Action::make('cargarPersonalizacion')
                                    ->label('Actualizar vista previa')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('primary')
                                    ->action(fn (Component $livewire) => $livewire->loadPagePreview()),
                            ])
                            ->schema([
                                Forms\Components\View::make('filament.components.pagina-live-preview'),
                            ])
                            ->collapsible(),
                    ])->columnSpan(['default' => 12, 'xl' => 5]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Buscar páginas, locales o rutas…')
            ->defaultSort('updated_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(50)
            ->contentGrid(['default' => 1, 'md' => 2, 'xl' => 3])
            ->columns([
                Tables\Columns\Layout\Grid::make(['default' => 1])
                    ->extraAttributes(['class' => 'ct-record-grid ct-page-card'])
                    ->schema([
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\TextColumn::make('nombre_publico')
                                ->label('Página')->weight('semibold')->searchable()->sortable()->wrap(),
                            Tables\Columns\TextColumn::make('publicacion_estado')
                                ->label('Publicación')
                                ->default('sin_publicar')
                                ->badge()
                                ->wrap()
                                ->grow(false)
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
                        ])->extraAttributes(['class' => 'ct-page-card-heading']),
                        Tables\Columns\TextColumn::make('local.nombre_interno')->label('Local')->prefix('Local: ')->default('Sin asignar')->searchable()->wrap(),
                        Tables\Columns\TextColumn::make('slug')->label('Ruta')->prefix('Ruta: /')->copyable()->searchable()->wrap(),
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\TextColumn::make('enlaces_count')->counts('enlaces')->label('Enlaces')->prefix('Enlaces: ')->icon('heroicon-o-link')->grow(false)->wrap(),
                            Tables\Columns\TextColumn::make('updated_at')->label('Última edición')->prefix('Última edición: ')->icon('heroicon-o-clock')->since()->sortable()->grow(false)->wrap(),
                        ])->extraAttributes(['class' => 'ct-page-card-meta']),
                        Tables\Columns\TextColumn::make('activo')
                            ->label('Lista para publicar')
                            ->formatStateUsing(fn (): string => 'No está lista para publicar')
                            ->visible(fn (?Pagina $record): bool => $record === null || ! $record->activo)
                            ->color('gray')
                            ->wrap(),
                    ]),
            ])
            ->actionsAlignment('between')
            ->actions([
                Tables\Actions\ActionGroup::make([
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
                ])->label('Acciones')->icon('heroicon-m-ellipsis-vertical')->button(),
                Tables\Actions\Action::make('public_page')
                    ->label('Ver página')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Pagina $record): string => 'https://clicktap.app/'.rawurlencode($record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Pagina $record): bool => in_array($record->publicacion_estado, ['enviada', 'legado'], true)),
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
