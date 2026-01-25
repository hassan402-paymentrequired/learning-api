import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/admin';

interface Subject {
    id: number;
    name: string;
}

interface Props {
    subjects: Subject[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exams', href: admin.exams.index().url },
    { title: 'Create Exam', href: '#' },
];

export default function CreateExam({ subjects }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        subject_id: '',
        year: null as number | null,
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(admin.exams.store().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Exam" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Create Exam</h1>
                    <p className="text-muted-foreground">Add a new past question exam</p>
                </div>

                <Card className="border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-900/10">
                    <CardHeader>
                        <CardTitle className="text-sm">Note</CardTitle>
                        <CardDescription className="text-xs">
                            Exams are now only for past questions. Students select their own duration when taking exams.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Exam Details</CardTitle>
                        <CardDescription>Fill in the information for the new JAMB past question exam</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g., JAMB Mathematics 2024 (optional - will auto-generate)"
                                />
                                <InputError message={errors.title} />
                                <p className="text-xs text-muted-foreground">
                                    Title will be auto-generated if left empty based on subject and year
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="subject_id">Subject/Course *</Label>
                                    <Select
                                        value={data.subject_id}
                                        onValueChange={(value) => setData('subject_id', value)}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a subject" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {subjects.map((subject) => (
                                                <SelectItem key={subject.id} value={subject.id.toString()}>
                                                    {subject.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.subject_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="year">Year *</Label>
                                    <Input
                                        id="year"
                                        type="number"
                                        min="2000"
                                        max={new Date().getFullYear() + 1}
                                        value={data.year || ''}
                                        onChange={(e) => setData('year', e.target.value ? parseInt(e.target.value) : null)}
                                        placeholder="e.g., 2024"
                                        required
                                    />
                                    <InputError message={errors.year} />
                                    <p className="text-xs text-muted-foreground">
                                        Only one past question exam per subject per year
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="is_active" className="cursor-pointer">
                                    Active (visible to students)
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Exam'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                >
                                    <a href={admin.exams.index().url}>Cancel</a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
