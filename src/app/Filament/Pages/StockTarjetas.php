<?php

namespace App\Filament\Pages;

use App\Support\BusinessLedger;
use App\Support\InventoryReadCache;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class StockTarjetas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Stock de tarjetas';

    protected static ?string $title = 'Stock de tarjetas';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.stock-tarjetas';

    protected ?string $maxContentWidth = 'full';

    #[Url(as: 'vista')]
    public string $activeTab = 'inventario';

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $estado = 'todos';

    #[Url]
    public string $estadoPago = 'todos';

    #[Url]
    public string $historialTipo = 'todos';

    #[Url]
    public ?string $historialDesde = null;

    #[Url(as: 'pagina')]
    public int $stockPage = 1;

    public bool $showPurchases = false;

    public string $purchaseSearch = '';

    public int $purchasePage = 1;

    public function callMountedAction(array $arguments = []): mixed
    {
        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $exception) {
            $prefix = 'mountedActionsData.'.max(0, count($this->mountedActions) - 1).'.';
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'mountedActionsData.') ? $field : $prefix.$field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        } catch (\PDOException $exception) {
            report($exception);
            Notification::make()->danger()->persistent()->title('No se pudo confirmar la operación')
                ->body('Los datos siguen en el formulario. Comprueba el historial antes de reintentar para evitar duplicados.')->send();

            return null;
        }
    }

    public function mount(): void
    {
        $saved = session('stock-navigation', []);
        foreach (['activeTab' => 'vista', 'buscar' => 'buscar', 'estado' => 'estado', 'estadoPago' => 'estadoPago', 'historialTipo' => 'historialTipo', 'historialDesde' => 'historialDesde', 'stockPage' => 'pagina'] as $property => $query) {
            if (! request()->has($query) && array_key_exists($property, $saved)) {
                $this->{$property} = $saved[$property];
            }
        }
    }

    public function getViewData(): array
    {
        $this->validate(['activeTab' => 'in:inventario,pedidos,produccion,historial', 'historialDesde' => 'nullable|date', 'stockPage' => 'integer|min:1|max:100000']);

        return app(BusinessLedger::class)->snapshot($this->activeTab, [
            'buscar' => $this->buscar, 'estado' => $this->estado, 'estadoPago' => $this->estadoPago,
            'historialTipo' => $this->historialTipo, 'historialDesde' => $this->historialDesde,
        ], $this->stockPage) + [
            'purchaseHistory' => $this->showPurchases ? app(BusinessLedger::class)->purchases($this->purchaseSearch, $this->purchasePage) : null,
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['activeTab', 'buscar', 'estado', 'estadoPago', 'historialTipo', 'historialDesde'], true)) {
            $this->stockPage = 1;
        }
        if ($property === 'purchaseSearch') {
            $this->purchasePage = 1;
        }
        if (in_array($property, ['activeTab', 'buscar', 'estado', 'estadoPago', 'historialTipo', 'historialDesde', 'stockPage'], true)) {
            session()->put('stock-navigation', [
                'activeTab' => $this->activeTab, 'buscar' => $this->buscar, 'estado' => $this->estado,
                'estadoPago' => $this->estadoPago, 'historialTipo' => $this->historialTipo,
                'historialDesde' => $this->historialDesde, 'stockPage' => $this->stockPage,
            ]);
        }
    }

    public function refreshStock(): void
    {
        app(BusinessLedger::class)->invalidateReads();
    }

    public function openPurchases(): void
    {
        $this->showPurchases = true;
        $this->dispatch('open-modal', id: 'purchase-history');
    }

    #[On('close-modal')]
    public function closePurchases(?string $id = null): void
    {
        if ($id === 'purchase-history') {
            $this->showPurchases = false;
        }
    }

    private function productForm(): array
    {
        return [
            TextInput::make('nombre')->label('Nombre del producto')->required()->maxLength(255),
            TextInput::make('sku')->label('SKU / código único')->required()->maxLength(80)->regex('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/'),
            Select::make('tipo')->label('Tipo')->options(['terminado' => 'Producto terminado', 'insumo' => 'Insumo'])->default('terminado')->native()->required(),
            Toggle::make('vendible')->label('Disponible para vender')->default(true),
            TextInput::make('precio_clp')->label('Precio de venta CLP')->numeric()->integer()->minValue(0)->maxValue(2147483647),
            TextInput::make('minimo')->label('Avisar al llegar a estas unidades')->numeric()->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
        ];
    }

    public function productoAction(): Action
    {
        return Action::make('producto')->label('Añadir producto')->modalDescription('Crea el producto y después registra una compra para ingresar sus existencias.')
            ->form($this->productForm())->action(function (array $data): void {
                app(BusinessLedger::class)->createProduct($data);
                Notification::make()->success()->title('Producto creado')->send();
            });
    }

    public function pagarCompraAction(): Action
    {
        return Action::make('pagarCompra')->label('Registrar pago de compra')->form([
            TextInput::make('monto_clp')->label('Importe pagado CLP')->numeric()->integer()->minValue(1)->required(),
            DatePicker::make('fecha')->label('Fecha')->default(now('America/Santiago')->toDateString())->required(),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data, array $arguments): void {
            app(BusinessLedger::class)->payPurchase($arguments['compra'], (int) $data['monto_clp'], $data['fecha'], $data['clave_operacion']);
            Notification::make()->success()->title('Pago registrado en caja')->send();
        });
    }

    public function entradaAction(): Action
    {
        return Action::make('entrada')->label('Registrar compra / entrada')->form([
            Select::make('producto_id')->label('Producto')->options(fn (): array => $this->productOptions())->required()->searchable()
                ->createOptionForm($this->productForm())
                ->createOptionUsing(fn (array $data): string => app(BusinessLedger::class)->createProduct($data)),
            TextInput::make('cantidad')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('costo_total_clp')->label('Costo total CLP')->helperText('Déjalo vacío si todavía no conoces el costo.')->numeric()->integer()->minValue(0),
            TextInput::make('pagado_clp')->label('Pagado CLP')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('proveedor')->maxLength(255),
            TextInput::make('documento')->label('Comprobante / referencia')->maxLength(255),
            DatePicker::make('fecha')->default(now('America/Santiago')->toDateString())->required(),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            app(BusinessLedger::class)->receive($data['producto_id'], (int) $data['cantidad'], $this->nullableInt($data['costo_total_clp'] ?? null), (int) $data['pagado_clp'], $data['proveedor'] ?? null, $data['documento'] ?? null, $data['fecha'], $data['clave_operacion']);
            Notification::make()->success()->title('Entrada registrada')->send();
        });
    }

    public function reservarAction(): Action
    {
        return Action::make('reservar')->label('Crear pedido / reserva')->form([
            Select::make('producto_id')->label('Producto vendible')->options(fn (): array => $this->productOptions(true))->required()->searchable(),
            TextInput::make('cantidad')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('precio_unitario_clp')->label('Precio por unidad CLP')->numeric()->integer()->minValue(0)->required(),
            TextInput::make('cliente')->label('Cliente / referencia')->maxLength(255),
            Select::make('canal')->options([
                'directo' => 'Directo',
                'whatsapp' => 'WhatsApp',
                'mercadolibre' => 'Mercado Libre',
                'marketplace' => 'Marketplace',
            ])->default('directo')->required(),
            TextInput::make('referencia_externa')->label('Referencia externa / folio')->maxLength(255),
            DatePicker::make('reservado_hasta')->label('Revisar reserva el día')->helperText('Fecha informativa de seguimiento comercial.'),
            TextInput::make('abono_inicial_clp')->label('Abono inicial recibido CLP')->numeric()->integer()->minValue(0)->default(0)->helperText('Si el cliente paga un anticipo al reservar, se registra automáticamente en caja.'),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            $ledger = app(BusinessLedger::class);
            $ledger->connection()->transaction(function () use ($ledger, $data): void {
                $orderId = $ledger->reserveOrder($data['producto_id'], (int) $data['cantidad'], (int) $data['precio_unitario_clp'], $data['cliente'] ?? null, $data['canal'], $data['referencia_externa'] ?? null, $data['reservado_hasta'] ?? null, $data['clave_operacion']);
                if (! empty($data['abono_inicial_clp']) && (int) $data['abono_inicial_clp'] > 0) {
                    $ledger->orderPayment($orderId, (int) $data['abono_inicial_clp'], now('America/Santiago')->toDateString(), $data['clave_operacion'].':abono');
                }
            });
            Notification::make()->success()->title('Pedido y reserva registrados')->send();
        });
    }

    public function producirAction(): Action
    {
        return Action::make('producir')->label('Registrar acrílicos terminados')->form([
            TextInput::make('cantidad')->label('Cantidad a fabricar')->numeric()->integer()->minValue(1)->helperText('Consume 1 Acrílico Base y 1 Chip apartado por cada unidad.')->required(),
            TextInput::make('impresion_clp')->label('Costo total de impresión CLP')->helperText('Vacío si aún no se conoce. Si es gratis, ingresa 0.')->numeric()->integer()->minValue(0),
            Toggle::make('impresion_pagada')->label('Impresión pagada con dinero de caja')->default(false),
            DatePicker::make('fecha')->label('Fecha de pago')->default(now('America/Santiago')->toDateString())->required(),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            app(BusinessLedger::class)->produce((int) $data['cantidad'], $this->nullableInt($data['impresion_clp'] ?? null), (bool) $data['impresion_pagada'], $data['fecha'], $data['clave_operacion']);
            Notification::make()->success()->title('Producción de acrílicos registrada')->send();
        });
    }

    public function apartarChipsAction(): Action
    {
        return Action::make('apartarChips')->label('Apartar chips')->color('gray')->form([
            TextInput::make('cantidad')->label('Cantidad de chips a apartar')->numeric()->integer()->minValue(1)->default(20)->helperText('Los aparta para producción de acrílicos sin consumirlos aún.')->required(),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            app(BusinessLedger::class)->reserveProduction((int) $data['cantidad'], $data['clave_operacion']);
            Notification::make()->success()->title('Chips apartados para producción')->send();
        });
    }

    public function liberarChipsAction(): Action
    {
        return Action::make('liberarChips')->label('Liberar chips')->color('gray')->form([
            TextInput::make('cantidad')->label('Cantidad a devolver a venta libre')->numeric()->integer()->minValue(1)->required(),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            app(BusinessLedger::class)->reserveProduction((int) $data['cantidad'], $data['clave_operacion'], true);
            Notification::make()->success()->title('Chips liberados para venta libre')->send();
        });
    }

    public function ajusteAction(): Action
    {
        return Action::make('ajuste')->label('Ajuste de inventario')->color('gray')->form([
            Select::make('producto_id')->label('Producto')->options(fn (): array => $this->productOptions())->required(),
            Radio::make('sentido')->label('Sentido del ajuste')->options([
                'merma' => 'Disminución / Merma (-)',
                'positivo' => 'Incremento / Recuento (+)',
            ])->default('merma')->required(),
            TextInput::make('cantidad')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('motivo')->label('Motivo obligatorio')->helperText('Describe la causa (ej. chip defectuoso, conteo físico, rotura).')->required()->maxLength(255),
            Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
        ])->action(function (array $data): void {
            $diff = $data['sentido'] === 'merma' ? -((int) $data['cantidad']) : (int) $data['cantidad'];
            app(BusinessLedger::class)->adjust($data['producto_id'], $diff, $data['motivo'], $data['clave_operacion']);
            Notification::make()->success()->title('Ajuste de stock registrado')->send();
        });
    }

    public function precioAction(): Action
    {
        return Action::make('precio')->label('Precio sugerido')->color('gray')->form([
            Select::make('producto_id')->label('Producto vendible')->options(fn (): array => $this->productOptions(true))->required(),
            TextInput::make('precio_clp')->label('Precio CLP')->numeric()->integer()->minValue(0),
        ])->action(function (array $data): void {
            app(BusinessLedger::class)->setPrice($data['producto_id'], $this->nullableInt($data['precio_clp'] ?? null));
            Notification::make()->success()->title('Precio actualizado')->send();
        });
    }

    public function deliver(string $id): void
    {
        app(BusinessLedger::class)->fulfillOrder($id, 'entrega:'.$id);
        Notification::make()->success()->title('Entrega registrada y stock descontado')->send();
    }

    public function cancel(string $id): void
    {
        app(BusinessLedger::class)->cancelOrder($id, 'cancelacion:'.$id);
        Notification::make()->success()->title('Reserva cancelada y stock liberado')->send();
    }

    public function registrarAbono(string $orderId, int $monto, ?string $fecha = null): void
    {
        $fecha = $fecha ?: now('America/Santiago')->toDateString();
        $key = 'abono:'.$orderId.':'.Str::uuid();
        app(BusinessLedger::class)->orderPayment($orderId, $monto, $fecha, $key);
        Notification::make()->success()->title('Abono registrado en caja')->send();
    }

    private function productOptions(bool $saleable = false): array
    {
        $connection = app(BusinessLedger::class)->connection();

        return app(InventoryReadCache::class)->remember($connection, 'product-options:'.(int) $saleable, function () use ($connection, $saleable): array {
            return $connection->table('inventario_productos')->where('activo', true)
                ->when($saleable, fn ($query) => $query->where('vendible', true))
                ->orderBy('nombre')->pluck('nombre', 'id')->all();
        });
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
