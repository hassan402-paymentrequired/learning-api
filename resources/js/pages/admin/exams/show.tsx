import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    BookOpen,
    Copy,
    Download,
    Edit,
    Link2,
    Plus,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

interface Answer {
    id: number;
    answer_text: string;
    is_correct: boolean;
    order: string;
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    explanation: string | null;
    points: number;
    order: number;
    answers: Answer[];
    exams?: Array<{ id: number; title: string; year: number | null }>;
}

interface Exam {
    id: number;
    title: string;
    description: string | null;
    exam_type: 'JAMB' | 'DLI' | 'UNILAG' | 'GENERAL';
    subject: string | null;
    total_questions: number;
    year: number | null;
    is_active: boolean;
    questions: Question[];
}

interface RelatedExam {
    id: number;
    title: string;
    subject: string | null;
    year: number | null;
}

interface Props {
    exam: Exam;
    relatedExams?: RelatedExam[];
    import_errors?: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Past Questions', href: admin.exams.index().url },
    { title: 'Details', href: '#' },
];

export default function ShowExam({ exam, relatedExams = [], import_errors = [] }: Props) {
    const [uploadErrors, setUploadErrors] = useState<string[]>(import_errors);
    const [linkModalOpen, setLinkModalOpen] = useState(false);
    const [questionToLink, setQuestionToLink] = useState<Question | null>(null);
    const [linkExamIds, setLinkExamIds] = useState<string[]>([]);
    const [linkSubmitting, setLinkSubmitting] = useState(false);
    const [questionType, setQuestionType] = useState<
        'multiple_choice' | 'text_input'
    >('multiple_choice');
    const {
        data,
        setData,
        post,
        processing,
        errors: formErrors,
    } = useForm({
        file: null as File | null,
        question_type: 'multiple_choice' as 'multiple_choice' | 'text_input',
    });

    const handleRemove = (questionId: number) => {
        if (confirm('Remove this question from this past question paper? It will remain available in other linked years.')) {
            router.delete(
                admin.exams.questions.destroy({ exam: exam.id, question: questionId }).url,
            );
        }
    };

    const openLinkModal = (question: Question) => {
        setQuestionToLink(question);
        const alreadyLinked = (question.exams ?? [])
            .filter((linkedExam) => linkedExam.id !== exam.id)
            .map((linkedExam) => linkedExam.id.toString());
        setLinkExamIds(alreadyLinked);
        setLinkModalOpen(true);
    };

    const closeLinkModal = () => {
        setLinkModalOpen(false);
        setQuestionToLink(null);
        setLinkExamIds([]);
    };

    const confirmLink = () => {
        if (!questionToLink || linkExamIds.length === 0) return;
        setLinkSubmitting(true);
        router.post(
            `/admin/exams/${exam.id}/questions/${questionToLink.id}/link`,
            { exam_ids: linkExamIds.map((id) => parseInt(id, 10)) },
            {
                onFinish: () => setLinkSubmitting(false),
                onSuccess: () => closeLinkModal(),
            },
        );
    };

    const linkableExams = relatedExams.filter((relatedExam) => relatedExam.id !== exam.id);

    const handleBulkUpload = (e: React.FormEvent) => {
        e.preventDefault();
        setUploadErrors([]);

        if (!data.file) {
            setUploadErrors(['Please select a file to upload.']);
            return;
        }

        setData('question_type', questionType);

        post(admin.exams.questions.bulkUpload(exam.id).url, {
            forceFormData: true,
            data: {
                ...data,
                question_type: questionType,
            },
            onSuccess: () => {
                setData('file', null);
                // Reset file input
                const fileInput = document.getElementById(
                    'csv-file',
                ) as HTMLInputElement;
                if (fileInput) fileInput.value = '';
            },
            onError: (errors) => {
                if (errors.file) {
                    setUploadErrors([errors.file]);
                }
                if (errors.question_type) {
                    setUploadErrors([...uploadErrors, errors.question_type]);
                }
            },
        });
    };

    const downloadSample = () => {
        const url = new URL(admin.exams.questions.sample(exam.id).url);
        url.searchParams.set('question_type', questionType);
        window.location.href = url.toString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={exam.title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 overflow-x-hidden">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl sm:text-2xl font-bold break-words">{exam.title}</h1>
                        <p className="text-sm sm:text-base text-muted-foreground mt-1">
                            {exam.description || 'No description'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 shrink-0">
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-full sm:w-auto"
                            onClick={() => {
                                if (
                                    confirm(
                                        'Are you sure you want to duplicate this exam? All questions will be copied.',
                                    )
                                ) {
                                    router.post(
                                        `/admin/exams/${exam.id}/duplicate`,
                                        {},
                                        {
                                            onSuccess: () => {
                                                router.reload();
                                            },
                                        },
                                    );
                                }
                            }}
                        >
                            <Copy className="mr-2 h-4 w-4" />
                            Duplicate
                        </Button>
                        <Button variant="outline" size="sm" className="w-full sm:w-auto" asChild>
                            <Link href={admin.exams.edit(exam.id).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Exam
                            </Link>
                        </Button>
                        <Button size="sm" className="w-full sm:w-auto" asChild>
                            <Link
                                href={admin.exams.questions.create(exam.id).url}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Question
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">
                                Exam Type
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {exam.exam_type}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Past Question
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">
                                Questions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {exam.questions.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Total questions
                            </p>
                        </CardContent>
                    </Card>

                    {exam.year && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium">
                                    Year
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {exam.year}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Exam year
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Bulk Upload Questions</CardTitle>
                        <CardDescription>
                            Upload multiple questions at once using a CSV file
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                <Download className="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0" />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                        Need help with the format?
                                    </p>
                                    <p className="text-xs text-blue-700 dark:text-blue-300">
                                        Download a sample CSV template to see
                                        the required format
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={downloadSample}
                                    className="w-full sm:w-auto shrink-0"
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Download Sample
                                </Button>
                            </div>

                            <form
                                onSubmit={handleBulkUpload}
                                className="space-y-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="question_type">
                                        Question Type *
                                    </Label>
                                    <Select
                                        value={questionType}
                                        onValueChange={(
                                            value:
                                                | 'multiple_choice'
                                                | 'text_input',
                                        ) => {
                                            setQuestionType(value);
                                            setUploadErrors([]);
                                        }}
                                    >
                                        <SelectTrigger id="question_type">
                                            <SelectValue placeholder="Select question type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="multiple_choice">
                                                Multiple Choice
                                            </SelectItem>
                                            <SelectItem value="text_input">
                                                Text Input
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Select the type of questions you're
                                        uploading. The CSV format will differ
                                        based on your selection.
                                    </p>
                                    {formErrors.question_type && (
                                        <p className="text-xs text-red-600 dark:text-red-400">
                                            {formErrors.question_type}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="csv-file">CSV File *</Label>
                                    <Input
                                        id="csv-file"
                                        type="file"
                                        accept=".csv,.txt"
                                        onChange={(e) => {
                                            const file =
                                                e.target.files?.[0] || null;
                                            setData('file', file);
                                            setUploadErrors([]);
                                        }}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Upload a CSV file with questions.
                                        Maximum file size: 10MB
                                    </p>
                                    {formErrors.file && (
                                        <p className="text-xs text-red-600 dark:text-red-400">
                                            {formErrors.file}
                                        </p>
                                    )}
                                    {uploadErrors.length > 0 && (
                                        <Alert
                                            variant="destructive"
                                            className="mt-2"
                                        >
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertTitle>
                                                Upload Errors
                                            </AlertTitle>
                                            <AlertDescription>
                                                <ul className="mt-2 list-inside list-disc">
                                                    {uploadErrors.map(
                                                        (error, index) => (
                                                            <li
                                                                key={index}
                                                                className="text-sm"
                                                            >
                                                                {error}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing || !data.file}
                                >
                                    <Upload className="mr-2 h-4 w-4" />
                                    {processing
                                        ? 'Uploading...'
                                        : 'Upload Questions'}
                                </Button>
                            </form>

                            <div className="border-t pt-4">
                                <h4 className="mb-2 text-sm font-medium">
                                    CSV Format Requirements:
                                </h4>
                                {questionType === 'multiple_choice' ? (
                                    <ul className="list-inside list-disc space-y-1 text-xs text-muted-foreground">
                                        <li>
                                            Columns: Question Text, Answer A,
                                            Answer B, Answer C, Answer D, Answer
                                            E (Optional), Correct Answer
                                            (A/B/C/D/E), Explanation (Optional),
                                            Points, Order
                                        </li>
                                        <li>
                                            First row should be headers (will be
                                            skipped)
                                        </li>
                                        <li>
                                            At least 4 answers (A, B, C, D) are
                                            required
                                        </li>
                                        <li>Answer E is optional</li>
                                        <li>
                                            Correct Answer must be A, B, C, D,
                                            or E
                                        </li>
                                        <li>
                                            Points default to 1 if not specified
                                        </li>
                                        <li>
                                            Order will auto-increment if not
                                            specified
                                        </li>
                                    </ul>
                                ) : (
                                    <ul className="list-inside list-disc space-y-1 text-xs text-muted-foreground">
                                        <li>
                                            Columns: Question Text, Expected
                                            Answer, Alternative Answers
                                            (comma-separated, optional),
                                            Explanation (Optional), Points,
                                            Order
                                        </li>
                                        <li>
                                            First row should be headers (will be
                                            skipped)
                                        </li>
                                        <li>Expected Answer is required</li>
                                        <li>
                                            Alternative Answers can be
                                            comma-separated for multiple
                                            acceptable answers (e.g.,
                                            "abuja,ABUJA")
                                        </li>
                                        <li>
                                            Points default to 1 if not specified
                                        </li>
                                        <li>
                                            Order will auto-increment if not
                                            specified
                                        </li>
                                    </ul>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Questions</CardTitle>
                        <CardDescription>
                            Manage questions for this exam
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {exam.questions.length === 0 ? (
                                <div className="py-10 text-center text-muted-foreground">
                                    <BookOpen className="mx-auto mb-4 h-12 w-12 opacity-50" />
                                    <p>
                                        No questions yet. Add your first
                                        question to get started.
                                    </p>
                                </div>
                            ) : (
                                exam.questions.map((question) => (
                                    <Card key={question.id} className="min-w-0 overflow-hidden">
                                        <CardHeader>
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="flex-1 min-w-0">
                                                    <div className="mb-2 flex flex-wrap items-center gap-2">
                                                        <Badge variant="outline">
                                                            Q{question.order}
                                                        </Badge>
                                                        <Badge variant="secondary">
                                                            {question.points}{' '}
                                                            point
                                                            {question.points !==
                                                            1
                                                                ? 's'
                                                                : ''}
                                                        </Badge>
                                                    </div>
                                                    <CardTitle className="text-sm sm:text-base break-words">
                                                        {question.question_text}
                                                    </CardTitle>
                                                    {(question.exams?.length ?? 0) > 0 && (
                                                        <div className="mt-2 flex flex-wrap gap-1">
                                                            {question.exams?.map((linkedExam) => (
                                                                <Badge
                                                                    key={linkedExam.id}
                                                                    variant={linkedExam.id === exam.id ? 'default' : 'secondary'}
                                                                >
                                                                    {linkedExam.year ?? linkedExam.title}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex gap-2 shrink-0">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        className="shrink-0"
                                                    >
                                                        <Link
                                                            href={
                                                                admin.exams.questions.edit(
                                                                    { exam: exam.id, question: question.id },
                                                                ).url
                                                            }
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => openLinkModal(question)}
                                                        className="shrink-0"
                                                        title="Link to other years"
                                                    >
                                                        <Link2 className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleRemove(question.id)}
                                                        className="shrink-0"
                                                        title="Remove from this paper"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-2">
                                                {question.answers.map(
                                                    (answer) => (
                                                        <div
                                                            key={answer.id}
                                                            className={`rounded border p-2 ${
                                                                answer.is_correct
                                                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                                                                    : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/20'
                                                            }`}
                                                        >
                                                            <div className="flex items-start sm:items-center gap-2 min-w-0">
                                                                <span className="font-medium shrink-0">
                                                                    {
                                                                        answer.order
                                                                    }
                                                                    .
                                                                </span>
                                                                <span className="break-words min-w-0 flex-1">
                                                                    {
                                                                        answer.answer_text
                                                                    }
                                                                </span>
                                                                {answer.is_correct && (
                                                                    <Badge
                                                                        variant="default"
                                                                        className="ml-auto shrink-0"
                                                                    >
                                                                        Correct
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                                {question.explanation && (
                                                    <div className="mt-2 rounded border border-blue-200 bg-blue-50 p-2 dark:border-blue-800 dark:bg-blue-900/20 min-w-0 overflow-hidden">
                                                        <p className="text-sm break-words">
                                                            <strong>
                                                                Explanation:
                                                            </strong>{' '}
                                                            {
                                                                question.explanation
                                                            }
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Link Question Modal */}
            <Dialog open={linkModalOpen} onOpenChange={(open) => !open && closeLinkModal()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Link to Past Question Years</DialogTitle>
                        <DialogDescription>
                            {questionToLink ? (
                                <>
                                    Select additional past question papers for this question.
                                    Edits apply everywhere it is linked.
                                </>
                            ) : null}
                        </DialogDescription>
                    </DialogHeader>
                    {linkableExams.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No other papers for this subject yet. Create another year first.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            <Label htmlFor="link-exams">Past question papers</Label>
                            <MultiSelect
                                id="link-exams"
                                options={linkableExams.map((relatedExam) => ({
                                    value: relatedExam.id.toString(),
                                    label: `${relatedExam.title}${relatedExam.year ? ` (${relatedExam.year})` : ''}`,
                                }))}
                                value={linkExamIds}
                                onChange={setLinkExamIds}
                                placeholder="Select years to link..."
                            />
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={closeLinkModal}>
                            Cancel
                        </Button>
                        <Button
                            onClick={confirmLink}
                            disabled={
                                linkExamIds.length === 0 ||
                                linkSubmitting ||
                                linkableExams.length === 0
                            }
                        >
                            {linkSubmitting ? 'Linking...' : 'Link Question'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
