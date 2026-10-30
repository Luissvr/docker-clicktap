<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaginaResource\Pages;
use App\Models\Pagina;
use App\Support\LinkTypeHelper;
use App\Support\LogoCache;
use App\Support\LogoPalette;
use App\Support\PageAppearance;
use App\Support\PageDescription;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use App\Support\TransferBank;
use App\Support\TypographyHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return app(PageTrash::class)->available() ? $query->whereNull('papelera_en') : $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(['default' => 1, 'xl' => 12])
                ->extraAttributes(['class' => 'ct-page-editor-grid'])
                ->schema([
                    Forms\Components\Group::make([
                        Forms\Components\View::make('filament.components.editor-navigation'),
                        Forms\Components\Section::make('Página pública')
                            ->extraAttributes(['x-show' => "!desktopEditor || editorTab === 'datos' || editorTab === 'todo'"])
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
                                Forms\Components\Textarea::make('direccion_publica')
                                    ->label('Dirección')
                                    ->rows(2)
                                    ->maxLength(255)
                                    ->helperText('Puedes usar saltos de línea; se conservarán en la página publicada.'),
                                Forms\Components\TextInput::make('telefono_publico')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('correo_publico')
                                    ->label('Correo')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('contenido_extra.tagline')
                                    ->label('Frase bajo el nombre')
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->helperText('Aparece debajo del título en la página pública.'),
                                Forms\Components\Textarea::make('contenido_extra.schedule')
                                    ->label('Horario')
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->helperText('Se muestra debajo de ubicación y contacto.'),
                                Forms\Components\Select::make('contenido_extra.transfer_logo')
                                    ->label('Logo en botones de transferencia')
                                    ->options(TransferBank::options())
                                    ->default('none')
                                    ->helperText('Se utiliza en todos los enlaces configurados para copiar datos de transferencia.'),
                                // Async Alpine removes x-ignore after loading. Keep it in the server HTML
                                // (also on color pickers) so Livewire cannot initialize unloaded clones.
                                Forms\Components\FileUpload::make('logo_path')
                                    ->extraAlpineAttributes(['x-ignore' => ''])
                                    ->label('Logo o imagen principal')
                                    ->image()
                                    ->fetchFileInformation(false)
                                    ->getUploadedFileUsing(static function (Forms\Components\BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                                        $record = $component->getRecord();
                                        if ($record instanceof Pagina && $record->logo_path === $file && app(LogoCache::class)->isKnownMissing($record)) {
                                            return null;
                                        }
                                        $cachedUrl = $record instanceof Pagina && $record->logo_disk === 'supabase' && $record->logo_path === $file
                                            ? app(LogoCache::class)->url($record)
                                            : null;
                                        if ($cachedUrl !== null) {
                                            $url = $cachedUrl;
                                        } else {
                                            try {
                                                $url = $component->getVisibility() === 'private'
                                                    ? $component->getDisk()->temporaryUrl($file, now()->addMinutes(5))
                                                    : $component->getDisk()->url($file);
                                            } catch (Throwable) {
                                                return null;
                                            }
                                        }

                                        return $url === null ? null : [
                                            'name' => is_string($storedFileNames) ? $storedFileNames : basename($file),
                                            'size' => 0,
                                            'type' => null,
                                            'url' => $url,
                                        ];
                                    })
                                    ->disk(fn (?Pagina $record): string => $record?->logo_disk ?? config('clicktap.media_disk', 'public'))
                                    ->visibility(fn (?Pagina $record): string => ($record?->logo_disk ?? config('clicktap.media_disk', 'public')) === 'public' ? 'public' : 'private')
                                    ->directory('logos')
                                    ->live()
                                    ->helperText(fn (?Pagina $record): string => $record && app(LogoCache::class)->isKnownMissing($record)
                                        ? 'El archivo de este logo no está en Supabase. Súbelo de nuevo para recuperarlo.'
                                        : ($record && app(LogoCache::class)->isRecovered($record)
                                            ? 'Se muestra una copia de la publicación. El original falta en Supabase; súbelo de nuevo si necesitas reemplazarlo.'
                                            : 'Se conserva la imagen completa por defecto. Define la forma y el tamaño debajo.'))
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
                            ->extraAttributes(['x-show' => "!desktopEditor || editorTab === 'datos' || editorTab === 'todo'"])
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
                            ->extraAttributes(['x-show' => "!desktopEditor || editorTab === 'apariencia' || editorTab === 'todo'"])
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
                                        'comercio' => 'Comercio',
                                        'default' => 'Boceto predeterminado',
                                    ])
                                    ->default('comercio')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Component $livewire) => $livewire->loadPagePreview()),
                                Forms\Components\Select::make('tipografia')
                                    ->label('Tipografía')
                                    ->options(TypographyHelper::options())
                                    ->default('inter')
                                    ->required(),
                                Forms\Components\ColorPicker::make('color_fondo')
                                    ->extraAlpineAttributes(['x-ignore' => ''])
                                    ->label('Fondo')
                                    ->default('#ffffff')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state): void {
                                        if (! is_string($state) || preg_match('/^#[0-9a-fA-F]{6}$/', $state) !== 1) {
                                            return;
                                        }

                                        $palette = PageAppearance::resolve([
                                            'color_fondo' => $state,
                                            'color_texto' => $get('color_texto'),
                                            'color_boton' => $get('color_boton'),
                                            'color_texto_boton' => $get('color_texto_boton'),
                                        ]);

                                        foreach (['color_texto', 'color_boton', 'color_texto_boton'] as $field) {
                                            $set($field, $palette[$field]);
                                        }
                                    })
                                    ->helperText('Al cambiar el fondo, los textos y botones se ajustan para mantener contraste.'),
                                Forms\Components\ColorPicker::make('color_texto')
                                    ->extraAlpineAttributes(['x-ignore' => ''])
                                    ->label('Texto')
                                    ->default('#000000')
                                    ->live(debounce: 500)
                                    ->helperText('Se recomienda un contraste mínimo de 4,5:1.'),
                                Forms\Components\ColorPicker::make('color_boton')
                                    ->extraAlpineAttributes(['x-ignore' => ''])
                                    ->label('Botones')
                                    ->default('#111827')
                                    ->live(debounce: 500)
                                    ->helperText('Los enlaces se muestran con este color de fondo.'),
                                Forms\Components\ColorPicker::make('color_texto_boton')
                                    ->extraAlpineAttributes(['x-ignore' => ''])
                                    ->label('Texto de botones')
                                    ->default('#ffffff')
                                    ->live(debounce: 500)
                                    ->helperText('Se corrige automáticamente si no contrasta con el botón.'),
                            ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),

                        Forms\Components\Section::make('Enlaces')
                            ->extraAttributes(['x-show' => "!desktopEditor || editorTab === 'enlaces' || editorTab === 'todo'"])
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
                                            ->prefix(fn (Forms\Get $get): HtmlString => new HtmlString(LinkTypeHelper::iconSvg($get('tipo'), 'h-5 w-5')))
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Forms\Get $get, Forms\Set $set): void {
                                                if ($state === 'review') {
                                                    $set('place_id', LinkTypeHelper::reviewPlaceIdFromUrl($get('url')));
                                                    $set('url', LinkTypeHelper::reviewUrlFromPlaceId($get('place_id')));
                                                    $set('copiar_texto', false);
                                                }

                                                if ($state === 'transferencia') {
                                                    $set('copiar_texto', true);
                                                    $set('url', null);
                                                    if (blank($get('etiqueta'))) {
                                                        $set('etiqueta', 'Copiar datos de transferencia');
                                                    }
                                                }
                                            })
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\TextInput::make('nombre')
                                            ->label('Nombre del enlace')
                                            ->placeholder('Ej. WhatsApp Ventas, Instagram...')
                                            ->helperText('Identificador para la lista interna.')
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\Select::make('banco_transferencia')
                                            ->label('Banco del botón')
                                            ->options(TransferBank::options())
                                            ->default('none')
                                            ->helperText('El distintivo se mostrará dentro de este botón de transferencia.')
                                            ->visible(fn (Forms\Get $get): bool => $get('tipo') === 'transferencia')
                                            ->dehydratedWhenHidden(),
                                        Forms\Components\TextInput::make('etiqueta')
                                            ->label(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'instagram' => 'Texto para Instagram',
                                                'facebook' => 'Texto para Facebook',
                                                'whatsapp' => 'Texto para WhatsApp',
                                                'review' => 'Texto para reseñas',
                                                'location' => 'Texto para ubicación',
                                                'transferencia' => 'Texto del botón',
                                                default => 'Texto del botón',
                                            })
                                            ->placeholder(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'instagram' => 'Ej. Síguenos en Instagram',
                                                'facebook' => 'Ej. Visita nuestro Facebook',
                                                'whatsapp' => 'Ej. Escríbenos por WhatsApp',
                                                'review' => 'Ej. Déjanos una reseña',
                                                'location' => 'Ej. Cómo llegar',
                                                'transferencia' => 'Ej. Copiar datos de transferencia',
                                                default => 'Ej. Visita nuestra página',
                                            })
                                            ->helperText(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'review' => 'El destino se genera con el Place ID de este enlace.',
                                                'transferencia' => 'Este texto aparecerá en el botón que copia los datos.',
                                                default => 'Texto visible en el botón público. Si lo dejas vacío, se usa el nombre del tipo.',
                                            })
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\TextInput::make('place_id')
                                            ->label('Place ID de Google')
                                            ->placeholder('Ej. ChIJ...')
                                            ->helperText('Ingresa solo el Place ID del negocio; el enlace de reseña se genera automáticamente.')
                                            ->maxLength(255)
                                            ->regex('/^[A-Za-z0-9_-]+$/')
                                            ->dehydrated(false)
                                            ->live(debounce: 500)
                                            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set): void {
                                                $set('place_id', LinkTypeHelper::reviewPlaceIdFromUrl($get('url')));
                                            })
                                            ->afterStateUpdated(fn (?string $state, Forms\Set $set) => $set('url', LinkTypeHelper::reviewUrlFromPlaceId($state)))
                                            ->visible(fn (Forms\Get $get): bool => $get('tipo') === 'review'),
                                        Forms\Components\TextInput::make('url')
                                            ->label(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'instagram' => 'Perfil de Instagram (URL)',
                                                'facebook' => 'Página de Facebook (URL)',
                                                'whatsapp' => 'Enlace de WhatsApp (URL)',
                                                'location' => 'Ubicación (URL de Maps)',
                                                default => 'Destino (URL)',
                                            })
                                            ->placeholder(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'instagram' => 'https://instagram.com/tu-negocio',
                                                'facebook' => 'https://facebook.com/tu-negocio',
                                                'whatsapp' => 'https://wa.me/56912345678',
                                                'location' => 'https://maps.google.com/?q=...',
                                                default => 'https://...',
                                            })
                                            ->url()
                                            ->maxLength(2048)
                                            ->helperText(fn (Forms\Get $get): string => match ($get('tipo')) {
                                                'whatsapp' => 'Incluye el código de país en el número de wa.me.',
                                                'location' => 'Pega un enlace compartido de Google Maps.',
                                                default => (bool) $get('copiar_texto') ? 'Opcional si este botón solo copia texto.' : 'Pega el enlace completo, incluyendo https://.',
                                            })
                                            ->dehydrateStateUsing(fn (?string $state, Forms\Get $get): ?string => match ($get('tipo')) {
                                                'review' => LinkTypeHelper::reviewUrlFromPlaceId($get('place_id')),
                                                'transferencia' => null,
                                                default => $state,
                                            })
                                            ->dehydratedWhenHidden()
                                            ->visible(fn (Forms\Get $get): bool => ! in_array($get('tipo'), ['review', 'transferencia'], true))
                                            ->columnSpan(['default' => 1, 'md' => 1]),
                                        Forms\Components\Toggle::make('copiar_texto')
                                            ->label('Copiar texto al pulsar')
                                            ->live()
                                            ->dehydrateStateUsing(fn (mixed $state, Forms\Get $get): bool => match ($get('tipo')) {
                                                'transferencia' => true,
                                                'review' => false,
                                                default => (bool) $state,
                                            })
                                            ->dehydratedWhenHidden()
                                            ->visible(fn (Forms\Get $get): bool => ! in_array($get('tipo'), ['review', 'transferencia'], true)),
                                        Forms\Components\Toggle::make('activo')
                                            ->label('Activo')
                                            ->default(true),
                                        Forms\Components\Textarea::make('texto_copia')
                                            ->label(fn (Forms\Get $get): string => $get('tipo') === 'transferencia' ? 'Datos de transferencia' : 'Texto a copiar')
                                            ->placeholder(fn (Forms\Get $get): string => $get('tipo') === 'transferencia'
                                                ? "Nombre del titular\nRUT\nBanco y tipo de cuenta\nNúmero de cuenta\nCorreo"
                                                : 'Escribe el texto que se copiará al pulsar el botón')
                                            ->helperText(fn (Forms\Get $get): string => $get('tipo') === 'transferencia'
                                                ? 'Estos datos se copiarán desde el botón público de transferencia.'
                                                : 'El texto se copiará al pulsar el botón.')
                                            ->maxLength(5000)
                                            ->rows(3)
                                            ->live(debounce: 500)
                                            ->visible(fn (Forms\Get $get): bool => $get('tipo') === 'transferencia' || ($get('tipo') !== 'review' && (bool) $get('copiar_texto')))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
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
                            'class' => 'ct-editor-fields',
                            'x-on:resize.window.debounce.150ms' => "desktopEditor = window.matchMedia('(min-width: 1024px)').matches",
                            'x-on:form-validation-error.window' => "editorTab = 'todo'",
                            'x-data' => <<<'JS'
                                {
                                    selectedLink: null,
                                    editorTab: 'datos',
                                    desktopEditor: window.matchMedia('(min-width: 1024px)').matches,
                                    async moveLink(direction) {
                                        if (this.selectedLink === null) return;

                                        await this.$wire.moveSelectedLink(this.selectedLink, direction);
                                        this.$dispatch(`repeater-collapse`, `data.enlaces`);
                                    },
                                }
                                JS,
                        ])
                        ->live(onBlur: true)
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
                    ])->extraAttributes(['class' => 'ct-editor-preview'])->columnSpan(['default' => 12, 'xl' => 5]),
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
                Tables\Actions\EditAction::make()->label('Editar')->button(),
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
                    ->modalDescription('Se enviará únicamente esta página. Las demás se conservarán como están en producción.')
                    ->visible(fn (Pagina $record): bool => $record->activo && (! is_array(config('clicktap.publication_slugs')) || in_array($record->slug, config('clicktap.publication_slugs'), true) || app(PageTrash::class)->isUnpublishedDraft($record)))
                    ->action(function (Pagina $record): void {
                        try {
                            app(PublicationQueue::class)->request($record);

                            Notification::make()
                                ->title('Publicación en cola')
                                ->body('Puedes seguir trabajando. Consulta el avance en Publicaciones.')
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
                Tables\Actions\Action::make('public_page')
                    ->label('Ver página')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Pagina $record): string => rtrim((string) config('services.cloudflare.public_url', 'https://clicktap.app'), '/').'/'.rawurlencode($record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Pagina $record): bool => $record->activo && in_array($record->publicacion_estado, ['enviada', 'legado', 'publicada'], true)),
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
