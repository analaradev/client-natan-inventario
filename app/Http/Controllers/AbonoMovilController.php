<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Apartado;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AbonoMovilController extends Controller
{
    /**
     * Listar todos los apartados activos/vencidos
     * GET /api/v1/movil/apartados
     */
    public function listarApartados(Request $request)
    {
        try {
            // Parámetros opcionales para filtrar
            $estado = $request->query('estado'); // activo, vencido, todos
            $limite = $request->query('limite', 50); // Por defecto 50 apartados
            
            $query = Apartado::with(['cliente', 'detalles.libro', 'abonos']);
            
            // Filtrar por estado
            if ($estado === 'todos') {
                $query->whereIn('estado', ['activo', 'vencido', 'liquidado']);
            } elseif ($estado === 'liquidado') {
                $query->where('estado', 'liquidado');
            } else {
                // Por defecto solo activos y vencidos (los que pueden recibir abonos)
                $query->whereIn('estado', ['activo', 'vencido']);
            }
            
            // Ordenar por fecha de apartado descendente
            $apartados = $query->orderBy('fecha_apartado', 'desc')
                ->limit($limite)
                ->get();
            
            if ($apartados->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron apartados'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'total' => $apartados->count(),
                'data' => $apartados->map(function ($apartado) {
                    return $this->formatearApartado($apartado);
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar apartados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar apartados por folio
     * GET /api/v1/movil/apartados/buscar-folio/{folio?}
     * Si no se proporciona folio, devuelve todos los apartados
     */
    public function buscarPorFolio($folio = null)
    {
        try {
            // Si no se proporciona folio, listar todos los apartados
            if (empty($folio)) {
                $apartados = Apartado::with(['cliente', 'detalles.libro', 'abonos'])
                    ->whereIn('estado', ['activo', 'vencido'])
                    ->orderBy('fecha_apartado', 'desc')
                    ->get();

                if ($apartados->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontraron apartados'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'total' => $apartados->count(),
                    'data' => $apartados->map(function ($apartado) {
                        return $this->formatearApartado($apartado);
                    })
                ]);
            }

            // Si se proporciona folio, buscar específicamente
            $apartado = Apartado::with(['cliente', 'detalles.libro', 'abonos'])
                ->where('folio', $folio)
                ->first();

            if (!$apartado) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún apartado con ese folio'
                ], 404);
            }

            // Verificar si el apartado está en estado activo
            if ($apartado->estado === 'cancelado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este apartado está cancelado y no puede recibir abonos'
                ], 400);
            }

            if ($apartado->estado === 'liquidado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este apartado ya está liquidado'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatearApartado($apartado)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar apartado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar apartados por nombre de cliente
     * GET /api/v1/movil/apartados/buscar-cliente?nombre={nombre}
     * Si no se proporciona nombre, devuelve todos los clientes con apartados
     */
    public function buscarPorCliente(Request $request)
    {
        try {
            $nombre = $request->query('nombre');

            // Si no se proporciona nombre, listar todos los clientes con apartados
            if (empty($nombre)) {
                $clientes = Cliente::with(['apartados' => function ($query) {
                        $query->whereIn('estado', ['activo', 'vencido'])
                            ->with(['detalles.libro', 'abonos'])
                            ->orderBy('fecha_apartado', 'desc');
                    }])
                    ->get();

                // Filtrar clientes que tengan apartados activos
                $clientesConApartados = $clientes->filter(function ($cliente) {
                    return $cliente->apartados->isNotEmpty();
                })->values();

                if ($clientesConApartados->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontraron clientes con apartados activos'
                    ], 404);
                }

                // Formatear respuesta
                $resultado = $clientesConApartados->map(function ($cliente) {
                    return [
                        'cliente_id' => $cliente->id,
                        'nombre_cliente' => $cliente->nombre,
                        'telefono_cliente' => $cliente->telefono,
                        'apartados' => $cliente->apartados->map(function ($apartado) {
                            return $this->formatearApartado($apartado);
                        })
                    ];
                });

                return response()->json([
                    'success' => true,
                    'total_clientes' => $clientesConApartados->count(),
                    'data' => $resultado
                ]);
            }

            // Si se proporciona nombre, buscar específicamente
            $clientes = Cliente::where('nombre', 'like', '%' . $nombre . '%')
                ->with(['apartados' => function ($query) {
                    $query->whereIn('estado', ['activo', 'vencido'])
                        ->with(['detalles.libro', 'abonos'])
                        ->orderBy('fecha_apartado', 'desc');
                }])
                ->get();

            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron clientes con ese nombre'
                ], 404);
            }

            // Filtrar clientes que tengan apartados activos
            $clientesConApartados = $clientes->filter(function ($cliente) {
                return $cliente->apartados->isNotEmpty();
            })->values();

            if ($clientesConApartados->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron apartados activos para clientes con ese nombre'
                ], 404);
            }

            // Formatear respuesta
            $resultado = $clientesConApartados->map(function ($cliente) {
                return [
                    'cliente_id' => $cliente->id,
                    'nombre_cliente' => $cliente->nombre,
                    'telefono_cliente' => $cliente->telefono,
                    'apartados' => $cliente->apartados->map(function ($apartado) {
                        return $this->formatearApartado($apartado);
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'total_clientes' => $clientesConApartados->count(),
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar apartados por cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar un nuevo abono
     * POST /api/v1/movil/abonos
     */
    public function registrarAbono(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'apartado_id' => 'required|exists:apartados,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:efectivo,transferencia,tarjeta',
            'comprobante' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:500',
            'usuario' => 'required|string|max:100'
        ], [
            'apartado_id.required' => 'El ID del apartado es requerido',
            'apartado_id.exists' => 'El apartado no existe',
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser un número',
            'monto.min' => 'El monto debe ser mayor a 0',
            'metodo_pago.required' => 'El método de pago es requerido',
            'metodo_pago.in' => 'El método de pago debe ser: efectivo, transferencia o tarjeta',
            'usuario.required' => 'El usuario es requerido'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Obtener el apartado
            $apartado = Apartado::whereKey($request->apartado_id)->lockForUpdate()->firstOrFail();

            // Verificar estado del apartado
            if ($apartado->estado === 'cancelado') {
                throw new \DomainException('No se puede abonar a un apartado cancelado');
            }

            if ($apartado->estado === 'liquidado') {
                throw new \DomainException('Este apartado ya está liquidado');
            }

            // Verificar que el monto no exceda el saldo pendiente
            if ($request->monto > $apartado->saldo_pendiente) {
                throw new \DomainException('El monto del abono excede el saldo pendiente');
            }

            // Guardar saldo anterior
            $saldoAnterior = $apartado->saldo_pendiente;

            // Crear el abono
            $abono = Abono::create([
                'apartado_id' => $apartado->id,
                'fecha_abono' => now(),
                'monto' => $request->monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoAnterior - $request->monto,
                'metodo_pago' => $request->metodo_pago,
                'comprobante' => $request->comprobante,
                'observaciones' => $request->observaciones,
                'usuario' => $request->usuario
            ]);

            // Actualizar saldo del apartado
            $apartado->saldo_pendiente = $saldoAnterior - $request->monto;

            // Si el saldo llega a 0, liquidar automáticamente
            if ($apartado->saldo_pendiente == 0) {
                // 1. Crear la venta
                $venta = \App\Models\Venta::create([
                    'cliente_id' => $apartado->cliente_id,
                    'apartado_id' => $apartado->id,
                    'fecha_venta' => now(),
                    'tipo_pago' => 'contado',
                    'subtotal' => $apartado->monto_total,
                    'descuento_global' => 0,
                    'total' => $apartado->monto_total,
                    'estado' => 'completada',
                    'tiene_envio' => false,
                    'costo_envio' => 0,
                    'observaciones' => "Venta generada automáticamente al liquidar el apartado {$apartado->folio} desde app móvil",
                    'usuario' => $request->usuario,
                    'tipo_inventario' => $apartado->tipo_inventario,
                    'subinventario_id' => $apartado->subinventario_id,
                    'es_a_plazos' => false,
                    'total_pagado' => $apartado->monto_total,
                    'estado_pago' => 'completado',
                ]);

                // 2. Crear movimientos de salida
                foreach ($apartado->detalles as $detalle) {
                    \App\Models\Movimiento::create([
                        'libro_id' => $detalle->libro_id,
                        'venta_id' => $venta->id,
                        'tipo_movimiento' => 'salida',
                        'tipo_salida' => 'venta',
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'descuento' => $detalle->descuento,
                        'fecha' => now(),
                        'observaciones' => "Venta del apartado {$apartado->folio} (Móvil)",
                        'usuario' => $request->usuario,
                    ]);
                }

                // 3. Consumir el stock usando el servicio
                app(\App\Services\InventoryStockService::class)->consumeApartado($apartado);

                // 4. Cambiar estado del apartado y asociar venta
                $apartado->estado = 'liquidado';
                $apartado->venta_id = $venta->id;
            }

            $apartado->save();

            // Recargar relaciones
            $apartado->load(['cliente', 'detalles.libro', 'abonos']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Abono registrado exitosamente',
                'data' => [
                    'abono' => [
                        'id' => $abono->id,
                        'fecha_abono' => $abono->fecha_abono->format('Y-m-d'),
                        'monto' => $abono->monto,
                        'saldo_anterior' => $abono->saldo_anterior,
                        'saldo_nuevo' => $abono->saldo_nuevo,
                        'metodo_pago' => $abono->metodo_pago,
                        'comprobante' => $abono->comprobante,
                        'observaciones' => $abono->observaciones
                    ],
                    'apartado' => $this->formatearApartado($apartado)
                ]
            ], 201);
        } catch (\DomainException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el abono',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de abonos de un apartado
     * GET /api/v1/movil/apartados/{apartado_id}/abonos
     */
    public function historialAbonos($apartado_id)
    {
        try {
            $apartado = Apartado::with(['cliente', 'abonos'])
                ->findOrFail($apartado_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'apartado' => [
                        'id' => $apartado->id,
                        'folio' => $apartado->folio,
                        'cliente' => $apartado->cliente->nombre,
                        'monto_total' => $apartado->monto_total,
                        'saldo_pendiente' => $apartado->saldo_pendiente,
                        'estado' => $apartado->estado
                    ],
                    'abonos' => $apartado->abonos->map(function ($abono) {
                        return [
                            'id' => $abono->id,
                            'fecha_abono' => $abono->fecha_abono->format('Y-m-d'),
                            'monto' => $abono->monto,
                            'saldo_anterior' => $abono->saldo_anterior,
                            'saldo_nuevo' => $abono->saldo_nuevo,
                            'metodo_pago' => $abono->metodo_pago,
                            'metodo_pago_label' => $abono->getMetodoPagoLabel(),
                            'comprobante' => $abono->comprobante,
                            'observaciones' => $abono->observaciones,
                            'usuario' => $abono->usuario
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de abonos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo cliente desde la app móvil
     * POST /api/v1/movil/clientes
     */
    public function crearCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20'
        ], [
            'nombre.required' => 'El nombre del cliente es requerido',
            'nombre.string' => 'El nombre debe ser un texto válido',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'telefono.string' => 'El teléfono debe ser un texto válido',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar si ya existe un cliente con el mismo nombre y teléfono
            $clienteExistente = null;
            
            if (!empty($request->telefono)) {
                // Si hay teléfono, buscar por nombre Y teléfono
                $clienteExistente = Cliente::where('nombre', $request->nombre)
                    ->where('telefono', $request->telefono)
                    ->first();
            } else {
                // Si no hay teléfono, buscar solo por nombre
                $clienteExistente = Cliente::where('nombre', $request->nombre)
                    ->whereNull('telefono')
                    ->first();
            }

            if ($clienteExistente) {
                return response()->json([
                    'success' => true,
                    'message' => 'El cliente ya existe',
                    'data' => [
                        'id' => $clienteExistente->id,
                        'nombre' => $clienteExistente->nombre,
                        'telefono' => $clienteExistente->telefono,
                        'created_at' => $clienteExistente->created_at->format('Y-m-d H:i:s'),
                        'es_nuevo' => false
                    ]
                ], 200);
            }

            // Crear el nuevo cliente
            $cliente = Cliente::create([
                'nombre' => $request->nombre,
                'telefono' => $request->telefono
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'data' => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'telefono' => $cliente->telefono,
                    'created_at' => $cliente->created_at->format('Y-m-d H:i:s'),
                    'es_nuevo' => true
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todos los clientes
     * GET /api/v1/movil/clientes
     */
    public function listarClientes(Request $request)
    {
        try {
            $busqueda = $request->query('busqueda');
            $limite = $request->query('limite', 100);
            
            $query = Cliente::query();
            
            // Si hay búsqueda, filtrar por nombre o teléfono
            if (!empty($busqueda)) {
                $query->where(function($q) use ($busqueda) {
                    $q->where('nombre', 'like', '%' . $busqueda . '%')
                      ->orWhere('telefono', 'like', '%' . $busqueda . '%');
                });
            }
            
            $clientes = $query->orderBy('nombre', 'asc')
                ->limit($limite)
                ->get();
            
            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron clientes'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'total' => $clientes->count(),
                'data' => $clientes->map(function ($cliente) {
                    return [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre,
                        'telefono' => $cliente->telefono,
                        'total_apartados' => $cliente->apartados()->count(),
                        'apartados_activos' => $cliente->apartados()
                            ->whereIn('estado', ['activo', 'vencido'])
                            ->count()
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear datos del apartado para la respuesta
     */
    private function formatearApartado($apartado)
    {
        return [
            'id' => $apartado->id,
            'folio' => $apartado->folio,
            'cliente' => [
                'id' => $apartado->cliente->id,
                'nombre' => $apartado->cliente->nombre,
                'telefono' => $apartado->cliente->telefono
            ],
            'fecha_apartado' => $apartado->fecha_apartado ? $apartado->fecha_apartado->format('Y-m-d') : null,
            'fecha_limite' => $apartado->fecha_limite ? $apartado->fecha_limite->format('Y-m-d') : null,
            'monto_total' => $apartado->monto_total,
            'enganche' => $apartado->enganche,
            'saldo_pendiente' => $apartado->saldo_pendiente,
            'total_pagado' => $apartado->total_pagado,
            'porcentaje_pagado' => $apartado->porcentaje_pagado,
            'estado' => $apartado->estado,
            'observaciones' => $apartado->observaciones,
            'libros' => $apartado->detalles->map(function ($detalle) {
                return [
                    'codigo' => $detalle->libro->codigo ?? 'N/A',
                    'titulo' => $detalle->libro->titulo ?? 'Sin título',
                    'precio_unitario' => $detalle->precio_unitario,
                    'cantidad' => $detalle->cantidad,
                    'subtotal' => $detalle->subtotal
                ];
            }),
            'total_abonos' => $apartado->abonos->count(),
            'ultimo_abono' => $apartado->abonos->isNotEmpty() 
                ? [
                    'fecha' => $apartado->abonos->last()->fecha_abono->format('Y-m-d'),
                    'monto' => $apartado->abonos->last()->monto
                ]
                : null
        ];
    }
}
