<?php

namespace App\Filament\Pages;

use App\Actions\RejectSaleAction;
use App\Support\BusinessLedger;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Ventas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $title = 'Ventas';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.ventas';

    protected ?string $maxContentWidth = 'full';

    public ?string $ventaId = null;

    public array $data = [];

    public string $rejectionReason = '';

    public function form(Form $form): Form
    {
        return $form->schema([
            Checkbox::make('tarjeta_google')
                ->label('Tarjeta Google')
                ->default(true)
                ->extraInputAttributes(['x-model' => 'google']),
            Radio::make('forma_cobro')
                ->label('Forma de cobro por cantidad')
                ->options([
                    'una' => '1 × $15.000 CLP',
                    'dos' => '2 × $20.000 CLP',
                    'personalizada' => 'Cantidad y precio recibido',
                ])
                ->extraInputAttributes(['x-model' => 'modo'])
                ->extraFieldWrapperAttributes(['x-show' => 'google', 'x-cloak' => true])
                ->columnSpanFull(),
            Grid::make(2)
                ->schema([
                    ViewField::make('cantidad_personalizada')
                        ->label('Cantidad de tarjetas')
                        ->view('filament.forms.components.quantity-counter')
                        ->extraFieldWrapperAttributes(['x-cloak' => true]),
                    TextInput::make('precio_recibido_clp')
                        ->label('Precio recibido total CLP')
                        ->helperText('Ingresa el total recibido por toda la cantidad, no el precio por unidad.')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(2147483647)
                        ->extraInputAttributes(['x-model' => 'totalPersonalizado']),
                ])
                ->columnSpanFull()
                ->extraAttributes([
                    'x-show' => "google && modo === 'personalizada'",
                    'x-cloak' => true,
                ]),
            TextInput::make('cliente_nombre')
                ->label('Cliente')
                ->placeholder('Escribe el nombre del cliente')
                ->maxLength(255),
            TextInput::make('local_nombre')
                ->label('Local o negocio (opcional)')
                ->placeholder('Escribe el nombre del local o negocio')
                ->maxLength(255),
        ])->statePath('data');
    }

    public function mount(?string $venta = null): void
    {
        $venta ??= request()->query('venta');
        abort_if($venta !== null && ! is_string($venta), 404);

        $this->ventaId = $venta;
        $sale = $venta ? $this->sale($venta) : null;
        abort_if($sale && $sale->estado === 'eliminada', 404);

        $this->data = $sale ? [
            'cliente_nombre' => $sale->cliente_nombre,
            'local_nombre' => $sale->local_nombre,
            'tarjeta_google' => (int) $sale->cantidad_tarjetas > 0,
            'forma_cobro' => $sale->forma_cobro ?? 'una',
            'cantidad_personalizada' => $sale->cantidad_tarjetas,
            'precio_recibido_clp' => $sale->precio_recibido_clp,
        ] : [
            'cliente_nombre' => '',
            'local_nombre' => '',
            'tarjeta_google' => true,
            'forma_cobro' => 'una',
            'cantidad_personalizada' => 1,
            'precio_recibido_clp' => 0,
        ];

        $this->form->fill($this->data);
    }

    public function getViewData(): array
    {
        if ($this->ventaId) {
            $db = app(BusinessLedger::class)->connection();
            $itemTotals = $db->table('venta_items')
                ->selectRaw('venta_id, SUM(cantidad * precio_unitario_clp) AS total_clp')
                ->groupBy('venta_id');
            $sale = $db->table('ventas as v')
                ->leftJoin('paginas as p', 'p.id', '=', 'v.pagina_id')
                ->leftJoinSub($itemTotals, 'it', 'it.venta_id', '=', 'v.id')
                ->where('v.id', $this->ventaId)
                ->selectRaw('v.*, p.nombre_publico AS pagina_nombre, COALESCE(NULLIF(v.precio_recibido_clp, 0), it.total_clp, 0) AS total_clp')
                ->first();
            abort_unless($sale, 404);

            return [
                'sale' => $sale,
                'pageId' => $sale->pagina_id,
                'pageName' => $sale->pagina_nombre,
            ];
        }

        $db = app(BusinessLedger::class)->connection();
        $itemTotals = $db->table('venta_items')
            ->selectRaw('venta_id, SUM(cantidad * precio_unitario_clp) AS total_clp')
            ->groupBy('venta_id');
        $query = $db->table('ventas as v')
            ->leftJoin('paginas as p', 'p.id', '=', 'v.pagina_id')
            ->leftJoinSub($itemTotals, 'it', 'it.venta_id', '=', 'v.id')
            ->selectRaw('v.*, p.nombre_publico AS pagina_nombre, COALESCE(NULLIF(v.precio_recibido_clp, 0), it.total_clp, 0) AS total_clp')
            ->orderByRaw("CASE WHEN v.estado = 'borrador' THEN 0 ELSE 1 END")
            ->orderByDesc('v.created_at');

        $sales = $query->limit(100)->get();

        return ['sale' => null, 'sales' => $sales];
    }

    public function createSale(): void
    {
        $id = (string) Str::uuid();
        $db = app(BusinessLedger::class)->connection();
        $db->table('ventas')->insert([
            'id' => $id,
            'estado' => 'borrador',
            'registrado_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->redirect(static::getUrl(['venta' => $id]), navigate: true);
    }

    public function saveDraft(): void
    {
        $this->saveSaleDetails($this->draftId(), $this->validatedSaleData());

        Notification::make()->success()->title('Borrador guardado')->send();
    }

    public function confirmSale(): void
    {
        $saleId = $this->draftId();
        $data = $this->validatedSaleData();
        $this->saveSaleDetails($saleId, $data);

        if (trim($data['cliente_nombre'] ?? '') === '') {
            throw ValidationException::withMessages([
                'data.cliente_nombre' => 'Escribe el nombre del cliente para confirmar la venta.',
            ]);
        }

        if (empty($data['tarjeta_google'])) {
            throw ValidationException::withMessages([
                'data.tarjeta_google' => 'Selecciona la tarjeta Google para confirmar la venta.',
            ]);
        }

        app(BusinessLedger::class)->confirmFieldSale($saleId);

        Notification::make()->success()->title('Venta confirmada y stock descontado')->send();
        $this->redirect(static::getUrl(['venta' => $saleId]), navigate: true);
    }

    public function deleteDraft(): void
    {
        $db = app(BusinessLedger::class)->connection();
        $deleted = $db->table('ventas')->where('id', $this->draftId())
            ->where('estado', 'borrador')->delete();
        abort_unless($deleted, 403);

        Notification::make()->success()->title('Borrador eliminado')->send();
        $this->redirect(static::getUrl(), navigate: true);
    }

    public function rejectSale(): void
    {
        abort_unless($this->ventaId, 404);
        $page = app(RejectSaleAction::class)->handle(auth()->user(), $this->ventaId, $this->rejectionReason);

        Notification::make()
            ->warning()
            ->title($page ? 'Página desactivada; retirada pública pendiente' : 'Venta marcada como no concretada')
            ->body($page ? 'Consulta Publicaciones para seguir la retirada. No se ajustaron pagos ni tarjetas entregadas.' : 'Se conservó el historial de la venta.')
            ->send();

        $this->redirect($page
            ? PublicacionesSitio::getUrl(['search' => $page->slug])
            : static::getUrl(['venta' => $this->ventaId]), navigate: true);
    }

    private function saveSaleDetails(string $saleId, array $data): void
    {
        $db = app(BusinessLedger::class)->connection();
        $hasCard = (bool) ($data['tarjeta_google'] ?? false);
        $paymentMethod = $hasCard ? ($data['forma_cobro'] ?? 'una') : null;
        $quantity = match ($paymentMethod) {
            'una' => 1,
            'dos' => 2,
            'personalizada' => (int) ($data['cantidad_personalizada'] ?? 0),
            default => 0,
        };
        $totalReceived = match ($paymentMethod) {
            'una' => 15000,
            'dos' => 20000,
            'personalizada' => (int) ($data['precio_recibido_clp'] ?? 0),
            default => 0,
        };

        if ($paymentMethod === 'personalizada' && $quantity < 1) {
            throw ValidationException::withMessages([
                'data.cantidad_personalizada' => 'Ingresa una cantidad de tarjetas mayor que cero.',
            ]);
        }

        $db->transaction(function () use ($db, $saleId, $data, $paymentMethod, $quantity, $totalReceived): void {
            $updated = $db->table('ventas')->where('id', $saleId)->where('estado', 'borrador')->update([
                'cliente_nombre' => trim($data['cliente_nombre'] ?? '') ?: null,
                'local_nombre' => trim($data['local_nombre'] ?? '') ?: null,
                'forma_cobro' => $paymentMethod,
                'cantidad_tarjetas' => $quantity,
                'precio_recibido_clp' => $totalReceived,
                'updated_at' => now(),
            ]);
            abort_unless($updated, 403);
            $db->table('venta_items')->where('venta_id', $saleId)->delete();

            if ($quantity === 0) {
                return;
            }

            $productId = $db->table('inventario_productos')->where('sku', 'TAR-GOOGLE')->value('id');
            if (! $productId) {
                throw ValidationException::withMessages([
                    'data.tarjeta_google' => 'La Tarjeta Google no está configurada en el inventario de Supabase.',
                ]);
            }

            $basePrice = intdiv($totalReceived, $quantity);
            $remainder = $totalReceived % $quantity;
            $items = [];
            $now = now();
            $regularQuantity = $quantity - $remainder;
            if ($regularQuantity > 0) {
                $items[] = [
                    'id' => (string) Str::uuid(),
                    'venta_id' => $saleId,
                    'producto_id' => $productId,
                    'cantidad' => $regularQuantity,
                    'precio_unitario_clp' => $basePrice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($remainder > 0) {
                $items[] = [
                    'id' => (string) Str::uuid(),
                    'venta_id' => $saleId,
                    'producto_id' => $productId,
                    'cantidad' => $remainder,
                    'precio_unitario_clp' => $basePrice + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $db->table('venta_items')->insert($items);
        });
    }

    private function validatedSaleData(): array
    {
        $hasCard = filter_var($this->data['tarjeta_google'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $customPricing = $hasCard && ($this->data['forma_cobro'] ?? null) === 'personalizada';

        return $this->validate([
            'data.tarjeta_google' => ['required', 'boolean'],
            'data.forma_cobro' => [$hasCard ? 'required' : 'nullable', 'in:una,dos,personalizada'],
            'data.cantidad_personalizada' => [$customPricing ? 'required' : 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'data.precio_recibido_clp' => [$customPricing ? 'required' : 'nullable', 'integer', 'min:0', 'max:2147483647'],
            'data.cliente_nombre' => ['nullable', 'string', 'max:255'],
            'data.local_nombre' => ['nullable', 'string', 'max:255'],
        ])['data'];
    }

    private function sale(string $id): object
    {
        $sale = app(BusinessLedger::class)->connection()->table('ventas')->where('id', $id)->first();
        abort_unless($sale, 404);

        return $sale;
    }

    private function draftId(): string
    {
        abort_unless($this->ventaId, 404);

        return $this->ventaId;
    }
}
