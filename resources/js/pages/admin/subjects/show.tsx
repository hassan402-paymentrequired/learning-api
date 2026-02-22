import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { router, Link, Head, useForm } from '@inertiajs/react';
import { ArrowLeft, FileQuestion, Edit, Trash2, Power, PowerOff, Plus, Upload, Download, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import { type BreadcrumbItem } from '@/types';

interface Question {
    id: number;
    question_text: string;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    exam_types: string[];
    is_active: boolean;
    subject: {
        id: number;
        name: string;
    } | null;
    exam: {
        id: number;
        title: string;
        exam_type: string;
    } | null;
    answers_count?: number;
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
}

interface Props {
    subject: Subject;
    questions: {
        data: Question[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subjects', href: admin.subjects.index().url },
    { title: 'Subject Questions', href: '#' },
];

export default function SubjectShow({ subject, questions }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [questionToDelete, setQuestionToDelete] = useState<number | null>(null);
    const [bulkUploadOpen, setBulkUploadOpen] = useState(false);
    const [questionType, setQuestionType] = useState<'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false'>('multiple_choice');
    const [examType, setExamType] = useState<'JAMB' | 'DLI'>('JAMB');
    const [uploadErrors, setUploadErrors] = useState<string[]>([]);

    const { data, setData, post, processing, errors: formErrors } = useForm({
        file: null as File | null,
        question_type: 'multiple_choice',
        exam_type: 'JAMB',
    });

    const getQuestionTypeBadge = (type: string) => {
        const colors = {
            'multiple_choice': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'text_input': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'numeric_input': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            'true_false': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };
        return colors[type as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const handleToggleActive = (questionId: number) => {
        router.post(`/admin/questions/${questionId}/toggle-active`, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Question status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update question status');
            },
        });
    };

    const handleDelete = (questionId: number) => {
        setQuestionToDelete(questionId);
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (questionToDelete) {
            router.delete(admin.questions.destroy({ question: questionToDelete }).url, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Question deleted successfully');
                    setDeleteDialogOpen(false);
                    setQuestionToDelete(null);
                },
                onError: () => {
                    toast.error('Failed to delete question');
                },
            });
        }
    };

    const handleBulkUpload = (e: React.FormEvent) => {
        e.preventDefault();
        setUploadErrors([]);

        if (!data.file) {
            setUploadErrors(['Please select a file to upload.']);
            return;
        }

        setData('question_type', questionType);
        setData('exam_type', examType);

        const url = `/admin/subjects/${subject.id}/questions/bulk-upload`;
        post(url, {
            forceFormData: true,
            data: {
                ...data,
                question_type: questionType,
                exam_type: examType,
            },
            onSuccess: () => {
                setData('file', null);
                setBulkUploadOpen(false);
                // Reset file input
                const fileInput = document.getElementById(
                    'bulk-upload-file',
                ) as HTMLInputElement;
                if (fileInput) fileInput.value = '';
                toast.success('Questions uploaded successfully');
            },
            onError: (errors) => {
                if (errors.file) {
                    setUploadErrors([errors.file]);
                }
                if (errors.question_type) {
                    setUploadErrors([...uploadErrors, errors.question_type]);
                }
                if (errors.bulk_upload) {
                    setUploadErrors([...uploadErrors, errors.bulk_upload]);
                }
            },
        });
    };

    const downloadSample = () => {
        const url = `/admin/subjects/${subject.id}/questions/sample?question_type=${questionType}&exam_type=${examType}`;
        window.location.href = url;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${subject.name} - Questions`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-bold">{subject.name}</h1>
                            <p className="text-muted-foreground">
                                {subject.questions_count} question{subject.questions_count !== 1 ? 's' : ''} in this subject
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/admin/subjects/${subject.id}/tests`}>
                                Manage Tests
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={admin.subjects.edit(subject.id).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Subject
                            </Link>
                        </Button>
                        <Dialog open={bulkUploadOpen} onOpenChange={setBulkUploadOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Upload className="mr-2 h-4 w-4" />
                                    Bulk Upload Questions
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-[500px]">
                                <DialogHeader>
                                    <DialogTitle>Bulk Upload Questions</DialogTitle>
                                    <DialogDescription>
                                        Upload questions for <strong>{subject.name}</strong> from CSV, XLSX, or DOCX file.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleBulkUpload} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="question_type">Question Type</Label>
                                        <Select
                                            value={questionType}
                                            onValueChange={(value: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false') => {
                                                setQuestionType(value);
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="multiple_choice">Multiple Choice</SelectItem>
                                                <SelectItem value="text_input">Text Input</SelectItem>
                                                <SelectItem value="numeric_input">Numeric Input</SelectItem>
                                                <SelectItem value="true_false">True/False</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="exam_type">Exam Type</Label>
                                        <Select
                                            value={examType}
                                            onValueChange={(value: 'JAMB' | 'DLI') => {
                                                setExamType(value);
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="JAMB">JAMB</SelectItem>
                                                <SelectItem value="DLI">DLI</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="bulk-upload-file">File (CSV, XLSX, or DOCX)</Label>
                                        <Input
                                            id="bulk-upload-file"
                                            type="file"
                                            accept=".csv,.txt,.xlsx,.xls,.docx"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0] || null;
                                                setData('file', file);
                                                setUploadErrors([]);
                                            }}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Supported formats: CSV, TXT, XLSX, XLS, DOCX (Max 10MB)
                                        </p>
                                    </div>
                                    {uploadErrors.length > 0 && (
                                        <div className="rounded-md bg-destructive/15 p-3">
                                            <ul className="list-disc list-inside text-sm text-destructive">
                                                {uploadErrors.map((error, index) => (
                                                    <li key={index}>{error}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {formErrors.file && (
                                        <div className="rounded-md bg-destructive/15 p-3">
                                            <p className="text-sm text-destructive">{formErrors.file}</p>
                                        </div>
                                    )}
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={downloadSample}
                                            className="flex-1"
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            Download Sample
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing || !data.file}
                                            className="flex-1"
                                        >
                                            {processing ? 'Uploading...' : 'Upload'}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Button asChild>
                            <Link href={admin.questions.create().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Question
                            </Link>
                        </Button>
                    </div>
                </div>

                {subject.description && (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">{subject.description}</p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {questions.data.map((question) => (
                        <Card key={question.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <CardTitle className="text-lg line-clamp-2">
                                            {question.question_text.substring(0, 80)}{question.question_text.length > 80 ? '...' : ''}
                                        </CardTitle>
                                        <CardDescription className="mt-1">
                                            {question.exam_types && question.exam_types.length > 0 &&
                                                question.exam_types.join(', ')
                                            }
                                        </CardDescription>
                                    </div>
                                    <span className={`px-2 py-1 text-xs rounded ${getQuestionTypeBadge(question.question_type)}`}>
                                        {question.question_type.replace('_', ' ')}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-sm">
                                        <FileQuestion className="h-4 w-4 text-muted-foreground" />
                                        <span className={`px-2 py-0.5 text-xs rounded ${question.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                            {question.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    {question.answers_count !== undefined && question.answers_count > 0 && (
                                        <div className="text-sm text-muted-foreground">
                                            {question.answers_count} answer{question.answers_count !== 1 ? 's' : ''}
                                        </div>
                                    )}
                                    <div className="flex justify-end pt-2">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreVertical className="h-4 w-4" />
                                                    <span className="sr-only">Open menu</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={admin.questions.edit({ question: question.id }).url}>
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleToggleActive(question.id)}>
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
                                                    variant="destructive"
                                                >
                                                    <Trash2 className="mr-2 h-4 w-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {questions.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No questions found for this subject.</p>
                            <Button asChild className="mt-4">
                                <Link href={admin.questions.create().url}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add First Question
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {questions.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            disabled={questions.current_page === 1}
                            onClick={() => router.get(admin.subjects.show({ subject: subject.id }).url, { page: questions.current_page - 1 })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Page {questions.current_page} of {questions.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={questions.current_page === questions.last_page}
                            onClick={() => router.get(admin.subjects.show({ subject: subject.id }).url, { page: questions.current_page + 1 })}
                        >
                            Next
                        </Button>
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
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
            </div>
        </AppLayout>
    );
}
