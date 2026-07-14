import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Machine = {
    id: number;
    name: string;
    brand: string;
    category: string;
    description: string | null;
};

export default function MachinesIndex({ machines }: { machines: Machine[] }) {
    const remove = (machine: Machine) => {
        if (confirm(`Hapus alat "${machine.name}"?`)) {
            router.delete(`/admin/machines/${machine.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="p-4">
            <Head title="Alat" />

            <div className="mb-6 flex items-start justify-between">
                <Heading
                    title="Alat Gym"
                    description="Kelola daftar alat terstandar (Shua Fitness)."
                />
                <Button asChild>
                    <Link href="/admin/machines/create">
                        <Plus className="h-4 w-4" /> Tambah Alat
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{machines.length} alat</CardTitle>
                    <CardDescription>
                        Alat yang muncul di leaderboard dan pencatatan set.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">Nama</th>
                                    <th className="py-2 pr-4 font-medium">Kategori</th>
                                    <th className="py-2 pr-4 font-medium">Merek</th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {machines.map((machine) => (
                                    <tr key={machine.id} className="border-b last:border-0">
                                        <td className="py-2 pr-4">{machine.name}</td>
                                        <td className="py-2 pr-4 capitalize text-muted-foreground">
                                            {machine.category}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {machine.brand}
                                        </td>
                                        <td className="py-2 pl-4 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/admin/machines/${machine.id}/edit`}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => remove(machine)}
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

MachinesIndex.layout = {
    breadcrumbs: [{ title: 'Alat', href: '/admin/machines' }],
};
