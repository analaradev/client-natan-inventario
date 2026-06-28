<?php

namespace App\Http\Controllers;

use App\Models\CorteCaja;
use App\Models\IngresoCaja;
use App\Models\SubInventario;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorteCajaController extends Controller
{
    public function __construct(
        private ExcelReportService $excelReportService,
        private PdfReportService $pdfReportService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $estadisticas = [
            'total_cortes' => (clone $query)->count(),
            'total_sistema' => (clone $query)->sum('total_sistema'),
            'total_reportado' => (clone $query)->sum('total_reportado'),
            'diferencia' => (clone $query)->sum('diferencia'),
        ];
        $cortes = $query->paginate(15)->withQueryString();

        return view('cortes.index', compact('cortes', 'estadisticas'));
    }

    public function create(Request $request)
    {
        $fechaCorte = $request->get('fecha_corte', now()->toDateString());
        $tipoInventario = $request->get('tipo_inventario', 'todos');
        $subinventarioId = $request->get('subinventario_id');
        $subinventarios = SubInventario::orderBy('descripcion')->get();

        if ($tipoInventario === 'subinventario' && !$subinventarioId && $subinventarios->count() === 1) {
            $subinventarioId = $subinventarios->first()->id;
        }

        $ingresos = $this->ingresosQuery($fechaCorte, $tipoInventario, $subinventarioId)
            ->with(['venta', 'apartado', 'pago', 'abono', 'subinventario'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $resumen = $this->calcularResumen($ingresos);

        return view('cortes.create', compact(
            'fechaCorte',
            'tipoInventario',
            'subinventarioId',
            'ingresos',
            'resumen',
            'subinventarios'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha_corte' => 'required|date',
            'tipo_inventario' => 'required|in:todos,general,subinventario',
            'subinventario_id' => 'nullable|required_if:tipo_inventario,subinventario|exists:subinventarios,id',
            'total_reportado' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'fecha_corte.required' => 'La fecha del corte es obligatoria',
            'subinventario_id.required_if' => 'Selecciona el subinventario del corte',
            'total_reportado.required' => 'Captura el total contado o reportado',
            'total_reportado.min' => 'El total contado o reportado no puede ser negativo',
        ]);

        $ingresos = $this->ingresosQuery(
            $validated['fecha_corte'],
            $validated['tipo_inventario'],
            $validated['subinventario_id'] ?? null
        )->get();

        if ($ingresos->isEmpty()) {
            return back()->withErrors(['error' => 'No hay ingresos activos sin corte para esa fecha.'])->withInput();
        }

        $resumen = $this->calcularResumen($ingresos);
        $totalReportado = (float) $validated['total_reportado'];

        $corte = DB::transaction(function () use ($validated, $ingresos, $resumen, $totalReportado) {
            $corte = CorteCaja::create([
                'fecha_corte' => $validated['fecha_corte'],
                'tipo_inventario' => $validated['tipo_inventario'],
                'subinventario_id' => $validated['tipo_inventario'] === 'subinventario' ? $validated['subinventario_id'] : null,
                'total_efectivo' => $resumen['metodos']['efectivo'] ?? 0,
                'total_tarjeta' => $resumen['metodos']['tarjeta'] ?? 0,
                'total_transferencia' => $resumen['metodos']['transferencia'] ?? 0,
                'total_no_especificado' => $resumen['metodos']['no_especificado'] ?? 0,
                'total_sistema' => $resumen['total'],
                'total_reportado' => $totalReportado,
                'diferencia' => $totalReportado - $resumen['total'],
                'estado' => 'cerrado',
                'usuario_cierre' => session('username', 'Sistema'),
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $corte->ingresos()->attach($ingresos->pluck('id')->all());

            return $corte;
        });

        return redirect()->route('cortes.show', $corte)
            ->with('success', 'Corte de caja cerrado correctamente.');
    }

    public function show(CorteCaja $corte)
    {
        $corte->load($this->corteRelations());
        $resumen = $this->calcularResumen($corte->ingresos);
        $productos = $this->productosDelCorte($corte);

        return view('cortes.show', compact('corte', 'resumen', 'productos'));
    }

    public function exportExcel(Request $request)
    {
        $cortes = $this->buildFilteredQuery($request)->get();
        $filtros = $this->buildFiltersList($request);

        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cortes');

        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE CORTES DE CAJA', 'K', 1);
        $row++;
        $row = $this->excelReportService->setFilters($sheet, $filtros, $row);

        $headers = ['ID', 'Fecha', 'Caja de', 'Efectivo', 'Tarjeta', 'Transferencia', 'No especificado', 'Sistema', 'Reportado', 'Diferencia', 'Usuario'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);

        $data = [];
        foreach ($cortes as $corte) {
            $data[] = [
                $corte->id,
                $corte->fecha_corte->format('d/m/Y'),
                $this->tipoInventarioLabel($corte),
                (float) $corte->total_efectivo,
                (float) $corte->total_tarjeta,
                (float) $corte->total_transferencia,
                (float) $corte->total_no_especificado,
                (float) $corte->total_sistema,
                (float) $corte->total_reportado,
                (float) $corte->diferencia,
                $corte->usuario_cierre,
            ];
        }

        $firstDataRow = $row;
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);

        if ($lastRow > $firstDataRow) {
            foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
                $this->excelReportService->formatCurrency($sheet, $column, $firstDataRow, $lastRow - 1);
            }
        }

        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']);

        $filename = $this->excelReportService->generateFilename('reporte_cortes_caja');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    public function exportPdf(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $totalCount = (clone $query)->count();

        if ($totalCount > 100) {
            return response()->json([
                'error' => true,
                'message' => 'El reporte PDF solo soporta hasta 100 cortes. Usa Excel o filtra por una fecha.',
            ], 422);
        }

        $cortes = $query->get();
        $filtros = $this->buildFiltersList($request);
        $estadisticas = [
            'cantidad' => $cortes->count(),
            'total_sistema' => $cortes->sum('total_sistema'),
            'total_reportado' => $cortes->sum('total_reportado'),
            'diferencia' => $cortes->sum('diferencia'),
        ];
        $styles = $this->pdfReportService->getBaseStyles();
        $filename = $this->pdfReportService->generateFilename('reporte_cortes_caja');

        return $this->pdfReportService->generate(
            'cortes.pdf-report',
            compact('cortes', 'filtros', 'estadisticas', 'styles'),
            $filename,
            ['orientation' => 'landscape']
        );
    }

    public function exportIndividualExcel(CorteCaja $corte)
    {
        $corte->load($this->corteRelations());
        $resumen = $this->calcularResumen($corte->ingresos);
        $productos = $this->productosDelCorte($corte);

        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Corte #' . $corte->id);

        $row = $this->excelReportService->setTitle($sheet, 'CORTE DE CAJA #' . $corte->id, 'G', 1);
        $row++;

        $summary = [
            ['Fecha', $corte->fecha_corte->format('d/m/Y')],
            ['Caja de', $this->tipoInventarioLabel($corte)],
            ['Total sistema', (float) $corte->total_sistema],
            ['Total reportado', (float) $corte->total_reportado],
            ['Diferencia', (float) $corte->diferencia],
            ['Cerrado por', $corte->usuario_cierre],
            ['Observaciones', $corte->observaciones ?: 'Sin observaciones'],
        ];

        foreach ($summary as [$label, $value]) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $value);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
        }

        foreach (['B' . ($row - 5), 'B' . ($row - 4), 'B' . ($row - 3)] as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('$#,##0.00');
        }

        $row += 2;
        $sheet->setCellValue('A' . $row, 'Resumen por metodo de pago');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        $row = $this->excelReportService->setTableHeaders($sheet, ['Metodo', 'Total'], $row);

        $firstSummaryRow = $row;
        foreach (IngresoCaja::metodosPago() as $key => $label) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $resumen['metodos'][$key] ?? 0);
            $row++;
        }
        $this->excelReportService->formatCurrency($sheet, 'B', $firstSummaryRow, $row - 1);

        $row += 2;
        $sheet->setCellValue('A' . $row, 'Ingresos incluidos');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        $headers = ['Hora', 'Concepto', 'Referencia', 'Metodo', 'Caja de', 'Monto', 'Usuario'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);

        $data = [];
        foreach ($corte->ingresos as $ingreso) {
            $data[] = [
                $ingreso->fecha?->format('H:i') ?? '--:--',
                IngresoCaja::conceptos()[$ingreso->concepto] ?? ucfirst($ingreso->concepto),
                $this->ingresoReferencia($ingreso),
                IngresoCaja::metodosPago()[$ingreso->metodo_pago] ?? $ingreso->metodo_pago,
                $this->ingresoOrigen($ingreso),
                (float) $ingreso->monto,
                $ingreso->usuario,
            ];
        }

        $firstIncomeRow = $row;
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        if ($lastRow > $firstIncomeRow) {
            $this->excelReportService->formatCurrency($sheet, 'F', $firstIncomeRow, $lastRow - 1);
        }

        $row = $lastRow + 2;
        $sheet->setCellValue('A' . $row, 'Productos vendidos y apartados relacionados');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        $headers = ['Producto', 'Codigo', 'Operacion', 'Referencias', 'Caja de', 'Cantidad', 'Precio unitario', 'Total'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);

        $productData = [];
        foreach ($productos as $producto) {
            $productData[] = [
                $producto['nombre'],
                $producto['codigo'],
                $producto['operacion'],
                $producto['referencias'],
                $producto['caja'],
                $producto['cantidad'],
                (float) $producto['precio_unitario'],
                (float) $producto['total'],
            ];
        }

        $firstProductRow = $row;
        $lastProductRow = $this->excelReportService->fillData($sheet, $productData, $row);
        if ($lastProductRow > $firstProductRow) {
            $this->excelReportService->formatCurrency($sheet, 'G', $firstProductRow, $lastProductRow - 1);
            $this->excelReportService->formatCurrency($sheet, 'H', $firstProductRow, $lastProductRow - 1);
        }

        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

        $filename = 'corte_caja_' . $corte->id . '_' . $corte->fecha_corte->format('Y-m-d') . '.xlsx';
        $this->excelReportService->download($spreadsheet, $filename);
    }

    public function exportIndividualPdf(CorteCaja $corte)
    {
        $corte->load($this->corteRelations());
        $resumen = $this->calcularResumen($corte->ingresos);
        $productos = $this->productosDelCorte($corte);
        $styles = $this->pdfReportService->getBaseStyles();
        $filename = 'corte_caja_' . $corte->id . '_' . $corte->fecha_corte->format('Y-m-d') . '.pdf';

        return $this->pdfReportService->generate(
            'cortes.pdf-individual',
            compact('corte', 'resumen', 'productos', 'styles'),
            $filename,
            ['orientation' => 'landscape']
        );
    }

    private function corteRelations(): array
    {
        return [
            'subinventario',
            'ingresos.venta.movimientos.libro',
            'ingresos.venta.subinventario',
            'ingresos.apartado.detalles.libro',
            'ingresos.apartado.subinventario',
            'ingresos.pago',
            'ingresos.abono',
            'ingresos.subinventario',
        ];
    }

    private function buildFilteredQuery(Request $request)
    {
        $query = CorteCaja::with('subinventario')->latest('fecha_corte')->latest('id');

        if ($request->filled('fecha_corte')) {
            $query->whereDate('fecha_corte', $request->fecha_corte);
        }

        if ($request->filled('tipo_inventario')) {
            $query->where('tipo_inventario', $request->tipo_inventario);
        }

        return $query;
    }

    private function buildFiltersList(Request $request): array
    {
        $filtros = [];

        if ($request->filled('fecha_corte')) {
            $filtros[] = 'Fecha del corte: ' . date('d/m/Y', strtotime($request->fecha_corte));
        }

        if ($request->filled('tipo_inventario')) {
            $labels = [
                'general' => 'Inventario general',
                'subinventario' => 'Subinventarios',
                'todos' => 'Todos',
            ];
            $filtros[] = 'Caja de: ' . ($labels[$request->tipo_inventario] ?? $request->tipo_inventario);
        }

        if (empty($filtros)) {
            $filtros[] = 'Sin filtros aplicados';
        }

        return $filtros;
    }

    private function ingresosQuery(string $fechaCorte, string $tipoInventario, mixed $subinventarioId)
    {
        $query = IngresoCaja::query()
            ->whereDate('fecha', $fechaCorte)
            ->where('estado', 'activo')
            ->whereDoesntHave('cortes');

        if ($tipoInventario === 'general') {
            $query->where('tipo_inventario', 'general');
        }

        if ($tipoInventario === 'subinventario') {
            $query->where('tipo_inventario', 'subinventario')
                ->where('subinventario_id', $subinventarioId);
        }

        return $query;
    }

    private function calcularResumen($ingresos): array
    {
        $metodos = collect(IngresoCaja::metodosPago())->mapWithKeys(fn ($label, $key) => [$key => 0.0])->all();
        $conceptos = collect(IngresoCaja::conceptos())->mapWithKeys(fn ($label, $key) => [$key => 0.0])->all();

        foreach ($ingresos as $ingreso) {
            $metodo = $ingreso->metodo_pago ?? 'no_especificado';
            $concepto = $ingreso->concepto ?? 'venta';
            $metodos[$metodo] = ($metodos[$metodo] ?? 0) + (float) $ingreso->monto;
            $conceptos[$concepto] = ($conceptos[$concepto] ?? 0) + (float) $ingreso->monto;
        }

        return [
            'metodos' => $metodos,
            'conceptos' => $conceptos,
            'total' => array_sum($metodos),
            'cantidad' => $ingresos->count(),
        ];
    }

    private function productosDelCorte(CorteCaja $corte)
    {
        $items = [];
        $ventasProcesadas = [];
        $apartadosProcesados = [];

        foreach ($corte->ingresos as $ingreso) {
            if ($ingreso->concepto === 'venta' && $ingreso->venta_id && $ingreso->venta && !isset($ventasProcesadas[$ingreso->venta_id])) {
                $ventasProcesadas[$ingreso->venta_id] = true;
                $operacion = $ingreso->venta->apartado_id ? 'Apartado liquidado' : 'Venta';

                foreach ($ingreso->venta->movimientos as $movimiento) {
                    if ($movimiento->tipo_movimiento !== 'salida' || $movimiento->tipo_salida !== 'venta') {
                        continue;
                    }

                    $items[] = $this->productoItem(
                        $movimiento->libro_id,
                        $movimiento->libro?->nombre ?? 'Producto eliminado',
                        $movimiento->libro?->codigo_barras ?? 'N/A',
                        $operacion,
                        'Venta #' . $ingreso->venta_id,
                        $this->ingresoOrigen($ingreso),
                        (int) $movimiento->cantidad,
                        (float) $movimiento->precio_unitario,
                        (float) ($movimiento->descuento ?? 0)
                    );
                }
            }

            if (in_array($ingreso->concepto, ['enganche_apartado', 'abono_apartado'], true)
                && $ingreso->apartado_id
                && $ingreso->apartado
                && !isset($apartadosProcesados[$ingreso->apartado_id])) {
                $apartadosProcesados[$ingreso->apartado_id] = true;

                foreach ($ingreso->apartado->detalles as $detalle) {
                    $items[] = $this->productoItem(
                        $detalle->libro_id,
                        $detalle->libro?->nombre ?? 'Producto eliminado',
                        $detalle->libro?->codigo_barras ?? 'N/A',
                        'Apartado',
                        'Apartado ' . ($ingreso->apartado->folio ?? ('#' . $ingreso->apartado_id)),
                        $this->apartadoOrigen($ingreso->apartado),
                        (int) $detalle->cantidad,
                        (float) $detalle->precio_unitario,
                        (float) ($detalle->descuento ?? 0)
                    );
                }
            }
        }

        return collect($items)
            ->groupBy(fn ($item) => implode('|', [
                $item['libro_id'],
                $item['operacion'],
                $item['caja'],
                number_format($item['precio_unitario'], 2, '.', ''),
            ]))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'nombre' => $first['nombre'],
                    'codigo' => $first['codigo'],
                    'operacion' => $first['operacion'],
                    'referencias' => $group->pluck('referencia')->unique()->implode(', '),
                    'caja' => $first['caja'],
                    'cantidad' => $group->sum('cantidad'),
                    'precio_unitario' => $first['precio_unitario'],
                    'total' => $group->sum('total'),
                ];
            })
            ->sortBy([
                ['operacion', 'asc'],
                ['nombre', 'asc'],
            ])
            ->values();
    }

    private function productoItem(
        mixed $libroId,
        string $nombre,
        string $codigo,
        string $operacion,
        string $referencia,
        string $caja,
        int $cantidad,
        float $precioUnitario,
        float $descuento
    ): array {
        $precioFinal = $precioUnitario;

        if ($descuento > 0) {
            $precioFinal -= ($precioUnitario * $descuento / 100);
        }

        return [
            'libro_id' => $libroId,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'operacion' => $operacion,
            'referencia' => $referencia,
            'caja' => $caja,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioFinal,
            'total' => $precioFinal * $cantidad,
        ];
    }

    private function tipoInventarioLabel(CorteCaja $corte): string
    {
        if ($corte->tipo_inventario === 'subinventario') {
            return $corte->subinventario->descripcion ?? 'Subinventario';
        }

        if ($corte->tipo_inventario === 'general') {
            return 'Inventario general';
        }

        return 'Todos';
    }

    private function ingresoReferencia(IngresoCaja $ingreso): string
    {
        if ($ingreso->venta_id) {
            return 'Venta #' . $ingreso->venta_id;
        }

        if ($ingreso->apartado_id) {
            return 'Apartado ' . ($ingreso->apartado->folio ?? ('#' . $ingreso->apartado_id));
        }

        return '-';
    }

    private function ingresoOrigen(IngresoCaja $ingreso): string
    {
        if ($ingreso->tipo_inventario === 'subinventario') {
            return $ingreso->subinventario->descripcion ?? 'Subinventario';
        }

        return 'Inventario general';
    }

    private function apartadoOrigen($apartado): string
    {
        if ($apartado->tipo_inventario === 'subinventario') {
            return $apartado->subinventario->descripcion ?? 'Subinventario';
        }

        return 'Inventario general';
    }
}
