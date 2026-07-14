import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Machine = {
    id: number;
    name: string;
    brand: string;
    category: string;
    description: string | null;
};

export default function MachineForm({ machine }: { machine: Machine | null }) {
    const editing = machine !== null;

    return (
        <div className="p-4">
            <Head title={editing ? 'Edit Alat' : 'Tambah Alat'} />

            <div className="mb-6 flex items-center gap-2">
                <Button variant="ghost" size="icon" asChild>
                    <Link href="/admin/machines">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                </Button>
                <Heading
                    title={editing ? `Edit ${machine.name}` : 'Tambah Alat'}
                    description="Alat terstandar yang dipakai untuk leaderboard."
                />
            </div>

            <Card className="max-w-xl">
                <CardContent className="pt-6">
                    <Form
                        action={
                            editing
                                ? `/admin/machines/${machine.id}`
                                : '/admin/machines'
                        }
                        method={editing ? 'put' : 'post'}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={machine?.name}
                                        required
                                        placeholder="Leg Press"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="category">Kategori</Label>
                                    <Input
                                        id="category"
                                        name="category"
                                        defaultValue={machine?.category}
                                        required
                                        placeholder="legs, chest, back, ..."
                                    />
                                    <InputError message={errors.category} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="brand">Merek</Label>
                                    <Input
                                        id="brand"
                                        name="brand"
                                        defaultValue={machine?.brand ?? 'Shua Fitness'}
                                        required
                                    />
                                    <InputError message={errors.brand} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Deskripsi / cara pakai
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        defaultValue={machine?.description ?? ''}
                                        rows={3}
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        placeholder="Opsional"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {editing ? 'Simpan' : 'Tambah'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/admin/machines">Batal</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </div>
    );
}

MachineForm.layout = {
    breadcrumbs: [{ title: 'Alat', href: '/admin/machines' }],
};
