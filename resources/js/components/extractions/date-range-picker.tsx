import * as React from 'react';
import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { DateRange } from 'react-day-picker';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

interface Props {
    value: DateRange | undefined;
    onChange: (range: DateRange | undefined) => void;
}

export function DateRangePicker({ value, onChange }: Props) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className={cn('w-[280px] justify-start text-left font-normal', !value && 'text-muted-foreground')}
                >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {value?.from ? (
                        value.to ? (
                            <>
                                {format(value.from, 'dd/MM/yyyy')} - {format(value.to, 'dd/MM/yyyy')}
                            </>
                        ) : (
                            format(value.from, 'dd/MM/yyyy')
                        )
                    ) : (
                        <span>Selecciona un rango de fechas</span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="range"
                    defaultMonth={value?.from}
                    selected={value}
                    onSelect={onChange}
                    numberOfMonths={2}
                    disabled={{ after: new Date() }}
                />
            </PopoverContent>
        </Popover>
    );
}