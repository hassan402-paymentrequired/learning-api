/* eslint-disable @typescript-eslint/no-explicit-any */
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { MultiSelect } from '@/components/ui/multi-select';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useEffect } from 'react';

interface Subject {
    id: number;
    name: string;
    department_id: number | null;
    exam_types: string[] | null;
}

interface Department {
    id: number;
    name: string;
}

interface SubjectTest {
    id: number;
    subject_id: number;
    name: string;
    order: number;
}

interface ExamCategory {
    id: number;
    name: string;
    slug: string;
    flow_type: 'standard' | 'departmental';
}

interface PastQuestionExam {
    id: number;
    title: string;
    subject: string | null;
    year: number | null;
    exam_type: string;
}

interface Props {
    subjects: Subject[];
    departments: Department[];
    subjectTests: SubjectTest[];
    examCategories: ExamCategory[];
    pastQuestionExams: PastQuestionExam[];
    defaults?: {
        subject_id: string;
        department_id: number | null;
        exam_types: string[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Questions', href: admin.questions.index().url },
    { title: 'Create Question', href: '#' },
];

export default function CreateQuestion({
    subjects,
    departments,
    subjectTests,
    examCategories,
    pastQuestionExams,
    defaults = { subject_id: '', department_id: null, exam_types: [] },
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        department_id: defaults.department_id,
        subject_id: defaults.subject_id,
        question_text: '',
        image: null as File | null,
        question_type: 'multiple_choice' as
            | 'multiple_choice'
            | 'text_input'
            | 'numeric_input'
            | 'true_false',
        explanation: '',
        expected_answer: '',
        exam_types: defaults.exam_types,
        test_ids: [] as number[],
        exam_ids: [] as number[],
        answers: [
            { answer_text: '', is_correct: false, order: 'A' },
            { answer_text: '', is_correct: false, order: 'B' },
        ] as Array<{ answer_text: string; is_correct: boolean; order: string }>,
    });

    const selectedSubject = subjects.find(
        (subject) => subject.id.toString() === data.subject_id,
    );
    const effectiveExamTypes = selectedSubject?.exam_types ?? data.exam_types;

    const selectedCategories = examCategories.filter((cat) =>
        effectiveExamTypes.includes(cat.slug),
    );
    const hasDepartmentalCategories = examCategories.some(
        (cat) => cat.flow_type === 'departmental',
    );

    const hasStandardFlow = selectedCategories.some(
        (cat) => cat.flow_type === 'standard',
    );

    const examsForSubject = selectedSubject
        ? pastQuestionExams.filter((pastExam) => pastExam.subject === selectedSubject.name)
        : [];

    const hasDepartmentalFlow = selectedCategories.some(
        (cat) => cat.flow_type === 'departmental',
    );

    const filteredSubjects = data.department_id
        ? subjects.filter((subject) => subject.department_id === data.department_id)
        : subjects;

    const testsForSubject = hasDepartmentalFlow && data.subject_id
        ? subjectTests.filter((t) => t.subject_id === parseInt(data.subject_id, 10))
        : [];

    const applySubjectSelection = (subjectId: string) => {
        const subject = subjects.find((item) => item.id.toString() === subjectId);

        setData('subject_id', subjectId);
        setData('test_ids', []);
        setData('exam_ids', []);
        setData('exam_types', subject?.exam_types ?? []);
        if (subject?.department_id) {
            setData('department_id', subject.department_id);
        }
    };

    // Clear answers when question type changes to non-multiple_choice
    useEffect(() => {
        if (data.question_type !== 'multiple_choice') {
            setData('answers', []);
        } else if (data.answers.length === 0) {
            // Re-initialize answers if switching back to multiple_choice
            setData('answers', [
                { answer_text: '', is_correct: false, order: 'A' },
                { answer_text: '', is_correct: false, order: 'B' },
            ]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.question_type]);

    const addAnswer = () => {
        const orders = ['A', 'B', 'C', 'D', 'E'];
        const usedOrders = data.answers.map((a) => a.order);
        const nextOrder = orders.find((o) => !usedOrders.includes(o));
        if (nextOrder && data.answers.length < 5) {
            setData('answers', [
                ...data.answers,
                { answer_text: '', is_correct: false, order: nextOrder },
            ]);
        }
    };

    const removeAnswer = (index: number) => {
        if (data.answers.length > 2) {
            setData(
                'answers',
                data.answers.filter((_, i) => i !== index),
            );
        }
    };

    const updateAnswer = (
        index: number,
        field: string,
        value: string | boolean,
    ) => {
        const updated = [...data.answers];
        updated[index] = { ...updated[index], [field]: value };
        setData('answers', updated);
    };

    const setCorrectAnswer = (index: number) => {
        const updated = data.answers.map((answer, i) => ({
            ...answer,
            is_correct: i === index,
        }));
        setData('answers', updated);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        
        post('/admin/questions', {
            forceFormData: !!data.image,
            transform: (formData) => {
                // Create a new object without answers for non-multiple_choice questions
                // Also remove department_id as it's not stored on questions (only on subjects)
                const { department_id, ...rest } = formData;
                if (rest.question_type !== 'multiple_choice') {
                    const { answers, ...restWithoutAnswers } = rest;
                    return restWithoutAnswers;
                }
                return rest;
            },
            onError: (errors: any) => {
                // Show toast notification for errors
                const errorMessages = Object.values(errors).flat();
                if (errorMessages.length > 0) {
                    const firstError = Array.isArray(errorMessages[0]) 
                        ? errorMessages[0][0] 
                        : errorMessages[0];
                    toast.error('Validation Error', {
                        description: firstError || 'Please check the form for errors.',
                    });
                }
            },
            onSuccess: () => {
                toast.success('Question created successfully!');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Question" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Create Question</h1>
                    <p className="text-muted-foreground">
                        Add a new question to the question bank
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Question Details</CardTitle>
                        <CardDescription>
                            Fill in the question and answer options
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* General error message */}
                        {(errors as any).error && (
                            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {(errors as any).error}
                                </p>
                            </div>
                        )}
                        
                        {/* Show validation errors summary */}
                        {Object.keys(errors).length > 0 && !(errors as any).error && (
                            <div className="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                                    Please fix the following errors:
                                </p>
                                <ul className="list-disc list-inside text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                                    {Object.entries(errors).map(([key, value]) => {
                                        const errorMessage = Array.isArray(value) ? value[0] : value;
                                        return (
                                            <li key={key}>
                                                <span className="font-medium">{key}:</span> {errorMessage}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                        
                        <form onSubmit={submit} className="space-y-6">
                            {hasDepartmentalCategories && (
                                <div className="grid gap-2">
                                    <Label htmlFor="department_id">Department</Label>
                                    <Select
                                        value={data.department_id?.toString() || 'all'}
                                        onValueChange={(value) => {
                                            setData('department_id', value === 'all' ? null : parseInt(value, 10));
                                            setData('subject_id', '');
                                            setData('test_ids', []);
                                            setData('exam_ids', []);
                                            setData('exam_types', []);
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All departments</SelectItem>
                                            {departments.map((department) => (
                                                <SelectItem
                                                    key={department.id}
                                                    value={department.id.toString()}
                                                >
                                                    {department.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Only needed for UNILAG/DLI courses. Leave as &quot;All departments&quot; for JAMB subjects.
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="subject_id">Subject / Course *</Label>
                                <Select
                                    value={data.subject_id}
                                    onValueChange={applySubjectSelection}
                                    required
                                    disabled={filteredSubjects.length === 0}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={
                                            filteredSubjects.length === 0
                                                ? 'No subjects available'
                                                : 'Select a subject or course'
                                        } />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredSubjects.map((subject) => (
                                            <SelectItem
                                                key={subject.id}
                                                value={subject.id.toString()}
                                            >
                                                {subject.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.subject_id} />
                            </div>

                            {hasDepartmentalFlow && data.subject_id && (
                                <div className="grid gap-2">
                                    <Label htmlFor="test_ids">Tests (optional)</Label>
                                    {testsForSubject.length === 0 ? (
                                        <p className="text-sm text-muted-foreground py-2">
                                            No tests yet for this course. Use Manage Tests on the department course page to add tests.
                                        </p>
                                    ) : (
                                        <MultiSelect
                                            id="test_ids"
                                            options={testsForSubject.map((test) => ({
                                                value: String(test.id),
                                                label: test.name,
                                            }))}
                                            value={data.test_ids.map(String)}
                                            onChange={(values) =>
                                                setData(
                                                    'test_ids',
                                                    values.map((value) => parseInt(value, 10)),
                                                )
                                            }
                                            placeholder="Select tests to assign this question to"
                                        />
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Assign this question to one or more tests for departmental practice.
                                    </p>
                                    <InputError message={(errors as any).test_ids} />
                                </div>
                            )}

                            {hasStandardFlow && data.subject_id && (
                                <div className="grid gap-2">
                                    <Label htmlFor="exam_ids">Past Question Years</Label>
                                    {examsForSubject.length === 0 ? (
                                        <p className="text-sm text-muted-foreground py-2">
                                            No past question papers yet for this subject. Create them under Past Questions first.
                                        </p>
                                    ) : (
                                        <MultiSelect
                                            id="exam_ids"
                                            options={examsForSubject.map((pastExam) => ({
                                                value: String(pastExam.id),
                                                label: pastExam.year
                                                    ? String(pastExam.year)
                                                    : pastExam.title,
                                            }))}
                                            value={data.exam_ids.map(String)}
                                            onChange={(values) =>
                                                setData(
                                                    'exam_ids',
                                                    values.map((value) => parseInt(value, 10)),
                                                )
                                            }
                                            placeholder="Select one or more years"
                                        />
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Link this question to past question papers (e.g. 2022, 2023, 2024). Optional for practice-only questions.
                                    </p>
                                    <InputError message={(errors as any).exam_ids} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="question_text">
                                    Question Text *
                                </Label>
                                <Textarea
                                    id="question_text"
                                    value={data.question_text}
                                    onChange={(e) =>
                                        setData('question_text', e.target.value)
                                    }
                                    placeholder="Enter the question..."
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.question_text} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">
                                    Question Image (Optional)
                                </Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0] || null;
                                        setData('image', file);
                                    }}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Upload an image or diagram for this question (e.g., math diagrams, charts)
                                </p>
                                {data.image && (
                                    <div className="mt-2">
                                        <img
                                            src={URL.createObjectURL(data.image)}
                                            alt="Preview"
                                            className="max-w-xs h-auto rounded-lg border border-border"
                                        />
                                    </div>
                                )}
                                <InputError message={errors.image} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="question_type">
                                    Question Type *
                                </Label>
                                <Select
                                    value={data.question_type}
                                    onValueChange={(value) =>
                                        setData('question_type', value as any)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select question type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="multiple_choice">
                                            Multiple Choice
                                        </SelectItem>
                                        <SelectItem value="text_input">
                                            Text Input
                                        </SelectItem>
                                        <SelectItem value="numeric_input">
                                            Numeric Input
                                        </SelectItem>
                                        <SelectItem value="true_false">
                                            True/False
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.question_type} />
                            </div>

                            {/* Conditional fields based on question_type */}
                            {data.question_type === 'multiple_choice' ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Label>Answers *</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={addAnswer}
                                            disabled={data.answers.length >= 5}
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Answer
                                        </Button>
                                    </div>

                                    {data.answers.map((answer, index) => (
                                        <div
                                            key={index}
                                            className="flex items-start gap-3 rounded-lg border p-3"
                                        >
                                            <Checkbox
                                                id={`answer-${index}`}
                                                checked={answer.is_correct}
                                                onCheckedChange={() =>
                                                    setCorrectAnswer(index)
                                                }
                                                className="mt-1"
                                            />
                                            <div className="flex-1 space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Label
                                                        htmlFor={`answer-${index}`}
                                                        className="font-medium"
                                                    >
                                                        {answer.order}.
                                                    </Label>
                                                    <Input
                                                        value={
                                                            answer.answer_text
                                                        }
                                                        onChange={(e) =>
                                                            updateAnswer(
                                                                index,
                                                                'answer_text',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={`Answer option ${answer.order}`}
                                                        required
                                                        className="flex-1"
                                                    />
                                                    {data.answers.length >
                                                        2 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                removeAnswer(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    <InputError message={errors.answers} />
                                </div>
                            ) : data.question_type === 'true_false' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="expected_answer">
                                        Expected Answer *
                                    </Label>
                                    <Select
                                        value={data.expected_answer}
                                        onValueChange={(value) =>
                                            setData('expected_answer', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select true or false" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">
                                                True
                                            </SelectItem>
                                            <SelectItem value="false">
                                                False
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.expected_answer}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Select the correct answer for this
                                        true/false question.
                                    </p>
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="expected_answer">
                                        Expected Answer *
                                    </Label>
                                    <Input
                                        id="expected_answer"
                                        value={data.expected_answer}
                                        onChange={(e) =>
                                            setData(
                                                'expected_answer',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Enter the expected answer (comma-separated for alternatives)"
                                        required
                                    />
                                    <InputError
                                        message={errors.expected_answer}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        For text/numeric input questions. Use
                                        comma to separate multiple acceptable
                                        answers.
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="explanation">Explanation</Label>
                                <Textarea
                                    id="explanation"
                                    value={data.explanation}
                                    onChange={(e) =>
                                        setData('explanation', e.target.value)
                                    }
                                    placeholder="Detailed explanation of the correct answer..."
                                    rows={3}
                                />
                                <InputError message={errors.explanation} />
                            </div>


                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Question'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href={admin.questions.index().url}>
                                        Cancel
                                    </a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
