import { ChevronDownIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export interface MultiSelectOption {
    value: string;
    label: string;
}

interface MultiSelectProps {
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    className?: string;
    id?: string;
}

export function MultiSelect({
    options,
    value,
    onChange,
    placeholder = 'Select options',
    className,
    id,
}: MultiSelectProps) {
    const selectedLabels = options
        .filter((option) => value.includes(option.value))
        .map((option) => option.label);

    const displayText =
        selectedLabels.length === 0
            ? placeholder
            : selectedLabels.length <= 2
              ? selectedLabels.join(', ')
              : `${selectedLabels.length} selected`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className={cn(
                        'h-10 w-full justify-between px-3 font-normal shadow-xs',
                        value.length === 0 && 'text-muted-foreground',
                        className
                    )}
                >
                    <span className="truncate">{displayText}</span>
                    <ChevronDownIcon className="size-4 shrink-0 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="max-h-60 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto"
            >
                {options.map((option) => (
                    <DropdownMenuCheckboxItem
                        key={option.value}
                        checked={value.includes(option.value)}
                        onCheckedChange={(checked) => {
                            const next = checked
                                ? [...value, option.value]
                                : value.filter((item) => item !== option.value);
                            onChange(next);
                        }}
                        onSelect={(event) => event.preventDefault()}
                    >
                        {option.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
