<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageHashService;

class ImageComparisonController extends Controller
{
    public function index()
    {
        return view('image-comparison.index');
    }

    public function compare(
        Request $request,
        ImageHashService $hashService
    )
    {
        $request->validate([
            'image' => 'required|image'
        ]);

        $image = $request->file('image');

        $filename = time().'_'.$image->getClientOriginalName();

        $path = $image->storeAs(
            'images',
            $filename,
            'public'
        );

        $realPath = storage_path(
            'app/public/'.$path
        );

        $newHash = $hashService->hash(
            $realPath
        );

        $jsonFile = database_path(
            'json/images.json'
        );

        if (!file_exists($jsonFile)) {
            file_put_contents(
                $jsonFile,
                json_encode([])
            );
        }

        $records = json_decode(
            file_get_contents($jsonFile),
            true
        );

        $matches = [];

        foreach ($records as $record) {

            $distance = $hashService->distance(
                $newHash,
                $record['hash']
            );

            $similarity = max(
                0,
                100 - ($distance * 2)
            );

            $matches[] = [
                'file' => $record['file'],
                'distance' => $distance,
                'similarity' => $similarity
            ];
        }

        usort($matches, function($a, $b){
            return $a['distance'] <=> $b['distance'];
        });

        $records[] = [
            'file' => $filename,
            'hash' => $newHash
        ];

        file_put_contents(
            $jsonFile,
            json_encode(
                $records,
                JSON_PRETTY_PRINT
            )
        );

        return view(
            'image-comparison.result',
            [
                'uploadedImage' => $filename,
                'matches' => array_slice(
                    $matches,
                    0,
                    5
                )
            ]
        );
    }
}