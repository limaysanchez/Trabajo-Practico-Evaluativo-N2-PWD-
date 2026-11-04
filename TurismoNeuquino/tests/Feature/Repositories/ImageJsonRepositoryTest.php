<?php

namespace Tests\Feature\Repositories;

use App\Repositories\ImageJsonRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImageJsonRepositoryTest extends TestCase
{
    private const ARCHIVO = 'turismo/images.json';

    private ImageJsonRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->repositorio = app(ImageJsonRepository::class);
    }

    /**
     * @return array<string, string>
     */
    private function imagen(string $titulo = 'Lago Lácar'): array
    {
        return $this->repositorio->armarImagen('destinos/foto.jpg', $titulo, 'ff00f0f0f0f0ff00');
    }

    /**
     * @return array<string, string>
     */
    private function datosDestino(string $nombre = 'San Martín de los Andes'): array
    {
        return ['nombre' => $nombre, 'departamento' => 'Lácar', 'descripcion' => 'Ciudad cordillerana.'];
    }

    public function test_missing_file_returns_no_destinations(): void
    {
        $this->assertSame([], $this->repositorio->todosLosDestinos());
    }

    public function test_creates_a_destination_with_its_image_and_generated_id(): void
    {
        $id = $this->repositorio->crearDestino($this->datosDestino(), $this->imagen());

        $destino = $this->repositorio->buscarDestino($id);

        $this->assertSame('san-martin-de-los-andes', $id);
        $this->assertSame('San Martín de los Andes', $destino['nombre']);
        $this->assertSame('Lago Lácar', $destino['imagenes'][0]['titulo']);
    }

    public function test_written_json_keeps_accents_readable(): void
    {
        $this->repositorio->crearDestino($this->datosDestino(), $this->imagen());

        $this->assertStringContainsString('San Martín de los Andes', Storage::disk('local')->get(self::ARCHIVO));
    }

    public function test_adds_an_image_without_losing_the_previous_ones(): void
    {
        $id = $this->repositorio->crearDestino($this->datosDestino(), $this->imagen('Primera'));

        $this->repositorio->agregarImagen($id, $this->imagen('Segunda'));

        $titulos = array_column($this->repositorio->buscarDestino($id)['imagenes'], 'titulo');
        $this->assertSame(['Primera', 'Segunda'], $titulos);
    }

    public function test_lists_images_of_every_destination_with_their_destination_id(): void
    {
        $this->repositorio->crearDestino($this->datosDestino('Caviahue'), $this->imagen('Termas'));
        $this->repositorio->crearDestino($this->datosDestino('Villa Pehuenia'), $this->imagen('Lago'));

        $imagenes = $this->repositorio->todasLasImagenes();

        $this->assertSame(['caviahue', 'villa-pehuenia'], array_column($imagenes, 'destino_id'));
    }

    public function test_rejects_adding_an_image_to_a_missing_destination(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repositorio->agregarImagen('no-existe', $this->imagen());
    }

    public function test_rejects_a_duplicate_destination_and_keeps_the_data(): void
    {
        $this->repositorio->crearDestino($this->datosDestino(), $this->imagen());

        try {
            $this->repositorio->crearDestino($this->datosDestino('San Martin de los Andes'), $this->imagen('Otra'));
            $this->fail('Debía rechazar el destino duplicado.');
        } catch (RuntimeException) {
            $this->assertCount(1, $this->repositorio->todosLosDestinos());
        }
    }

    public function test_corrupt_json_throws_and_is_never_overwritten(): void
    {
        Storage::disk('local')->put(self::ARCHIVO, '{"destinos": [');

        try {
            $this->repositorio->crearDestino($this->datosDestino(), $this->imagen());
            $this->fail('Debía rechazar el JSON dañado.');
        } catch (RuntimeException $excepcion) {
            $this->assertStringContainsString('dañado', $excepcion->getMessage());
            $this->assertSame('{"destinos": [', Storage::disk('local')->get(self::ARCHIVO));
        }
    }

    public function test_rejects_a_destination_without_required_keys(): void
    {
        Storage::disk('local')->put(self::ARCHIVO, '{"destinos": [{"nombre": "Sin id"}]}');

        $this->expectException(RuntimeException::class);

        $this->repositorio->todosLosDestinos();
    }

    public function test_rejects_an_image_without_a_hash(): void
    {
        Storage::disk('local')->put(self::ARCHIVO, json_encode(['destinos' => [[
            'id' => 'caviahue',
            'nombre' => 'Caviahue',
            'imagenes' => [['archivo' => 'destinos/a.jpg', 'hash' => 'ff00f0f0f0f0ff00']],
        ]]]));

        $this->expectExceptionMessage('debe tener "archivo" y "hash_hex"');

        $this->repositorio->todosLosDestinos();
    }

    public function test_reads_a_json_saved_with_a_windows_bom(): void
    {
        Storage::disk('local')->put(self::ARCHIVO, "\xEF\xBB\xBF".'{"destinos": []}');

        $this->assertSame([], $this->repositorio->todosLosDestinos());
    }
}
