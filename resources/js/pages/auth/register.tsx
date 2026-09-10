import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

type AccountType = 'company' | 'accountant';

type Props = {
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
};

export default function Register({ passwordRules, teamInvitation }: Props) {
    const [accountType, setAccountType] = useState<AccountType>('company');

    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {teamInvitation && (
                            <TeamInvitationAlert
                                invitation={teamInvitation}
                                action="Register"
                            />
                        )}

                        <input type="hidden" name="account_type" value={accountType} />

                        {!teamInvitation && (
                            <div className="grid gap-2">
                                <Label>Tipo de cuenta</Label>
                                <Tabs
                                    value={accountType}
                                    onValueChange={(v) => setAccountType(v as AccountType)}
                                >
                                    <TabsList className="grid w-full grid-cols-2">
                                        <TabsTrigger value="company">Empresa</TabsTrigger>
                                        <TabsTrigger value="accountant">Contador</TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>
                        )}

                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {accountType === 'company' ? 'Nombre de contacto' : 'Nombre'}
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nombre completo"
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            {accountType === 'company' && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="company_name">Nombre de la empresa</Label>
                                        <Input
                                            id="company_name"
                                            type="text"
                                            required
                                            tabIndex={2}
                                            name="company_name"
                                            placeholder="Razón social"
                                        />
                                        <InputError message={errors.company_name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ruc">RUC</Label>
                                        <Input
                                            id="ruc"
                                            type="text"
                                            required
                                            tabIndex={3}
                                            name="ruc"
                                            placeholder="1234567890001"
                                            maxLength={13}
                                        />
                                        <InputError message={errors.ruc} />
                                    </div>
                                </>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo electrónico</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={4}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Contraseña"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirmar contraseña"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Crear cuenta
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            ¿Ya tienes cuenta?{' '}
                            <TextLink
                                href={
                                    teamInvitation
                                        ? login.url({ query: { invitation: teamInvitation.code } })
                                        : login()
                                }
                                data-test="team-invitation-login-link"
                                tabIndex={8}
                            >
                                Iniciar sesión
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Crear una cuenta',
    description: 'Ingresa tus datos para crear tu cuenta',
};