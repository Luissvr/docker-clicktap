<?php

namespace App\Filament\Pages;

use App\Support\BusinessLedger;
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

class StockTarjetas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Stock de tarjetas';

    protected static ?string $title = 'Stock de tarjetas';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.stock-tarjetas';

    protected ?string $maxContentWidth = 'full';

    public string $activeTab = 'inventario';

    public string $buscar = '';

    public string $estado = 'todos';

    public string $estadoPago = 'todos';

    public string $historialTipo = 'todos';

    public ?string $historialDesde = null;

    public function getViewData(): array
    {
        $data = app(BusinessLedger::class)->snapshot($this->activeTab);
        $term = mb_strtolower(trim($this->buscar));

        if ($term !== '') {
            $data['products'] = $data['products']->filter(fn ($row): bool => str_contains(mb_strtolower($row->nombre.' '.$row->sku), $term));
            $data['orders'] = $data['orders']->filter(fn ($row): bool => str_contains(mb_strtolower($row->producto_nombre.' '.($row->cliente ?? '').' '.$row->canal.' '.($row->referencia_externa ?? '')), $term));
            $data['movements'] = $data['movements']->filter(fn ($row): bool => str_contains(mb_strtolower($row->producto_nombre.' '.$row->tipo.' '.($row->motivo ?? '')), $term));
        }

        if ($this->estado !== 'todos') {
            $data['orders'] = $data['orders']->filter(fn ($row): bool => $row->estado === $this->estado);
        }

        if ($this->estadoPago !== 'todos') {
            $data['orders'] = $data['orders']->filter(fn ($row): bool => $row->estado_pago === $this->estadoPago);
        }

        if ($this->historialTipo !== 'todos') {
            $data['movements'] = $data['movements']->filter(fn ($row): bool => $row->tipo === $this->historialTipo);
        }

        if ($this->historialDesde) {
            $data['movements'] = $data['movements']->filter(fn ($row): bool => substr($row->created_at, 0, 10) >= $this->historialDesde);
        }

        return $data;
    }

    public function updatedActiveTab(): void
    {
        $this->buscar = '';
    }

    public function entradaAction(): Action
    {
        return Action::make('entrada')->label('Registrar compra / entrada')->form([
            Select::make('producto_id')->label('Producto')->options(fn (): array => $this->productOptions())->required()->searchable(),
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
            $orderId = app(BusinessLedger::class)->reserveOrder($data['producto_id'], (int) $data['cantidad'], (int) $data['precio_unitario_clp'], $data['cliente'] ?? null, $data['canal'], $data['referencia_externa'] ?? null, $data['reservado_hasta'] ?? null, $data['clave_operacion']);
            if (! empty($data['abono_inicial_clp']) && (int) $data['abono_inicial_clp'] > 0) {
                app(BusinessLedger::class)->orderPayment($orderId, (int) $data['abono_inicial_clp'], now('America/Santiago')->toDateString(), $data['clave_operacion'].':abono');
            }
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
        $query = app(BusinessLedger::class)->connection()->table('inventario_productos')->where('activo', true);
        if ($saleable) {
            $query->where('vendible', true);
        }

        return $query->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
