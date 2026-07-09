import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';

interface Department {
    id: number;
    name: string;
    is_active: boolean;
}

interface LinkedDepartment {
    id: number;
    name: string;
    is_active: boolean;
}

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    exam_types: string[] | null;
    is_active: boolean;
    department_ids: number[];
    linked_departments: LinkedDepartment[];
}

interface ExamCategory {
    id: number;
    name: string;
    slug: string;
    flow_type: 'standard' | 'departmental';
}

interface Props {
    subject: Subject;
    departments: Department[];
    examCategories: ExamCategory[];
    returnTo?: string | null;
}

export default function EditSubject({ subject, departments, examCategories, returnTo }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: subject.name,
        description: subject.description || '',
        exam_types: (subject.exam_types && Array.isArray(subject.exam_types))
            ? subject.exam_types
            : (subject.exam_types ? [subject.exam_types] : []),
        department_ids: subject.department_ids ?? [],
        is_active: subject.is_active,
    });

    const hasDepartmentalFlow = examCategories
        .filter((cat) => data.exam_types.includes(cat.slug))
        .some((cat) => cat.flow_type === 'departmental');

    const backHref = returnTo || admin.subjects.index().url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Subjects', href: admin.subjects.index().url },
        { title: subject.name, href: '#' },
    ];

    const toggleDepartment = (departmentId: number, checked: boolean) => {
        if (checked) {
            setData('department_ids', [...data.department_ids, departmentId]);
            return;
        }

        setData(
            'department_ids',
            data.department_ids.filter((id) => id !== departmentId),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(admin.subjects.update(subject.id).url, {
            onSuccess: () => {
                if (returnTo) {
                    router.visit(returnTo);
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Subject: ${subject.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="outline" size="sm">
                            <Link href={backHref}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Edit Course</h1>
                            <p className="text-muted-foreground">{subject.name}</p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Course details</CardTitle>
                            <CardDescription>Name, description, and availability</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Course Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., GST 101, Mathematics"
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
                                    placeholder="Optional description"
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Exam Types *</Label>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {examCategories.map((category) => (
                                        <div key={category.id} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`exam_type_${category.slug}`}
                                                checked={data.exam_types.includes(category.slug)}
                                                onCheckedChange={(checked) => {
                                                    const newExamTypes = checked
                                                        ? [...data.exam_types, category.slug]
                                                        : data.exam_types.filter((t) => t !== category.slug);

                                                    setData('exam_types', newExamTypes);

                                                    if (!checked && category.flow_type === 'departmental') {
                                                        const stillHasDepartmental = examCategories
                                                            .filter((cat) => newExamTypes.includes(cat.slug))
                                                            .some((cat) => cat.flow_type === 'departmental');

                                                        if (!stillHasDepartmental) {
                                                            setData('department_ids', []);
                                                        }
                                                    }
                                                }}
                                            />
                                            <Label htmlFor={`exam_type_${category.slug}`} className="font-normal cursor-pointer">
                                                {category.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {errors.exam_types && (
                                    <p className="text-sm text-red-500">{errors.exam_types}</p>
                                )}
                            </div>

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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Department membership</CardTitle>
                            <CardDescription>
                                Link this course to one or more departments. The same course can appear in Year 1, Year 2, etc.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {hasDepartmentalFlow ? (
                                <>
                                    {(subject.linked_departments?.length ?? 0) > 0 && (
                                        <div className="space-y-2">
                                            <Label>Currently linked to</Label>
                                            <div className="flex flex-wrap gap-2">
                                                {subject.linked_departments.map((dept) => (
                                                    <Link
                                                        key={dept.id}
                                                        href={`/admin/departments/${dept.id}`}
                                                        className="inline-flex items-center rounded-md border px-2.5 py-1 text-sm hover:bg-muted"
                                                    >
                                                        {dept.name}
                                                        {!dept.is_active && (
                                                            <span className="ml-1 text-xs text-muted-foreground">(inactive)</span>
                                                        )}
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label>Departments *</Label>
                                        {departments.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No departments yet. Create one from the Departments page first.
                                            </p>
                                        ) : (
                                            <div className="max-h-64 space-y-2 overflow-y-auto rounded-md border p-3">
                                                {departments.map((dept) => (
                                                    <div key={dept.id} className="flex items-center space-x-2">
                                                        <Checkbox
                                                            id={`department_${dept.id}`}
                                                            checked={data.department_ids.includes(dept.id)}
                                                            onCheckedChange={(checked) =>
                                                                toggleDepartment(dept.id, checked === true)
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`department_${dept.id}`}
                                                            className="font-normal cursor-pointer flex-1"
                                                        >
                                                            {dept.name}
                                                            {!dept.is_active && (
                                                                <span className="ml-1 text-xs text-muted-foreground">(inactive)</span>
                                                            )}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Select all departments where this course should appear.
                                        </p>
                                        {errors.department_ids && (
                                            <p className="text-sm text-red-500">{errors.department_ids}</p>
                                        )}
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Select a departmental exam type (e.g. DLI, UNILAG) to link this course to departments.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="lg:col-span-2 flex gap-2">
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={backHref}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
