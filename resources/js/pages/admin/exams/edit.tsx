import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';

interface Exam {
    id: number;
    title: string;
    description: string | null;
    exam_type: 'JAMB' | 'DLI' | 'UNILAG' | 'GENERAL';
    subject: string | null;
    year: number | null;
    is_active: boolean;
}

interface Props {
    exam: Exam;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exams', href: route('admin.exams.index').url },
    { title: 'Edit Exam', href: '#' },
];

export default function EditExam({ exam }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: exam.title,
        description: exam.description || '',
        exam_type: exam.exam_type as 'JAMB' | 'UNILAG' | 'DLI' | 'GENERAL',
        subject: exam.subject || '',
        year: exam.year,
        is_active: exam.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.exams.update', exam.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Exam" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Edit Exam</h1>
                    <p className="text-muted-foreground">Update exam information</p>
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
                        <CardDescription>Update the information for this exam</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title *</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="exam_type">Exam Type *</Label>
                                    <Select
                                        value={data.exam_type}
                                        onValueChange={(value: 'JAMB' | 'UNILAG' | 'DLI' | 'GENERAL') => setData('exam_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="JAMB">JAMB</SelectItem>
                                            <SelectItem value="UNILAG">UNILAG</SelectItem>
                                            <SelectItem value="DLI">DLI (Distance Learning Institute)</SelectItem>
                                            <SelectItem value="GENERAL">GENERAL</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.exam_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input
                                        id="subject"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        placeholder="e.g., Mathematics"
                                    />
                                    <InputError message={errors.subject} />
                                    <p className="text-xs text-muted-foreground">
                                        Optional: Subject/course for this past question exam
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="year">Year</Label>
                                <Input
                                    id="year"
                                    type="number"
                                    min="2000"
                                    max={new Date().getFullYear() + 1}
                                    value={data.year || ''}
                                    onChange={(e) => setData('year', e.target.value ? parseInt(e.target.value) : null)}
                                    placeholder="e.g., 2024"
                                />
                                <InputError message={errors.year} />
                                <p className="text-xs text-muted-foreground">
                                    Year for this past question exam
                                </p>
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
                                    {processing ? 'Updating...' : 'Update Exam'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                >
                                    <a href={route('admin.exams.show', exam.id)}>Cancel</a>
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
