<?php

namespace Tests\Feature\Http\Controllers;

use App\Repositories\ImageJsonRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use RuntimeException;
use Tests\GeneraImagenes;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class ImageComparisonControllerTest extends TestCase
{
    use GeneraImagenes;

    private const ARCHIVO_JSON = 'turismo/images.json';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function foto(bool $descendente = true): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto.png', $this->pngDegradado($descendente));
    }

    /**
     * Crea el JSON con San Martín de los Andes y dos fotos, la primera con el hash indicado.
     */
    private function escribirJson(string $hashHex): string
    {
        $json = json_encode(['destinos' => [[
            'id' => 'san-martin-de-los-andes',
            'nombre' => 'San Martín de los Andes',
            'departamento' => 'Lácar',
            'descripcion' => 'Ciudad cordillerana.',
            'imagenes' => [
                ['archivo' => 'destinos/a.jpg', 'titulo' => 'Muelle del lago', 'hash_hex' => $hashHex],
                ['archivo' => 'destinos/b.jpg', 'titulo' => 'Cerro Chapelco', 'hash_hex' => self::HASH_ASCENDENTE],
            ],
        ]]]);

        Storage::disk('local')->put(self::ARCHIVO_JSON, $json);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function destinoEnJson(string $id): array
    {
        return app(ImageJsonRepository::class)->buscarDestino($id);
    }

    /**
     * Deja una foto sin coincidencia pendiente de registro. Devuelve su ruta temporal.
     */
    private function compararSinCoincidencia(): string
    {
        $this->post(route('image.compare'), ['image' => $this->foto()])->assertRedirect(route('image.result'));

        return session('comparacion.temporal');
    }

    public function test_home_page_renders_the_upload_form_in_spanish(): void
    {
        $this->get(route('image.index'))->assertOk()->assertSee('Foto del lugar');
    }

    public function test_comparing_does_not_write_the_json_or_publish_the_photo(): void
    {
        $json = $this->escribirJson('fffffffffffff000');

        $temporal = $this->compararSinCoincidencia();

        $this->assertSame($json, Storage::disk('local')->get(self::ARCHIVO_JSON));
        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($temporal);
    }

    public function test_real_image_gets_the_expected_hash(): void
    {
        $this->compararSinCoincidencia();

        $this->assertSame(self::HASH_DESCENDENTE, session('comparacion.hash_hex'));
    }

    public function test_distance_of_5_bits_shows_the_destination_and_its_whole_gallery(): void
    {
        $this->escribirJson('ffffffffffffffe0');

        $this->post(route('image.compare'), ['image' => $this->foto()]);

        $this->get(route('image.result'))
            ->assertOk()
            ->assertSee('¡Coincidencia encontrada!')
            ->assertSee('San Martín de los Andes')
            ->assertSee('ffffffffffffffe0')
            ->assertSee('5 de 64 bits')
            ->assertSee('92,19%')
            ->assertSee('Muelle del lago')
            ->assertSee('Cerro Chapelco')
            ->assertDontSee('Crear lugar y registrar');
    }

    public function test_distance_of_6_bits_shows_the_registration_forms(): void
    {
        $this->escribirJson('ffffffffffffffc0');

        $this->post(route('image.compare'), ['image' => $this->foto()]);

        $this->get(route('image.result'))
            ->assertOk()
            ->assertSee('No encontramos ninguna coincidencia')
            ->assertSee('6 de 64 bits')
            ->assertSee('Registrar en este destino')
            ->assertSee('Crear lugar y registrar')
            ->assertDontSee('¡Coincidencia encontrada!');
    }

    public function test_without_destinations_only_the_new_place_form_is_shown(): void
    {
        $this->compararSinCoincidencia();

        $this->get(route('image.result'))
            ->assertOk()
            ->assertSee('Crear lugar y registrar')
            ->assertDontSee('Registrar en este destino');
    }

    public function test_rejects_a_non_image_file_with_a_spanish_message(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('notas.txt', 'esto no es una imagen');

        $this->post(route('image.compare'), ['image' => $archivo])
            ->assertSessionHasErrors(['image' => 'El archivo debe ser una imagen.']);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_rejects_a_photo_larger_than_5_mb(): void
    {
        $archivo = UploadedFile::fake()->image('grande.png')->size(5121);

        $this->post(route('image.compare'), ['image' => $archivo])
            ->assertSessionHasErrors(['image' => 'La foto no puede superar los 5 MB.']);
    }

    public function test_undecodable_image_returns_an_error_and_leaves_no_temporary_file(): void
    {
        $encabezadoPng = substr($this->pngDegradado(), 0, 40);
        $archivo = UploadedFile::fake()->createWithContent('rota.png', $encabezadoPng);

        $this->post(route('image.compare'), ['image' => $archivo])->assertSessionHasErrors('image');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_new_comparison_deletes_the_previous_temporary_photo(): void
    {
        $anterior = $this->compararSinCoincidencia();

        $nueva = $this->compararSinCoincidencia();

        Storage::disk('local')->assertMissing($anterior);
        Storage::disk('local')->assertExists($nueva);
    }

    public function test_a_comparison_purges_abandoned_temporary_photos(): void
    {
        Storage::disk('local')->put('temporales/abandonada.png', 'x');
        Storage::disk('local')->put('temporales/reciente.png', 'x');
        touch(Storage::disk('local')->path('temporales/abandonada.png'), now()->subHours(25)->getTimestamp());

        $this->compararSinCoincidencia();

        Storage::disk('local')->assertMissing('temporales/abandonada.png');
        Storage::disk('local')->assertExists('temporales/reciente.png');
    }

    public function test_result_without_a_comparison_redirects_home(): void
    {
        $this->get(route('image.result'))
            ->assertRedirect(route('image.index'))
            ->assertSessionHas('error');
    }

    public function test_result_handles_a_destination_removed_after_comparing(): void
    {
        $this->escribirJson(self::HASH_DESCENDENTE);
        $this->post(route('image.compare'), ['image' => $this->foto()]);
        $temporal = session('comparacion.temporal');

        Storage::disk('local')->put(self::ARCHIVO_JSON, '{"destinos": []}');

        $this->get(route('image.result'))->assertRedirect(route('image.index'));
        Storage::disk('local')->assertMissing($temporal);
    }

    public function test_temporary_image_is_served_from_the_session_photo(): void
    {
        $this->compararSinCoincidencia();

        $this->get(route('image.temporary'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_temporary_image_returns_404_without_a_comparison(): void
    {
        $this->get(route('image.temporary'))->assertNotFound();
    }

    public function test_registers_the_photo_in_an_existing_destination(): void
    {
        $this->escribirJson('fffffffffffff000');
        $temporal = $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'existente',
            'destino_id' => 'san-martin-de-los-andes',
            'titulo' => 'Atardecer',
        ])->assertRedirect(route('image.index'))->assertSessionHas('exito');

        $imagen = $this->destinoEnJson('san-martin-de-los-andes')['imagenes'][2];

        $this->assertSame('Atardecer', $imagen['titulo']);
        $this->assertSame(self::HASH_DESCENDENTE, $imagen['hash_hex']);
        Storage::disk('public')->assertExists($imagen['archivo']);
        Storage::disk('local')->assertMissing($temporal);
        $this->assertNull(session('comparacion'));
    }

    public function test_registers_the_photo_in_a_new_destination(): void
    {
        $temporal = $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'Villa Pehuenia',
            'departamento' => 'Aluminé',
            'descripcion' => 'Aldea de montaña.',
            'titulo' => 'Lago Aluminé',
        ])->assertRedirect(route('image.index'));

        $destino = $this->destinoEnJson('villa-pehuenia');

        $this->assertSame('Aluminé', $destino['departamento']);
        $this->assertSame('Lago Aluminé', $destino['imagenes'][0]['titulo']);
        Storage::disk('public')->assertExists($destino['imagenes'][0]['archivo']);
        Storage::disk('local')->assertMissing($temporal);
    }

    public function test_a_registered_photo_matches_when_uploaded_again(): void
    {
        $this->compararSinCoincidencia();
        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'Caviahue',
            'departamento' => 'Ñorquín',
            'descripcion' => 'Villa termal.',
            'titulo' => 'Termas',
        ]);

        $this->post(route('image.compare'), ['image' => $this->foto()]);

        $this->get(route('image.result'))
            ->assertSee('¡Coincidencia encontrada!')
            ->assertSee('0 de 64 bits')
            ->assertSee('100,00%');
    }

    public function test_form_fields_cannot_replace_the_hash_or_the_session_photo(): void
    {
        Storage::disk('local')->put('secreto.txt', 'privado');
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'Caviahue',
            'departamento' => 'Ñorquín',
            'descripcion' => 'Villa termal.',
            'titulo' => 'Termas',
            'hash_hex' => self::HASH_ASCENDENTE,
            'temporal' => 'secreto.txt',
        ]);

        $imagen = $this->destinoEnJson('caviahue')['imagenes'][0];

        $this->assertSame(self::HASH_DESCENDENTE, $imagen['hash_hex']);
        Storage::disk('public')->assertMissing('destinos/secreto.txt');
        Storage::disk('local')->assertExists('secreto.txt');
    }

    public function test_invalid_form_returns_to_the_result_with_spanish_errors_and_keeps_the_photo(): void
    {
        $temporal = $this->compararSinCoincidencia();

        $this->from(route('image.result'))
            ->followingRedirects()
            ->post(route('image.register'), ['modo' => 'nuevo', 'titulo' => 'Sin datos'])
            ->assertOk()
            ->assertSee('Escribí el nombre del lugar.')
            ->assertSee('Escribí el departamento.');

        Storage::disk('local')->assertExists($temporal);
        Storage::disk('local')->assertMissing(self::ARCHIVO_JSON);
    }

    public function test_rejects_a_new_destination_with_an_existing_name(): void
    {
        $json = $this->escribirJson('fffffffffffff000');
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'San Martin de los Andes',
            'departamento' => 'Lácar',
            'descripcion' => 'Duplicado.',
            'titulo' => 'Foto',
        ])->assertSessionHasErrors('nombre');

        $this->assertSame($json, Storage::disk('local')->get(self::ARCHIVO_JSON));
    }

    public function test_rejects_a_new_destination_whose_name_has_no_letters_or_numbers(): void
    {
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => '¡¡¡',
            'departamento' => 'Lácar',
            'descripcion' => 'Sin nombre válido.',
            'titulo' => 'Foto',
        ])->assertSessionHasErrors('nombre');
    }

    public function test_badly_encoded_text_is_cleaned_before_saving(): void
    {
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => "San Mart\xEDn",
            'departamento' => 'Lácar',
            'descripcion' => 'Texto en Latin-1.',
            'titulo' => 'Foto',
        ])->assertRedirect(route('image.index'))->assertSessionHas('exito');

        $nombre = app(ImageJsonRepository::class)->todosLosDestinos()[0]['nombre'];

        $this->assertTrue(mb_check_encoding($nombre, 'UTF-8'));
        $this->assertStringStartsWith('San Mart', $nombre);
    }

    public function test_badly_encoded_text_is_not_flashed_to_the_session_after_a_validation_error(): void
    {
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), ['modo' => 'nuevo', 'nombre' => "San Mart\xEDn", 'titulo' => 'Foto'])
            ->assertSessionHasErrors('departamento');

        $this->assertNotFalse(json_encode(session()->all()));
    }

    public function test_rejects_a_destination_that_does_not_exist(): void
    {
        $this->escribirJson('fffffffffffff000');
        $this->compararSinCoincidencia();

        $this->post(route('image.register'), [
            'modo' => 'existente',
            'destino_id' => 'no-existe',
            'titulo' => 'Foto',
        ])->assertSessionHasErrors(['destino_id' => 'El destino elegido no existe.']);
    }

    public function test_a_matched_photo_cannot_be_registered(): void
    {
        $json = $this->escribirJson(self::HASH_DESCENDENTE);
        $this->post(route('image.compare'), ['image' => $this->foto()]);

        $this->post(route('image.register'), [
            'modo' => 'existente',
            'destino_id' => 'san-martin-de-los-andes',
            'titulo' => 'Duplicada',
        ])->assertRedirect(route('image.index'))->assertSessionHas('error');

        $this->assertSame($json, Storage::disk('local')->get(self::ARCHIVO_JSON));
    }

    public function test_registering_without_a_comparison_redirects_home(): void
    {
        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'Caviahue',
            'departamento' => 'Ñorquín',
            'descripcion' => 'Villa termal.',
            'titulo' => 'Termas',
        ])->assertRedirect(route('image.index'))->assertSessionHas('error');

        Storage::disk('local')->assertMissing(self::ARCHIVO_JSON);
    }

    public function test_registering_after_the_temporary_photo_expired_asks_to_upload_again(): void
    {
        $temporal = $this->compararSinCoincidencia();
        Storage::disk('local')->delete($temporal);

        $this->post(route('image.register'), [
            'modo' => 'nuevo',
            'nombre' => 'Caviahue',
            'departamento' => 'Ñorquín',
            'descripcion' => 'Villa termal.',
            'titulo' => 'Termas',
        ])->assertRedirect(route('image.index'))->assertSessionHas('error');

        Storage::disk('local')->assertMissing(self::ARCHIVO_JSON);
    }

    public function test_failed_json_write_removes_the_public_copy_and_keeps_the_temporary_photo(): void
    {
        $temporal = $this->compararSinCoincidencia();

        $this->mock(ImageJsonRepository::class, function ($repositorio): void {
            $repositorio->shouldReceive('todosLosDestinos')->andReturn([['id' => 'caviahue']]);
            $repositorio->shouldReceive('armarImagen')->andReturn([]);
            $repositorio->shouldReceive('agregarImagen')->andThrow(new RuntimeException('Disco lleno'));
        });

        $this->post(route('image.register'), [
            'modo' => 'existente',
            'destino_id' => 'caviahue',
            'titulo' => 'Termas',
        ])->assertServerError();

        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($temporal);
    }

    public function test_discarding_deletes_the_temporary_photo_and_leaves_the_json_intact(): void
    {
        $json = $this->escribirJson('fffffffffffff000');
        $temporal = $this->compararSinCoincidencia();

        $this->post(route('image.discard'))->assertRedirect(route('image.index'));

        Storage::disk('local')->assertMissing($temporal);
        $this->assertSame($json, Storage::disk('local')->get(self::ARCHIVO_JSON));
        $this->assertNull(session('comparacion'));
    }

    public function test_oversized_request_renders_a_spanish_413_page(): void
    {
        $this->call('POST', route('image.compare'), server: ['CONTENT_LENGTH' => PHP_INT_MAX])
            ->assertStatus(413)
            ->assertSee('La foto es demasiado grande');
    }
}
