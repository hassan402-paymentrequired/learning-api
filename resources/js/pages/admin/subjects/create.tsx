import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, Link,  useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import admin from '@/routes/admin';

interface Department {
    id: number;
    name: string;
}

interface Props {
    departments: Department[];
}

export default function CreateSubject({ departments }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        exam_types: [] as string[],
        department_id: null as number | null,
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(admin.subjects.store().url);
    };

    return (
        <AppLayout>
            <Head title="Create Subject" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                
                        <div>
                            <h1 className="text-2xl font-bold">Create Subject</h1>
                            <p className="text-muted-foreground">Add a new subject for practice exams</p>
                        </div>
                    </div>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Subject Information</CardTitle>
                        <CardDescription>Enter the details for the new subject</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Subject Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Mathematics, English, Physics"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional description for this subject"
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Available for Exam Types *</Label>
                                <div className="flex gap-4">
                                    {['JAMB', 'DLI', 'UNILAG', 'GENERAL'].map((type) => (
                                        <div key={type} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`exam_type_${type}`}
                                                checked={data.exam_types.includes(type)}
                                                onCheckedChange={(checked) => {
                                                    setData('exam_types', checked
                                                        ? [...data.exam_types, type]
                                                        : data.exam_types.filter((t) => t !== type)
                                                    );
                                                    // Clear department if DLI/UNILAG is unchecked
                                                    if (!checked && (type === 'DLI' || type === 'UNILAG')) {
                                                        const hasDliOrUnilag = (checked
                                                            ? [...data.exam_types, type]
                                                            : data.exam_types.filter((t) => t !== type)
                                                        ).some(t => t === 'DLI' || t === 'UNILAG');
                                                        if (!hasDliOrUnilag) {
                                                            setData('department_id', null);
                                                        }
                                                    }
                                                }}
                                            />
                                            <Label htmlFor={`exam_type_${type}`} className="font-normal cursor-pointer">
                                                {type}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Select which exam types this subject should be available for. You can select multiple.
                                </p>
                                {errors.exam_types && (
                                    <p className="text-sm text-red-500">{errors.exam_types}</p>
                                )}
                            </div>

                            {(data.exam_types.includes('DLI') || data.exam_types.includes('UNILAG')) && (
                                <div className="space-y-2">
                                    <Label htmlFor="department_id">Department *</Label>
                                    <Select
                                        value={data.department_id?.toString() || ''}
                                        onValueChange={(value) => setData('department_id', value ? parseInt(value) : null)}
                                    >
                                        <SelectTrigger id="department_id">
                                            <SelectValue placeholder="Select a department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept.id} value={dept.id.toString()}>
                                                    {dept.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Department is required for DLI/Unilag subjects.
                                    </p>
                                    {errors.department_id && (
                                        <p className="text-sm text-red-500">{errors.department_id}</p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active" className="cursor-pointer">
                                    Active (visible to students)
                                </Label>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create Subject'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={admin.subjects.index().url}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
