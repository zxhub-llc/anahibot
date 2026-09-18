import { Head, router, usePage } from '@inertiajs/react';
import { update, test } from '@/routes/sri-credentials';
import { edit as editCompany } from '@/routes/companies';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useState } from 'react';
import type { SriCredential } from '@/types/fiscal-extraction';
import Heading from '@/components/heading';
import { Link } from '@inertiajs/react';
import Password from '@/components/password';

type Environment = SriCredential['environment'];

interface Company {
    ruc: string | null;
    business_name: string | null;
    trade_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    legal_representative: string | null;
    legal_representative_id: string | null;
}

export default function Edit({ credential, company }: { credential: SriCredential | null; company: Company | null }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const [companyRuc, setCompanyRuc] = useState(credential?.company_ruc ?? '');
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [environment, setEnvironment] = useState<Environment>(
        credential?.environment ?? 'production'
    );

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        router.put(
            update({ current_team: teamSlug }).url,
            { company_ruc: companyRuc, username, password, environment },
            { preserveScroll: true, onSuccess: () => setPassword('') },
        );
    }

    function handleTest() {
        router.post(test({ current_team: teamSlug }).url, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Conexión con el SRI" />

            <div className="@container/main flex flex-col gap-6 p-4 sm:p-6">
                <Heading
                    variant="small"
                    title="Conexión con el SRI"
                    description="Estas credenciales son las mismas que usas para iniciar sesión en srienlinea.sri.gob.ec. Se guardan cifradas y nunca se muestran de nuevo."
                />

                <div className="grid gap-6 md:grid-cols-5">
                    <Card className="col-span-2">
                        <CardHeader>
                            <CardTitle className="flex justify-between items-center gap-2">
                                Credenciales del portal SRI

                                {credential && (
                                    <Badge
                                        variant="outline"
                                        className={
                                            credential.is_connected
                                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'
                                        }
                                    >
                                        <span
                                            className={
                                                credential.is_connected
                                                    ? 'size-1.5 rounded-full bg-emerald-500'
                                                    : 'size-1.5 rounded-full bg-red-500'
                                            }
                                        />
                                        {credential.is_connected ? 'Conectado' : 'Sin verificar'}
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                Utiliza las mismas credenciales con las que accedes al portal{' '}
                                <span className="font-medium text-foreground">SRI en Línea</span>.
                                {' '}Tu información de acceso se almacena de forma cifrada y no volverá a
                                mostrarse una vez guardada.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-1">
                                    <Label htmlFor="company_ruc">RUC</Label>
                                    <Input
                                        id="company_ruc"
                                        value={companyRuc}
                                        onChange={(event) => setCompanyRuc(event.target.value)}
                                        maxLength={13}
                                        required
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="username">C.I. adicional (opcional)</Label>
                                    <Input
                                        id="username"
                                        value={username}
                                        onChange={(event) => setUsername(event.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Déjalo en blanco para iniciar sesión con el RUC como titular. Llénalo solo si
                                        inicias sesión como un usuario adicional autorizado sobre este RUC.
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="password">Contraseña SRI</Label>
                                    <Password
                                        id="password"
                                        value={password}
                                        onChange={setPassword}
                                        hasSavedValue={!!credential}
                                        placeholder={credential ? '' : 'Contraseña'}
                                        required={!credential}
                                    />
                                    {credential && (
                                        <p className="text-xs text-muted-foreground">
                                            Haz clic en el lápiz para cambiarla. Si no la editas, se conserva la actual.
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="environment">Ambiente</Label>

                                        <Select
                                            value={environment}
                                            onValueChange={(value) =>
                                                setEnvironment(value as Environment)
                                            }
                                        >
                                            <SelectTrigger
                                                id="environment"
                                                size='sm'
                                            >
                                                <SelectValue placeholder="Selecciona un ambiente" />
                                            </SelectTrigger>

                                            <SelectContent className='w-[300px] rounded-xl' position='popper' align='end'>
                                                <SelectItem value="production">
                                                    <div className="flex items-center gap-2 whitespace-nowrap">
                                                        <span className="size-2 rounded-full bg-emerald-500 shrink-0" />
                                                        <div className="flex items-baseline gap-1.5">
                                                            <span className="font-medium text-sm">Producción</span>
                                                            <span className="text-xs text-muted-foreground">Conexión con el SRI</span>
                                                        </div>
                                                    </div>
                                                </SelectItem>

                                                <SelectItem value="test">
                                                    <div className="flex items-center gap-2 whitespace-nowrap">
                                                        <span className="size-2 rounded-full bg-amber-500 shrink-0" />
                                                        <div className="flex items-baseline gap-1.5">
                                                            <span className="font-medium text-sm">Pruebas</span>
                                                            <span className="text-xs text-muted-foreground">Entorno de pruebas del SRI</span>
                                                        </div>
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        Selecciona el ambiente que utilizarán las credenciales para
                                        conectarse al portal del SRI.
                                    </p>
                                </div>

                                {credential?.last_authentication_error && (
                                    <p className="text-sm text-destructive">{credential.last_authentication_error}</p>
                                )}

                                <div className="flex gap-3">
                                    <Button type="submit">Guardar</Button>
                                    {credential && (
                                        <Button type="button" variant="outline" onClick={handleTest}>
                                            Probar conexión
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="col-span-3">
                        <CardHeader>
                            <CardTitle>Datos del contribuyente</CardTitle>
                            <CardDescription>
                                {company
                                    ? 'Sincronizados automáticamente al probar la conexión con el SRI.'
                                    : 'Aún no se ha sincronizado ningún dato. Prueba la conexión para traerlos.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {company ? (
                                <dl className="space-y-3 text-sm">
                                    <CompanyField label="RUC" value={company.ruc} />
                                    <CompanyField label="Razón social" value={company.business_name} />
                                    <CompanyField label="Nombre comercial" value={company.trade_name} />
                                    <CompanyField label="Email" value={company.email} />
                                    <CompanyField label="Teléfono" value={company.phone} />
                                    <CompanyField label="Dirección" value={company.address} />
                                    <CompanyField label="Representante legal" value={company.legal_representative} />
                                    <CompanyField label="Identificación representante" value={company.legal_representative_id} />
                                </dl>
                            ) : (
                                <p className="text-sm text-muted-foreground">Sin datos todavía.</p>
                            )}

                            <div className="mt-4">
                                <Link
                                    href={editCompany({ current_team: teamSlug }).url}
                                    className="text-sm text-primary underline underline-offset-4"
                                >
                                    Editar manualmente
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function CompanyField({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b pb-2 last:border-0 last:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={value ? 'text-right font-medium' : 'text-right text-muted-foreground italic'}>
                {value ?? 'No disponible'}
            </dd>
        </div>
    );
}

Edit.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'SRI',
            href: props.currentTeam ? '/tenant/' + props.currentTeam.slug + '/sri-credentials' : '/',
        },
    ],
});