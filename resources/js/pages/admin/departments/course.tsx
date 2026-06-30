import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, Layers, MoreVertical, Plus, Power, PowerOff, Trash2 } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ViewQuestionModal from '@/components/view-question-modal';
import axios from 'axios';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';

interface Department {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
}

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    exam_types: string[] | null;
    is_active: boolean;
    order: number;
    questions_count: number;
    tests_count: number;
}

interface Question {
    id: number;
    question_text: string;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    exam_types: string[];
    is_active: boolean;
    answers_count: number;
}

interface Props {
    department: Department;
    subject: Subject;
    questions: {
        data: Question[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

function questionTypeBadge(type: string) {
    const colors: Record<string, string> = {
        multiple_choice: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        text_input: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        numeric_input: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        true_false: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    };

    return colors[type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
}

export default function DepartmentCourseShow({ department, subject, questions }: Props) {
    const departmentUrl = `/admin/departments/${department.id}`;
    const courseUrl = `/admin/departments/${department.id}/courses/${subject.id}`;
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [questionToDelete, setQuestionToDelete] = useState<number | null>(null);
    const [viewModalOpen, setViewModalOpen] = useState(false);
    const [viewingQuestion, setViewingQuestion] = useState<Question | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Departments', href: admin.departments.index().url },
        { title: department.name, href: departmentUrl },
        { title: subject.name, href: '#' },
    ];

    const handleToggleCourseActive = () => {
        router.post(`/admin/subjects/${subject.id}/toggle-active`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Course status updated successfully'),
            onError: () => toast.error('Failed to update course status'),
        });
    };

    const handleToggleQuestionActive = (questionId: number) => {
        router.post(`/admin/questions/${questionId}/toggle-active`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Question status updated successfully'),
            onError: () => toast.error('Failed to update question status'),
        });
    };

    const handleViewQuestion = async (questionId: number) => {
        setViewModalOpen(true);
        setViewingQuestion(null);
        try {
            const response = await axios.get(admin.questions.show({ question: questionId }).url);
            if (response.data.success) {
                setViewingQuestion(response.data.data);
            } else {
                toast.error('Failed to load question details');
                setViewModalOpen(false);
            }
        } catch {
            toast.error('Failed to load question details');
            setViewModalOpen(false);
        }
    };

    const handleDelete = (questionId: number) => {
        setQuestionToDelete(questionId);
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!questionToDelete) return;

        router.delete(admin.questions.destroy({ question: questionToDelete }).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Question deleted successfully');
                setDeleteDialogOpen(false);
                setQuestionToDelete(null);
            },
            onError: () => toast.error('Failed to delete question'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${subject.name} — ${department.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">{subject.name}</h1>
                        <p className="text-muted-foreground">
                            {subject.questions_count} question{subject.questions_count !== 1 ? 's' : ''} in {department.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={admin.subjects.tests.index({ subject: subject.id }).url}>
                                <Layers className="mr-2 h-4 w-4" />
                                Manage Tests
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={admin.subjects.edit(subject.id).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Course
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={handleToggleCourseActive}>
                            {subject.is_active ? (
                                <>
                                    <PowerOff className="mr-2 h-4 w-4" />
                                    Deactivate
                                </>
                            ) : (
                                <>
                                    <Power className="mr-2 h-4 w-4" />
                                    Activate
                                </>
                            )}
                        </Button>
                        <Button asChild>
                            <Link
                                href={`${admin.questions.create().url}?subject_id=${subject.id}&department_id=${department.id}`}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Question
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Status</CardDescription>
                            <CardTitle className="text-lg">
                                {subject.is_active ? (
                                    <span className="text-green-600 dark:text-green-400">Active</span>
                                ) : (
                                    <span className="text-muted-foreground">Inactive</span>
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Questions</CardDescription>
                            <CardTitle className="text-lg">{subject.questions_count}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Tests</CardDescription>
                            <CardTitle className="text-lg">{subject.tests_count}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Sort Order</CardDescription>
                            <CardTitle className="text-lg">{subject.order}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Questions</CardTitle>
                        <CardDescription>All questions for this course</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {questions.data.length === 0 ? (
                            <div className="py-10 text-center">
                                <p className="text-muted-foreground">No questions found for this course.</p>
                                <Button asChild className="mt-4">
                                    <Link
                                        href={`${admin.questions.create().url}?subject_id=${subject.id}&department_id=${department.id}`}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add First Question
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[45%]">Question</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Answers</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {questions.data.map((question) => (
                                        <TableRow key={question.id}>
                                            <TableCell className="align-top">
                                                <p className="line-clamp-2 font-medium">
                                                    {question.question_text}
                                                </p>
                                                {question.exam_types?.length > 0 && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {question.exam_types.join(', ')}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <span className={`inline-flex rounded px-2 py-0.5 text-xs ${questionTypeBadge(question.question_type)}`}>
                                                    {question.question_type.replace(/_/g, ' ')}
                                                </span>
                                            </TableCell>
                                            <TableCell className="align-top">{question.answers_count}</TableCell>
                                            <TableCell className="align-top">
                                                <span className={`inline-flex rounded px-2 py-0.5 text-xs ${question.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                                    {question.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="align-top text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                            <MoreVertical className="h-4 w-4" />
                                                            <span className="sr-only">Open menu</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => handleViewQuestion(question.id)}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            View Question
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={admin.questions.edit({ question: question.id }).url}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => handleToggleQuestionActive(question.id)}>
                                                            {question.is_active ? (
                                                                <>
                                                                    <PowerOff className="mr-2 h-4 w-4" />
                                                                    Deactivate
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Power className="mr-2 h-4 w-4" />
                                                                    Activate
                                                                </>
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() => handleDelete(question.id)}
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {questions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {questions.current_page} of {questions.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={questions.current_page === 1}
                                onClick={() => router.get(courseUrl, { page: questions.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={questions.current_page === questions.last_page}
                                onClick={() => router.get(courseUrl, { page: questions.current_page + 1 })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}

                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Question</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this question? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button variant="destructive" onClick={confirmDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <ViewQuestionModal
                    question={viewingQuestion}
                    open={viewModalOpen}
                    onOpenChange={(open) => {
                        setViewModalOpen(open);
                        if (!open) setViewingQuestion(null);
                    }}
                    onToggleActive={handleToggleQuestionActive}
                    onDelete={(questionId) => {
                        setQuestionToDelete(questionId);
                        setDeleteDialogOpen(true);
                        setViewModalOpen(false);
                    }}
                />
            </div>
        </AppLayout>
    );
}
