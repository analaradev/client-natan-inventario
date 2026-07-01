@extends('layouts.app')

@section('title', 'Registrar Movimiento')

@section('page-title', 'Registrar Movimiento de Inventario')
@section('page-description', 'Registra una entrada o salida de libros')

@section('content')
<x-page-layout 
    title="Registrar Movimiento"
    description="Registra una entrada o salida de libros del inventario"
    button-text="Volver a Movimientos"
    button-icon="fas fa-arrow-left"
    :button-route="route('movimientos.index')"
>
    <x-card>
        <x-movimiento-form 
            :action="route('movimientos.store')"
            :libros="$libros"
            :subinventarios="$subinventarios"
            submitText="Registrar Movimiento"
        />
    </x-card>
</x-page-layout>
@endsection
