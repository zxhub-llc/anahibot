import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';

import {
    Building01Icon,
    Call02Icon,
    FloppyDiskIcon,
    Location01Icon,
    Mail01Icon,
    UserIcon,
    IdentificationIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

type Team = {
    id: number;
    slug: string;
    name: string;
};

type Company = {
    id?: number;
    team_id?: number;
    ruc: string | null;
    business_name: string | null;
    trade_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    legal_representative: string | null;
    legal_representative_id: string | null;
};

type Props = {
    team: Team;
    company: Company | null;
};

type FormData = {
    ruc: string;
    business_name: string;
    trade_name: string;
    email: string;
    phone: string;
    address: string;
    legal_representative: string;
    legal_representative_id: string;
};

export default function EditCompany({ team, company }: Props) {
    const [data, setData] = useState<FormData>({
        ruc: company?.ruc ?? '',
        business_name: company?.business_name ?? '',
        trade_name: company?.trade_name ?? '',
        email: company?.email ?? '',
        phone: company?.phone ?? '',
        address: company?.address ?? '',
        legal_representative: company?.legal_representative ?? '',
        legal_representative_id: company?.legal_representative_id ?? '',
    });

    const [processing, setProcessing] = useState(false);

    function updateField<K extends keyof FormData>(
        field: K,
        value: FormData[K],
    ) {
        setData((current) => ({
            ...current,
            [field]: value,
        }));
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        setProcessing(true);

        router.put(`/${team.slug}/company`, data, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <>
            <Head title="Información de la empresa" />

            <div className="@container/main flex flex-col gap-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <HugeiconsIcon
                                icon={Building01Icon}
                                className="size-5 text-muted-foreground"
                            />

                            <h1 className="text-2xl font-semibold tracking-tight">
                                Información de la empresa
                            </h1>
                        </div>

                        <p className="text-sm text-muted-foreground">
                            Consulta y administra la información fiscal y comercial
                            de tu empresa.
                        </p>
                    </div>

                    <Badge
                        variant="secondary"
                        className="w-fit gap-1.5 px-3 py-1"
                    >
                        <span className="size-1.5 rounded-full bg-emerald-500" />
                        {company ? 'Empresa configurada' : 'Sin configurar'}
                    </Badge>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Información fiscal */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HugeiconsIcon
                                    icon={IdentificationIcon}
                                    className="size-5 text-muted-foreground"
                                />

                                Información fiscal
                            </CardTitle>

                            <CardDescription>
                                Datos principales de identificación del
                                contribuyente.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="ruc">
                                        RUC
                                    </Label>

                                    <Input
                                        id="ruc"
                                        value={data.ruc}
                                        onChange={(event) =>
                                            updateField(
                                                'ruc',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0993407077001"
                                        maxLength={13}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Número de Registro Único de
                                        Contribuyentes.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="business_name">
                                        Razón social
                                    </Label>

                                    <Input
                                        id="business_name"
                                        value={data.business_name}
                                        onChange={(event) =>
                                            updateField(
                                                'business_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="ZXHUB S.A.S."
                                    />
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="trade_name">
                                        Nombre comercial
                                    </Label>

                                    <Input
                                        id="trade_name"
                                        value={data.trade_name}
                                        onChange={(event) =>
                                            updateField(
                                                'trade_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Nombre comercial de la empresa"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Contacto */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HugeiconsIcon
                                    icon={Mail01Icon}
                                    className="size-5 text-muted-foreground"
                                />

                                Información de contacto
                            </CardTitle>

                            <CardDescription>
                                Información utilizada para comunicaciones
                                administrativas y fiscales.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="email"
                                        className="flex items-center gap-2"
                                    >
                                        <HugeiconsIcon
                                            icon={Mail01Icon}
                                            className="size-4"
                                        />
                                        Correo electrónico
                                    </Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            updateField(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="empresa@ejemplo.com"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label
                                        htmlFor="phone"
                                        className="flex items-center gap-2"
                                    >
                                        <HugeiconsIcon
                                            icon={Call02Icon}
                                            className="size-4"
                                        />
                                        Teléfono
                                    </Label>

                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(event) =>
                                            updateField(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0999999999"
                                    />
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label
                                        htmlFor="address"
                                        className="flex items-center gap-2"
                                    >
                                        <HugeiconsIcon
                                            icon={Location01Icon}
                                            className="size-4"
                                        />
                                        Dirección
                                    </Label>

                                    <Textarea
                                        id="address"
                                        value={data.address}
                                        onChange={(event) =>
                                            updateField(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Dirección del establecimiento o domicilio fiscal"
                                        rows={3}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Representante legal */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HugeiconsIcon
                                    icon={UserIcon}
                                    className="size-5 text-muted-foreground"
                                />

                                Representante legal
                            </CardTitle>

                            <CardDescription>
                                Información registrada para el representante
                                legal del contribuyente.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="legal_representative">
                                        Nombre completo
                                    </Label>

                                    <Input
                                        id="legal_representative"
                                        value={data.legal_representative}
                                        onChange={(event) =>
                                            updateField(
                                                'legal_representative',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Nombres y apellidos"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="legal_representative_id">
                                        Identificación
                                    </Label>

                                    <Input
                                        id="legal_representative_id"
                                        value={data.legal_representative_id}
                                        onChange={(event) =>
                                            updateField(
                                                'legal_representative_id',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Cédula o identificación"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Footer */}
                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-xs text-muted-foreground">
                            <p>
                                La información sincronizada desde el SRI puede
                                actualizar estos datos.
                            </p>
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="gap-2"
                        >
                            <HugeiconsIcon
                                icon={FloppyDiskIcon}
                                className="size-4"
                            />

                            {processing
                                ? 'Guardando...'
                                : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

EditCompany.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Empresa',
            href: `/${props.team.slug}/company`,
        },
        {
            title: 'Información',
            href: `/${props.team.slug}/company`,
        },
    ],
});