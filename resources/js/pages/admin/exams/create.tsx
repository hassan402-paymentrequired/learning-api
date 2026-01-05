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
import admin from '@/routes/admin';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exams', href: admin.exams.index().url },
    { title: 'Create Exam', href: '#' },
];

export default function CreateExam() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        type: 'practice' as 'practice' | 'past_question',
        exam_type: 'JAMB' as 'JAMB' | 'UNILAG' | 'DLI' | 'GENERAL',
        subject: '',
        duration: 60,
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
                    <p className="text-muted-foreground">Add a new practice exam or past question</p>
                </div>

                <Card className="border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-900/10">
                    <CardHeader>
                        <CardTitle className="text-sm">Student Flow Requirements</CardTitle>
                        <CardDescription className="text-xs">
                            Based on the student app flow: Students choose JAMB/DLI → Select Subject → Choose Practice/Past Questions → Select Question Count → Set Duration (max 120 min)
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-xs text-muted-foreground space-y-1">
                        <p>• Practice exams require a subject (students must select one)</p>
                        <p>• Maximum duration is 120 minutes (2 hours) per student flow</p>
                        <p>• Practice sessions are limited to 4 per subject (enforced in app)</p>
                        <p>• Exam type determines if it appears in JAMB Practice or DLI Practice</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Exam Details</CardTitle>
                        <CardDescription>Fill in the information for the new exam</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title *</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g., JAMB Mathematics 2024"
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
                                    placeholder="Brief description of the exam"
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type *</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value: 'practice' | 'past_question') => setData('type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="practice">Practice</SelectItem>
                                            <SelectItem value="past_question">Past Question</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>

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
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="subject">
                                        Subject {data.type === 'practice' && '*'}
                                    </Label>
                                    <Input
                                        id="subject"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        placeholder="e.g., Mathematics"
                                        required={data.type === 'practice'}
                                    />
                                    <InputError message={errors.subject} />
                                    {data.type === 'practice' && (
                                        <p className="text-xs text-muted-foreground">
                                            Required for practice exams. Students must select a subject.
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="duration">Duration (minutes) *</Label>
                                    <Input
                                        id="duration"
                                        type="number"
                                        min="1"
                                        max="120"
                                        value={data.duration}
                                        onChange={(e) => setData('duration', parseInt(e.target.value) || 60)}
                                        required
                                    />
                                    <InputError message={errors.duration} />
                                    <p className="text-xs text-muted-foreground">
                                        Maximum 120 minutes (2 hours) per student flow requirements
                                    </p>
                                </div>
                            </div>

                            {data.type === 'past_question' && (
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
                                </div>
                            )}

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
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
