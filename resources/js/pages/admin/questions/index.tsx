/* eslint-disable @typescript-eslint/no-explicit-any */
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { router, useForm, Link, Head } from '@inertiajs/react';
import { Search, FileQuestion, Plus, Upload, Download, Edit, Trash2, Power, PowerOff, Eye, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import ViewQuestionModal from '@/components/view-question-modal';
import axios from 'axios';

interface Answer {
    id: number;
    answer_text: string;
    is_correct: boolean;
    order: string;
}

interface Question {
    id: number;
    question_text: string;
    image?: string | null;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    exam_types: string[];
    is_active: boolean;
    explanation?: string | null;
    expected_answer?: string | null;
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
    answers?: Answer[];
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
    questions: {
        data: Question[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    subjects: Subject[];
    examCategories: ExamCategory[];
    filters: {
        search?: string;
        subject_id?: string;
        exam_type?: string;
        question_type?: string;
    };
}

export default function QuestionsIndex({ questions, subjects, examCategories, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [subjectId, setSubjectId] = useState(filters.subject_id || '');
    const [examType, setExamType] = useState(filters.exam_type || '');
    const [questionType, setQuestionType] = useState(filters.question_type || '');
    const [selectedQuestions, setSelectedQuestions] = useState<number[]>([]);
    const [bulkUploadOpen, setBulkUploadOpen] = useState(false);
    const [selectedQuestionType, setSelectedQuestionType] = useState<'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false'>('multiple_choice');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [questionToDelete, setQuestionToDelete] = useState<number | null>(null);
    const [viewModalOpen, setViewModalOpen] = useState(false);
    const [viewingQuestion, setViewingQuestion] = useState<Question | null>(null);
    const [loadingQuestion, setLoadingQuestion] = useState(false);

    const {  setData, post, processing, errors } = useForm({
        file: null as File | null,
        question_type: 'multiple_choice' as 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false',
    });

    const handleFilter = () => {
        router.get(admin.questions.index().url, {
            search: search || undefined,
            subject_id: subjectId || undefined,
            exam_type: examType || undefined,
            question_type: questionType || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSelectQuestion = (questionId: number) => {
        if (selectedQuestions.includes(questionId)) {
            setSelectedQuestions(selectedQuestions.filter(id => id !== questionId));
        } else {
            setSelectedQuestions([...selectedQuestions, questionId]);
        }
    };

    const handleBulkUpload = (e: React.FormEvent) => {
        e.preventDefault();
        post(admin.questions.bulkUpload().url, {
            forceFormData: true,
            onSuccess: () => {
                setBulkUploadOpen(false);
                setData({ file: null, question_type: 'multiple_choice' });
            },
        });
    };

    const handleDownloadSample = (type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false') => {
        window.location.href = admin.questions.sample({ question_type: type }).url;
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

    const handleViewQuestion = async (questionId: number) => {
        setLoadingQuestion(true);
        setViewModalOpen(true);
        try {
            const response = await axios.get(admin.questions.show({ question: questionId }).url);
            if (response.data.success) {
                setViewingQuestion(response.data.data);
            } else {
                toast.error('Failed to load question details');
                setViewModalOpen(false);
            }
        } catch (error) {
            toast.error('Failed to load question details');
            setViewModalOpen(false);
        } finally {
            setLoadingQuestion(false);
        }
    };

    const getQuestionTypeBadge = (type: string) => {
        const colors = {
            'multiple_choice': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'text_input': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'numeric_input': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            'true_false': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };
        return colors[type as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    return (
        <AppLayout>
            <Head title="Questions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Questions</h1>
                        <p className="text-muted-foreground">Manage all questions in the question bank</p>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-2">
                        <Dialog open={bulkUploadOpen} onOpenChange={setBulkUploadOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Upload className="mr-2 h-4 w-4" />
                                    Bulk Upload
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Bulk Upload Questions</DialogTitle>
                                    <DialogDescription>
                                        Upload questions from a CSV file. Download a sample template first to see the format.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleBulkUpload} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="question_type">Question Type</Label>
                                        <Select
                                            value={selectedQuestionType}
                                            onValueChange={(value) => {
                                                setSelectedQuestionType(value as any);
                                                setData('question_type', value as any);
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
                                        <Label htmlFor="file">CSV File</Label>
                                        <Input
                                            id="file"
                                            type="file"
                                            accept=".csv,.txt"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0] || null;
                                                setData('file', file);
                                            }}
                                            required
                                        />
                                        {errors.file && (
                                            <p className="text-sm text-red-500">{errors.file}</p>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => handleDownloadSample(selectedQuestionType)}
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            Download Sample
                                        </Button>
                                        <Button type="submit" disabled={processing} className="flex-1">
                                            {processing ? 'Uploading...' : 'Upload'}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Button asChild>
                            <Link href={admin.questions.create().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Create Question
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter questions by search, subject, exam type, or question type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search questions..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 flex-1 lg:flex-initial">
                                <Select value={subjectId || 'all'} onValueChange={setSubjectId}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Subjects" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Subjects</SelectItem>
                                        {subjects.map((subject) => (
                                            <SelectItem key={subject.id} value={subject.id.toString()}>
                                                {subject.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={examType || 'all'} onValueChange={setExamType}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Exam Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Exam Types</SelectItem>
                                        {examCategories.map((category) => (
                                            <SelectItem key={category.id} value={category.slug}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={questionType || 'all'} onValueChange={setQuestionType}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Question Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Question Types</SelectItem>
                                        <SelectItem value="multiple_choice">Multiple Choice</SelectItem>
                                        <SelectItem value="text_input">Text Input</SelectItem>
                                        <SelectItem value="numeric_input">Numeric Input</SelectItem>
                                        <SelectItem value="true_false">True/False</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button onClick={handleFilter} className="w-full sm:w-auto">Filter</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {questions.data.map((question) => (
                        <Card key={question.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2 flex-1">
                                        <Checkbox
                                            checked={selectedQuestions.includes(question.id)}
                                            onCheckedChange={() => handleSelectQuestion(question.id)}
                                        />
                                        <div className="flex-1">
                                            <CardTitle className="text-lg line-clamp-2">
                                                {question.question_text.substring(0, 80)}{question.question_text.length > 80 ? '...' : ''}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {question.subject?.name || 'No Subject'}
                                                {question.exam_types && question.exam_types.length > 0 && 
                                                    ` • ${question.exam_types.join(', ')}`
                                                }
                                            </CardDescription>
                                        </div>
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
                                    {question.exams && question.exams.length > 0 && (
                                        <div className="text-sm text-muted-foreground">
                                            Past papers: {question.exams.map((pastExam: { title: string; year?: number | null }) => pastExam.year ?? pastExam.title).join(', ')}
                                        </div>
                                    )}
                                    {question.answers_count !== undefined && question.answers_count > 0 && (
                                        <div className="text-sm text-muted-foreground">
                                            {question.answers_count} answer{question.answers_count !== 1 ? 's' : ''}
                                        </div>
                                    )}
                                    <div className="flex items-center justify-end gap-2 pt-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleViewQuestion(question.id)}
                                        >
                                            <Eye className="mr-2 h-4 w-4" />
                                            View
                                        </Button>
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
                            <p className="text-muted-foreground">No questions found.</p>
                        </CardContent>
                    </Card>
                )}

                {questions.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            disabled={questions.current_page === 1}
                            onClick={() => router.get(admin.questions.index().url, { page: questions.current_page - 1, ...filters })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Page {questions.current_page} of {questions.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={questions.current_page === questions.last_page}
                            onClick={() => router.get(admin.questions.index().url, { page: questions.current_page + 1, ...filters })}
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

                {/* View Question Modal */}
                <ViewQuestionModal
                    question={viewingQuestion}
                    open={viewModalOpen}
                    onOpenChange={setViewModalOpen}
                    onToggleActive={handleToggleActive}
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
