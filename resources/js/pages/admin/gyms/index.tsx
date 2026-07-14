import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, QrCode, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Gym = {
    id: number;
    name: string;
    address: string;
    latitude: number;
    longitude: number;
    checkin_radius_m: number;
    checkins_count: number;
};

export default function GymsIndex({ gyms }: { gyms: Gym[] }) {
    const remove = (gym: Gym) => {
        if (confirm(`Hapus gym "${gym.name}"? Semua check-in ikut terhapus.`)) {
            router.delete(`/admin/gyms/${gym.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="p-4">
            <Head title="Gym" />

            <div className="mb-6 flex items-start justify-between">
                <Heading
                    title="Cabang Gym"
                    description="Kelola cabang FTL beserta titik check-in GPS."
                />
                <Button asChild>
                    <Link href="/admin/gyms/create">
                        <Plus className="h-4 w-4" /> Tambah Gym
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{gyms.length} cabang</CardTitle>
                    <CardDescription>
                        Cetak lembar QR per cabang untuk ditempel di tiap alat.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">Nama</th>
                                    <th className="py-2 pr-4 font-medium">Alamat</th>
                                    <th className="py-2 pr-4 font-medium">Radius</th>
                                    <th className="py-2 pr-4 font-medium">Check-in</th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {gyms.map((gym) => (
                                    <tr key={gym.id} className="border-b last:border-0">
                                        <td className="py-2 pr-4">{gym.name}</td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {gym.address}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {gym.checkin_radius_m} m
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {gym.checkins_count}
                                        </td>
                                        <td className="py-2 pl-4 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Cetak QR"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/admin/gyms/${gym.id}/qr`}
                                                    >
                                                        <QrCode className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/admin/gyms/${gym.id}/edit`}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => remove(gym)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

GymsIndex.layout = {
    breadcrumbs: [{ title: 'Gym', href: '/admin/gyms' }],
};
