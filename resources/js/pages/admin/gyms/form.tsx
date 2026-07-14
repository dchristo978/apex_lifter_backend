import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Gym = {
    id: number;
    name: string;
    address: string;
    latitude: number;
    longitude: number;
    checkin_radius_m: number;
};

export default function GymForm({ gym }: { gym: Gym | null }) {
    const editing = gym !== null;

    return (
        <div className="p-4">
            <Head title={editing ? 'Edit Gym' : 'Tambah Gym'} />

            <div className="mb-6 flex items-center gap-2">
                <Button variant="ghost" size="icon" asChild>
                    <Link href="/admin/gyms">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                </Button>
                <Heading
                    title={editing ? `Edit ${gym.name}` : 'Tambah Gym'}
                    description="Koordinat dipakai untuk check-in GPS (haversine)."
                />
            </div>

            <Card className="max-w-xl">
                <CardContent className="pt-6">
                    <Form
                        action={editing ? `/admin/gyms/${gym.id}` : '/admin/gyms'}
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
                                        defaultValue={gym?.name}
                                        required
                                        placeholder="FTL Puri Indah"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Alamat</Label>
                                    <Input
                                        id="address"
                                        name="address"
                                        defaultValue={gym?.address}
                                        required
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="latitude">Latitude</Label>
                                        <Input
                                            id="latitude"
                                            name="latitude"
                                            type="number"
                                            step="any"
                                            defaultValue={gym?.latitude}
                                            required
                                            placeholder="-6.1876"
                                        />
                                        <InputError message={errors.latitude} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="longitude">Longitude</Label>
                                        <Input
                                            id="longitude"
                                            name="longitude"
                                            type="number"
                                            step="any"
                                            defaultValue={gym?.longitude}
                                            required
                                            placeholder="106.7382"
                                        />
                                        <InputError message={errors.longitude} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="checkin_radius_m">
                                        Radius check-in (meter)
                                    </Label>
                                    <Input
                                        id="checkin_radius_m"
                                        name="checkin_radius_m"
                                        type="number"
                                        defaultValue={gym?.checkin_radius_m ?? 150}
                                        required
                                    />
                                    <InputError message={errors.checkin_radius_m} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {editing ? 'Simpan' : 'Tambah'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/admin/gyms">Batal</Link>
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

GymForm.layout = {
    breadcrumbs: [{ title: 'Gym', href: '/admin/gyms' }],
};
