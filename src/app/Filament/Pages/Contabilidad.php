<?php

namespace App\Filament\Pages;

use App\Support\BusinessLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class Contabilidad extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $navigationLabel = 'Contabilidad';

    protected static ?string $title = 'Contabilidad';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.contabilidad';

    public string $activeTab = 'resumen';

    public ?string $desde = null;

    public ?string $hasta = null;

    public string $tipoMovimiento = 'todos';

    public ?int $simPrecioTarjeta = null;

    public ?int $simPrecioChip = null;

    public ?int $simPrecioAcrilico = null;

    public ?int $simCostoImpresion = null;

    public function mount(): void
    {
        $products = app(BusinessLedger::class)->connection()->table('inventario_productos')->get()->keyBy('sku');
        $this->simPrecioTarjeta = $products->get('TAR-GOOGLE')?->precio_clp ?? 10000;
        $this->simPrecioChip = $products->get('NFC-CHIP')?->precio_clp ?? 2500;
        $this->simPrecioAcrilico = $products->get('ACR-PERSONALIZADO')?->precio_clp ?? 15000;
        $this->simCostoImpresion = 2000;
    }

    public function getViewData(): array
    {
        $data = app(BusinessLedger::class)->finances($this->desde, $this->hasta);

        if ($this->tipoMovimiento !== 'todos') {
            $data['entries'] = $data['entries']->filter(fn ($row): bool => $row->tipo === $this->tipoMovimiento);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('movimiento')->label('Registrar movimiento de caja')->color('primary')->form([
                Select::make('tipo')->label('Tipo de movimiento')->options([
                    'aporte' => 'Aporte de capital (+)',
                    'gasto' => 'Gasto operativo (-)',
                    'retiro' => 'Retiro de socio (-)',
                ])->required(),
                Select::make('categoria')->label('Categoría')->options([
                    'capital' => 'Capital / Aporte inicial',
                    'publicidad' => 'Publicidad y marketing',
                    'transporte' => 'Transporte y fletes',
                    'empaque' => 'Empaque y despacho',
                    'software' => 'Software y herramientas',
                    'servicios' => 'Servicios de impresión y terceros',
                    'otros' => 'Otros gastos',
                ])->required(),
                TextInput::make('monto_clp')->label('Monto CLP')->numeric()->integer()->minValue(1)->required(),
                DatePicker::make('fecha')->label('Fecha')->default(now('America/Santiago')->toDateString())->required(),
                TextInput::make('descripcion')->label('Descripción')->placeholder('Ej. Aporte inicial informado, Publicidad Meta Ads...')->required()->maxLength(255),
                TextInput::make('documento')->label('Comprobante / Referencia')->maxLength(255),
                Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
            ])->action(function (array $data): void {
                app(BusinessLedger::class)->recordCash($data['tipo'], $data['categoria'], (int) $data['monto_clp'], $data['fecha'], $data['descripcion'], $data['clave_operacion'], $data['documento'] ?? null);
                Notification::make()->success()->title('Movimiento de caja registrado')->send();
            }),

            Action::make('cobro')->label('Registrar cobro de venta')->form([
                Select::make('pedido_id')->label('Pedido a abonar')->options(fn (): array => $this->orderOptions())->searchable()->required(),
                TextInput::make('monto_clp')->label('Monto recibido CLP')->numeric()->integer()->minValue(1)->required(),
                DatePicker::make('fecha')->default(now('America/Santiago')->toDateString())->required(),
                Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
            ])->action(function (array $data): void {
                app(BusinessLedger::class)->orderPayment($data['pedido_id'], (int) $data['monto_clp'], $data['fecha'], $data['clave_operacion']);
                Notification::make()->success()->title('Cobro registrado en caja')->send();
            }),

            Action::make('pagarCompra')->label('Pagar compra a proveedor')->color('gray')->form([
                Select::make('compra_id')->label('Compra pendiente de pago')->options(fn (): array => $this->purchaseOptions())->searchable()->required(),
                TextInput::make('monto_clp')->label('Monto a pagar CLP')->numeric()->integer()->minValue(1)->required(),
                DatePicker::make('fecha')->default(now('America/Santiago')->toDateString())->required(),
                Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
            ])->action(function (array $data): void {
                app(BusinessLedger::class)->payPurchase($data['compra_id'], (int) $data['monto_clp'], $data['fecha'], $data['clave_operacion']);
                Notification::make()->success()->title('Pago de compra registrado y deuda reducida')->send();
            }),

            Action::make('reembolso')->label('Reembolsar reserva')->color('gray')->form([
                Select::make('pedido_id')->label('Pedido cancelado o reservado')->options(fn (): array => $this->orderOptions())->searchable()->required(),
                TextInput::make('monto_clp')->label('Monto a reembolsar CLP')->numeric()->integer()->minValue(1)->required(),
                DatePicker::make('fecha')->default(now('America/Santiago')->toDateString())->required(),
                Hidden::make('clave_operacion')->default(fn (): string => (string) Str::uuid()),
            ])->action(function (array $data): void {
                app(BusinessLedger::class)->orderPayment($data['pedido_id'], (int) $data['monto_clp'], $data['fecha'], $data['clave_operacion'], true);
                Notification::make()->success()->title('Reembolso registrado')->send();
            }),
        ];
    }

    public function pagarSaldo(string $compraId, int $monto): void
    {
        $fecha = now('America/Santiago')->toDateString();
        $key = 'pago-compra:'.$compraId.':'.Str::uuid();
        app(BusinessLedger::class)->payPurchase($compraId, $monto, $fecha, $key);
        Notification::make()->success()->title('Pago a compra registrado exitosamente')->send();
    }

    private function orderOptions(): array
    {
        return app(BusinessLedger::class)->connection()->table('inventario_pedidos as p')
            ->join('inventario_productos as i', 'i.id', '=', 'p.producto_id')
            ->where('p.estado', '!=', 'cancelado')->orderByDesc('p.created_at')->limit(100)
            ->get(['p.id', 'p.cliente', 'p.cantidad', 'p.precio_unitario_clp', 'i.nombre'])
            ->mapWithKeys(function ($row): array {
                $total = $row->cantidad * $row->precio_unitario_clp;

                return [$row->id => $row->nombre.' × '.$row->cantidad.' — '.($row->cliente ?: 'Directo').' (Total: $'.number_format($total, 0, ',', '.').')'];
            })->all();
    }

    private function purchaseOptions(): array
    {
        return app(BusinessLedger::class)->connection()->table('inventario_compras as c')
            ->join('inventario_productos as i', 'i.id', '=', 'c.producto_id')
            ->whereNotNull('c.costo_total_clp')->whereColumn('c.pagado_clp', '<', 'c.costo_total_clp')
            ->orderByDesc('c.created_at')->limit(100)->get(['c.id', 'c.costo_total_clp', 'c.pagado_clp', 'i.nombre', 'c.proveedor'])
            ->mapWithKeys(fn ($row): array => [$row->id => $row->nombre.' ('.($row->proveedor ?: 'Sin proveedor').') — Saldo pendiente: $'.number_format($row->costo_total_clp - $row->pagado_clp, 0, ',', '.')])->all();
    }
}

