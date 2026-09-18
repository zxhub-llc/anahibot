import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { HugeiconsIcon } from '@hugeicons/react';
import { EyeClosedIcon, EyeIcon, PencilEdit02Icon } from '@hugeicons/core-free-icons';

const MASKED_PLACEHOLDER = '••••••••••••';

interface PasswordProps extends Omit<ComponentProps<'input'>, 'type' | 'value' | 'onChange'> {
    ref?: Ref<HTMLInputElement>;
    value: string;
    onChange: (value: string) => void;
    hasSavedValue?: boolean;
}

export default function Password({
    className,
    ref,
    value,
    onChange,
    hasSavedValue = false,
    ...props
}: PasswordProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [locked, setLocked] = useState(hasSavedValue);

    function handleUnlock() {
        setLocked(false);
        onChange('');
    }

    return (
        <div className="relative">
            <Input
                type={locked ? 'text' : showPassword ? 'text' : 'password'}
                className={cn('pr-10', className)}
                ref={ref}
                value={locked ? MASKED_PLACEHOLDER : value}
                onChange={(event) => onChange(event.target.value)}
                disabled={locked}
                readOnly={locked}
                {...props}
            />
            {locked ? (
                <button
                    type="button"
                    onClick={handleUnlock}
                    className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Cambiar contraseña"
                >
                    <HugeiconsIcon icon={PencilEdit02Icon} />
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => setShowPassword((prev) => !prev)}
                    className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                    aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    tabIndex={-1}
                >
                    {showPassword ? <HugeiconsIcon icon={EyeIcon} /> : <HugeiconsIcon icon={EyeClosedIcon} />}
                </button>
            )}
        </div>
    );
}