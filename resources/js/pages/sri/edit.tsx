import { Head, router, usePage } from '@inertiajs/react';
import { update, test } from '@/routes/sri-credentials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useState } from 'react';
import type { SriCredential } from '@/types/fiscal-extraction';

type Environment = SriCredential['environment'];

export default function SriCredentialsEdit({ credential }: { credential: SriCredential | null }) {
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

            <div className="max-w-xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Conexión con el SRI</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            Credenciales del portal SRI
                            {credential && (
                                <Badge variant={credential.is_connected ? 'outline' : 'destructive'}>
                                    {credential.is_connected ? 'Conectado' : 'Sin verificar'}
                                </Badge>
                            )}
                        </CardTitle>
                        <CardDescription>
                            Estas credenciales son las mismas que usas para iniciar sesión en{' '}
                            <span className="font-mono">srienlinea.sri.gob.ec</span>. Se guardan cifradas y nunca se muestran de nuevo.
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
                                <Input
                                    id="password"
                                    type="password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    placeholder={credential ? 'Dejar en blanco para no cambiarla' : ''}
                                    required={!credential}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>Ambiente</Label>
                                <Select value={environment} onValueChange={(value) => setEnvironment(value as Environment)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="production">Producción</SelectItem>
                                        <SelectItem value="test">Pruebas</SelectItem>
                                    </SelectContent>
                                </Select>
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
            </div>
        </>
    );
}
