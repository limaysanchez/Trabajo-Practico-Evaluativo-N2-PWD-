<?php

namespace Tests\Feature\Console\Commands;

use App\Repositories\ImageJsonRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\GeneraImagenes;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class SembrarDestinosTest extends TestCase
{
    use GeneraImagenes;

    private string $rutaSemillas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->rutaSemillas = sys_get_temp_dir().'/semillas_'.uniqid();
        File::ensureDirectoryExists($this->rutaSemillas.'/fotos');
        config(['turismo.ruta_semillas' => $this->rutaSemillas]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->rutaSemillas);

        parent::tearDown();
    }

    private function escribirSemillas(string $nombre = 'Caviahue'): void
    {
        File::put($this->rutaSemillas.'/destinos.json', json_encode(['destinos' => [[
            'nombre' => $nombre,
            'departamento' => 'Ñorquín',
            'descripcion' => 'Villa termal.',
            'imagenes' => [['archivo' => 'termas.png', 'titulo' => 'Termas']],
        ]]]));
    }

    public function test_loads_destinations_with_real_hashes_and_copies_the_photos(): void
    {
        $this->escribirSemillas();
        File::put($this->rutaSemillas.'/fotos/termas.png', $this->pngDegradado());

        $this->artisan('turismo:sembrar')->assertSuccessful();

        $imagen = app(ImageJsonRepository::class)->buscarDestino('caviahue')['imagenes'][0];

        $this->assertSame(self::HASH_DESCENDENTE, $imagen['hash_hex']);
        $this->assertSame('destinos/termas.png', $imagen['archivo']);
        Storage::disk('public')->assertExists('destinos/termas.png');
    }

    public function test_missing_photo_fails_without_writing_the_json(): void
    {
        $this->escribirSemillas();

        $this->artisan('turismo:sembrar')
            ->expectsOutputToContain('termas.png')
            ->assertFailed();

        Storage::disk('local')->assertMissing('turismo/images.json');
    }

    public function test_does_not_replace_existing_destinations_without_force(): void
    {
        File::put($this->rutaSemillas.'/fotos/termas.png', $this->pngDegradado());
        $this->escribirSemillas();
        $this->artisan('turismo:sembrar');

        $this->escribirSemillas('Villa Pehuenia');
        $this->artisan('turismo:sembrar')->assertSuccessful();

        $this->assertNull(app(ImageJsonRepository::class)->buscarDestino('villa-pehuenia'));
        $this->assertNotNull(app(ImageJsonRepository::class)->buscarDestino('caviahue'));
    }

    public function test_force_replaces_existing_destinations(): void
    {
        File::put($this->rutaSemillas.'/fotos/termas.png', $this->pngDegradado());
        $this->escribirSemillas();
        $this->artisan('turismo:sembrar');

        $this->escribirSemillas('Villa Pehuenia');
        $this->artisan('turismo:sembrar', ['--force' => true])->assertSuccessful();

        $this->assertNotNull(app(ImageJsonRepository::class)->buscarDestino('villa-pehuenia'));
        $this->assertNull(app(ImageJsonRepository::class)->buscarDestino('caviahue'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function fotosVersionadas(): array
    {
        return [
            'Lago Filo Hua Hum' => ['lago-filo-hua-hum.jpg', 'Lago Filo Hua Hum', '070f0f0f0f0f0f1f'],
            'Lago Huechulafquen' => ['Huechu1.JPG', 'Lago Huechulafquen', 'f7f7f43c851101a5'],
            'Villa La Angostura' => ['645FFFFE-9E8E-4821-A1CF-9FD2DA0FE04E.png', 'Villa La Angostura', 'ffbfbfa79f8f8501'],
        ];
    }

    #[DataProvider('fotosVersionadas')]
    public function test_each_versioned_photo_is_recognized_in_its_destination(string $archivo, string $destino, string $hash): void
    {
        config(['turismo.ruta_semillas' => database_path('semillas')]);
        $this->artisan('turismo:sembrar')->assertSuccessful();

        $foto = new UploadedFile(database_path("semillas/fotos/{$archivo}"), $archivo, test: true);

        $this->post(route('image.compare'), ['image' => $foto])->assertRedirect(route('image.result'));

        $this->get(route('image.result'))
            ->assertSee('¡Coincidencia encontrada!')
            ->assertSee("<strong>{$destino}</strong>", false)
            ->assertSee($hash)
            ->assertSee('0 de 64 bits');

        Storage::disk('public')->assertExists("destinos/{$archivo}");
    }
}
