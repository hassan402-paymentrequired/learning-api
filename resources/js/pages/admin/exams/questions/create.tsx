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
}

interface Exam {
    id: number;
    title: string;
    exam_type: string;
    subject: string | null;
}

interface Props {
    exam: Exam;
    subjects: Subject[];
}

export default function CreateExamQuestion({ exam, subjects }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Exams', href: admin.exams.index().url },
        { title: exam.title, href: admin.exams.show(exam.id).url },
        { title: 'Create Question', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        question_text: '',
        image: null as File | null,
        question_type: 'multiple_choice' as
            | 'multiple_choice'
            | 'text_input'
            | 'numeric_input'
            | 'true_false',
        explanation: '',
        expected_answer: '',
        answers: [
            { answer_text: '', is_correct: false, order: 'A' },
            { answer_text: '', is_correct: false, order: 'B' },
        ] as Array<{ answer_text: string; is_correct: boolean; order: string }>,
    });

    // Clear answers when question type changes to non-multiple_choice
    useEffect(() => {
        if (data.question_type !== 'multiple_choice') {
            setData('answers', []);
        } else if (data.answers.length === 0) {
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
        
        post(admin.exams.questions.store(exam.id).url, {
            forceFormData: !!data.image,
            transform: (formData) => {
                if (formData.question_type !== 'multiple_choice') {
                    const { answers, ...rest } = formData;
                    return rest;
                }
                return formData;
            },
            onError: (errors: any) => {
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
            <Head title={`Create Question - ${exam.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 overflow-x-hidden">
                <div className="min-w-0">
                    <h1 className="text-xl sm:text-2xl font-bold break-words">Create Question</h1>
                    <p className="text-sm sm:text-base text-muted-foreground">
                        Add a new question to <strong>{exam.title}</strong>
                    </p>
                    <p className="text-xs sm:text-sm text-muted-foreground mt-1">
                        Exam Type: <strong>{exam.exam_type}</strong>
                        {exam.subject && (
                            <> • Subject: <strong>{exam.subject}</strong></>
                        )}
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
                        {(errors as any).error && (
                            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {(errors as any).error}
                                </p>
                            </div>
                        )}
                        
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
                                    Upload an image or diagram for this question
                                </p>
                                {data.image && (
                                    <div className="mt-2 max-w-full overflow-hidden">
                                        <img
                                            src={URL.createObjectURL(data.image)}
                                            alt="Preview"
                                            className="max-w-full max-h-48 sm:max-h-64 object-contain rounded-lg border border-border"
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

                            {data.question_type === 'multiple_choice' ? (
                                <div className="space-y-4">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <Label>Answers *</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={addAnswer}
                                            disabled={data.answers.length >= 5}
                                            className="w-full sm:w-auto"
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Answer
                                        </Button>
                                    </div>

                                    {data.answers.map((answer, index) => (
                                        <div
                                            key={index}
                                            className="flex items-start gap-2 sm:gap-3 rounded-lg border p-3 min-w-0"
                                        >
                                            <Checkbox
                                                id={`answer-${index}`}
                                                checked={answer.is_correct}
                                                onCheckedChange={() =>
                                                    setCorrectAnswer(index)
                                                }
                                                className="mt-1 shrink-0"
                                            />
                                            <div className="flex-1 min-w-0 space-y-2">
                                                <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                                                    <Label
                                                        htmlFor={`answer-${index}`}
                                                        className="font-medium shrink-0"
                                                    >
                                                        {answer.order}.
                                                    </Label>
                                                    <div className="flex gap-2 flex-1 min-w-0">
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
                                                            className="flex-1 min-w-0"
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
                                                                className="shrink-0"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
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
                                        Use comma to separate multiple acceptable
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

                            <div className="flex flex-col-reverse sm:flex-row flex-wrap gap-2">
                                <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Question'}
                                </Button>
                                <Button type="button" variant="outline" asChild className="w-full sm:w-auto">
                                    <a href={admin.exams.show(exam.id).url}>
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
