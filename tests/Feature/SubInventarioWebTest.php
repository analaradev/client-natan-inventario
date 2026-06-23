<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Libro;
use App\Models\SubInventario;
use App\Models\Movimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SubInventarioWebTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return [
            'codCongregante' => 'test-admin',
            'username' => 'test',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]],
        ];
    }

    public function test_admin_can_view_subinventarios_index()
    {
        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Subinventario Web Test',
            'estado' => 'activo',
            'usuario' => 'Admin'
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('subinventarios.index'));

        $response->assertStatus(200);
        $response->assertViewHas('subinventarios');
        $response->assertSee('Subinventario Web Test');
    }

    public function test_admin_can_view_create_form()
    {
        $libro = Libro::create([
            'nombre' => 'Libro General',
            'precio' => 100.00,
            'stock' => 10,
            'stock_subinventario' => 0
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('subinventarios.create'));

        $response->assertStatus(200);
        $response->assertSee('Libro General');
    }

    public function test_admin_can_view_edit_form_with_existing_books()
    {
        $libro = Libro::create([
            'nombre' => 'Libro visible al editar',
            'precio' => 100.00,
            'stock' => 7,
            'stock_subinventario' => 3,
        ]);
        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Subinventario editable',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $sub->libros()->attach($libro->id, ['cantidad' => 3]);

        $response = $this->withSession($this->adminSession())
            ->get(route('subinventarios.edit', $sub));

        $response->assertOk();
        $response->assertSee('Libro visible al editar');
        $response->assertSee('Selecciona el libro desde la lista de resultados antes de guardar.');
    }

    public function test_subinventory_details_show_the_book_id()
    {
        $libro = Libro::create([
            'nombre' => 'Libro con ID visible',
            'precio' => 100.00,
            'stock' => 7,
            'stock_subinventario' => 3,
        ]);
        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Detalle con ID',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $sub->libros()->attach($libro->id, ['cantidad' => 3]);

        $response = $this->withSession($this->adminSession())
            ->get(route('subinventarios.show', $sub));

        $response->assertOk();
        $response->assertSee('ID');
        $response->assertSee((string) $libro->id);
        $response->assertSee('Libro con ID visible');
    }

    public function test_admin_can_store_new_subinventory()
    {
        $libro = Libro::create([
            'nombre' => 'Libro para Sub',
            'precio' => 150.00,
            'stock' => 10,
            'stock_subinventario' => 0
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('subinventarios.store'), [
                'fecha_subinventario' => '2026-06-23',
                'descripcion' => 'Nuevo Subinventario Guardado',
                'libros' => [
                    ['libro_id' => $libro->id, 'cantidad' => 3]
                ]
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subinventarios', [
            'descripcion' => 'Nuevo Subinventario Guardado',
            'estado' => 'activo'
        ]);

        // Verificar descuento de stock en bodega
        $this->assertSame(7, $libro->fresh()->stock);
        $this->assertSame(3, $libro->fresh()->stock_subinventario);
    }

    public function test_admin_cannot_store_with_insufficient_stock()
    {
        $libro = Libro::create([
            'nombre' => 'Libro sin Stock',
            'precio' => 150.00,
            'stock' => 2,
            'stock_subinventario' => 0
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('subinventarios.store'), [
                'fecha_subinventario' => '2026-06-23',
                'descripcion' => 'Falla por Stock',
                'libros' => [
                    ['libro_id' => $libro->id, 'cantidad' => 5]
                ]
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('subinventarios', [
            'descripcion' => 'Falla por Stock'
        ]);
    }

    public function test_admin_can_update_active_subinventory()
    {
        $libro = Libro::create([
            'nombre' => 'Libro de Edicion',
            'precio' => 50.00,
            'stock' => 10,
            'stock_subinventario' => 4
        ]);

        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Sub a Editar',
            'estado' => 'activo',
            'usuario' => 'Admin'
        ]);
        $sub->libros()->attach($libro->id, ['cantidad' => 4]);

        $response = $this->withSession($this->adminSession())
            ->put(route('subinventarios.update', $sub), [
                'fecha_subinventario' => '2026-06-23',
                'descripcion' => 'Sub Editado',
                'libros' => [
                    ['libro_id' => $libro->id, 'cantidad' => 6]
                ]
            ]);

        $response->assertRedirect(route('subinventarios.show', $sub));
        $this->assertDatabaseHas('subinventarios', [
            'id' => $sub->id,
            'descripcion' => 'Sub Editado'
        ]);

        // Verificamos que el stock se ajustó correctamente (anterior 4, nuevo 6, diferencia 2 restada de stock general)
        $this->assertSame(8, $libro->fresh()->stock);
        $this->assertSame(6, $libro->fresh()->stock_subinventario);
    }

    public function test_admin_can_add_a_general_inventory_book_while_editing_subinventory()
    {
        $libroExistente = Libro::create([
            'nombre' => 'Libro ya asignado',
            'precio' => 50.00,
            'stock' => 6,
            'stock_subinventario' => 4,
        ]);
        $libroNuevo = Libro::create([
            'nombre' => 'Libro disponible en general',
            'precio' => 80.00,
            'stock' => 10,
            'stock_subinventario' => 0,
        ]);

        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Sub con libro nuevo',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $sub->libros()->attach($libroExistente->id, ['cantidad' => 4]);

        $response = $this->withSession($this->adminSession())
            ->put(route('subinventarios.update', $sub), [
                'fecha_subinventario' => '2026-06-23',
                'descripcion' => 'Sub con libro nuevo',
                'libros' => [
                    ['libro_id' => $libroExistente->id, 'cantidad' => 4],
                    ['libro_id' => $libroNuevo->id, 'cantidad' => 3],
                ],
            ]);

        $response->assertRedirect(route('subinventarios.show', $sub));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $sub->id,
            'libro_id' => $libroExistente->id,
            'cantidad' => 4,
        ]);
        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $sub->id,
            'libro_id' => $libroNuevo->id,
            'cantidad' => 3,
        ]);
        $this->assertSame(7, $libroNuevo->fresh()->stock);
        $this->assertSame(3, $libroNuevo->fresh()->stock_subinventario);
    }

    public function test_admin_can_destroy_active_subinventory_and_restore_stock()
    {
        $libro = Libro::create([
            'nombre' => 'Libro a Liberar',
            'precio' => 50.00,
            'stock' => 5,
            'stock_subinventario' => 5
        ]);

        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Sub a Eliminar',
            'estado' => 'activo',
            'usuario' => 'Admin'
        ]);
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->delete(route('subinventarios.destroy', $sub));

        $response->assertRedirect(route('subinventarios.index'));
        $this->assertDatabaseMissing('subinventarios', [
            'id' => $sub->id
        ]);

        // Stock general debería volver a 10 y stock_subinventario a 0
        $this->assertSame(10, $libro->fresh()->stock);
        $this->assertSame(0, $libro->fresh()->stock_subinventario);
    }

    public function test_cannot_destroy_completed_subinventory()
    {
        $sub = SubInventario::create([
            'fecha_subinventario' => '2026-06-23',
            'descripcion' => 'Sub Completado',
            'estado' => 'completado',
            'usuario' => 'Admin'
        ]);

        $response = $this->withSession($this->adminSession())
            ->delete(route('subinventarios.destroy', $sub));

        $response->assertSessionHas('error', 'No se pueden eliminar sub-inventarios completados');
        $this->assertDatabaseHas('subinventarios', [
            'id' => $sub->id,
            'estado' => 'completado'
        ]);
    }
}
