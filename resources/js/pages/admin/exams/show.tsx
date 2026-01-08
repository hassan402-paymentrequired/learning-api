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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

interface Props {
    exam: Exam;
    import_errors?: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exams', href: admin.exams.index().url },
    { title: 'Exam Details', href: '#' },
];

export default function ShowExam({ exam, import_errors = [] }: Props) {
    const [uploadErrors, setUploadErrors] = useState<string[]>(import_errors);
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

    const handleDelete = (questionId: number) => {
        if (confirm('Are you sure you want to delete this question?')) {
            router.delete(
                admin.exams.questions.destroy(exam?.id, questionId).url,
            );
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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{exam.title}</h1>
                        <p className="text-muted-foreground">
                            {exam.description || 'No description'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
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
                        <Button variant="outline" asChild>
                            <Link href={admin.exams.edit(exam.id).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Exam
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={admin.exams.questions.create(exam.id).url}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Question
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
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
                            <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                <Download className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                <div className="flex-1">
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
                                    <Card key={question.id}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <div className="mb-2 flex items-center gap-2">
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
                                                    <CardTitle className="text-base">
                                                        {question.question_text}
                                                    </CardTitle>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                admin.exams.questions.edit(
                                                                    [
                                                                        exam.id,
                                                                        question.id,
                                                                    ],
                                                                ).url
                                                            }
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                question.id,
                                                            )
                                                        }
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
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        answer.order
                                                                    }
                                                                    .
                                                                </span>
                                                                <span>
                                                                    {
                                                                        answer.answer_text
                                                                    }
                                                                </span>
                                                                {answer.is_correct && (
                                                                    <Badge
                                                                        variant="default"
                                                                        className="ml-auto"
                                                                    >
                                                                        Correct
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                                {question.explanation && (
                                                    <div className="mt-2 rounded border border-blue-200 bg-blue-50 p-2 dark:border-blue-800 dark:bg-blue-900/20">
                                                        <p className="text-sm">
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
        </AppLayout>
    );
}
