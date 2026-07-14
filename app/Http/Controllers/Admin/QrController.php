<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Machine;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QrController extends Controller
{
    /**
     * Printable PDF of QR codes for every machine at a gym (MVP 2 #5 / #2).
     * Each QR encodes a signed machine_id + gym_id deep link so a future scan
     * can be trusted as a genuine, un-tampered check-in target.
     */
    public function gymSheet(Gym $gym): Response
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(180, 1),
            new SvgImageBackEnd(),
        ));

        $machines = Machine::orderBy('category')->orderBy('name')->get()
            ->map(function (Machine $machine) use ($gym, $writer) {
                return [
                    'name' => $machine->name,
                    'brand' => $machine->brand,
                    'category' => $machine->category,
                    'svg' => $writer->writeString(self::payload($machine->id, $gym->id)),
                ];
            });

        $pdf = Pdf::loadView('admin.qr-sheet', [
            'gym' => $gym,
            'machines' => $machines,
        ])->setPaper('a4');

        return $pdf->download("qr-{$gym->id}-".str($gym->name)->slug().'.pdf');
    }

    /**
     * Signed deep link "apexlifter://checkin?m=..&g=..&t=<hmac>".
     */
    public static function payload(int $machineId, int $gymId): string
    {
        $data = "m={$machineId}&g={$gymId}";
        $signature = hash_hmac('sha256', $data, config('app.key'));

        return "apexlifter://checkin?{$data}&t=".substr($signature, 0, 16);
    }
}
