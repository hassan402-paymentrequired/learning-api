import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/admin';

interface Exam {
    id: number;
    title: string;
    subject: string | null;
    year: number | null;
    is_active: boolean;
}

interface Subject {
    id: number;
    name: string;
}

interface ExamCategory {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    exam: Exam;
    subjects: Subject[];
    examCategories: ExamCategory[];
    current_subject_id?: number | null;
    current_exam_category_id?: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Past Questions', href: admin.exams.index().url },
    { title: 'Edit', href: '#' },
];

export default function EditExam({
    exam,
    subjects,
    examCategories,
    current_subject_id,
    current_exam_category_id,
}: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        title: exam.title,
        exam_category_id: current_exam_category_id?.toString() ?? examCategories[0]?.id.toString() ?? '',
        subject_id: current_subject_id?.toString() || '',
        year: exam.year,
        is_active: exam.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(admin.exams.update(exam.id).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Past Question" />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 overflow-x-hidden">
                <div>
                    <h1 className="text-2xl font-bold">Edit Past Question</h1>
                    <p className="text-muted-foreground">Update past question details</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Past Question Details</CardTitle>
                        <CardDescription>Update the exam type, subject, and year for this paper</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Optional — auto-generated from exam type, subject, and year"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="exam_category_id">Exam Type *</Label>
                                    <Select
                                        value={data.exam_category_id}
                                        onValueChange={(value) => setData('exam_category_id', value)}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select exam type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {examCategories.map((category) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.exam_category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subject_id">Subject *</Label>
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
                            </div>

                            <div className="grid gap-2 max-w-xs">
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

                            <div className="flex flex-col-reverse sm:flex-row flex-wrap gap-2">
                                <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                    {processing ? 'Updating...' : 'Update Past Question'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                    className="w-full sm:w-auto"
                                >
                                    <a href={admin.exams.show(exam.id).url}>Cancel</a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
