<?php

namespace App\Models;

use Database\Factories\DestinoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'localidad_cercana', 'departamento', 'descripcion'])]
class Destino extends Model
{
    /** @use HasFactory<DestinoFactory> */
    use HasFactory;

    /**
     * @return HasMany<Imagen, $this>
     */
    public function imagenes(): HasMany
    {
        return $this->hasMany(Imagen::class);
    }
}
