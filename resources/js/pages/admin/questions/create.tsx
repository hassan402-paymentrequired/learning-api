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

interface Props {
    subjects: Subject[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Questions', href: admin.questions.index().url },
    { title: 'Create Question', href: '#' },
];

export default function CreateQuestion({ subjects }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        subject_id: '',
        question_text: '',
        image: null as File | null,
        question_type: 'multiple_choice' as
            | 'multiple_choice'
            | 'text_input'
            | 'numeric_input'
            | 'true_false',
        explanation: '',
        expected_answer: '',
        exam_types: [] as string[],
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
                if (formData.question_type !== 'multiple_choice') {
                    const { answers, ...rest } = formData;
                    return rest;
                }
                return formData;
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
                            <div className="grid gap-2">
                                <Label htmlFor="subject_id">Subject *</Label>
                                <Select
                                    value={data.subject_id}
                                    onValueChange={(value) =>
                                        setData('subject_id', value)
                                    }
                                    required
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a subject" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {subjects.map((subject) => (
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

                            <div className="space-y-2">
                                <Label>Available for Exam Types *</Label>
                                <div className="flex gap-4">
                                    {['JAMB', 'DLI', 'UNILAG', 'GENERAL'].map(
                                        (type) => (
                                            <div
                                                key={type}
                                                className="flex items-center space-x-2"
                                            >
                                                <Checkbox
                                                    id={`exam_type_${type}`}
                                                    checked={data.exam_types.includes(
                                                        type,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        setData(
                                                            'exam_types',
                                                            checked
                                                                ? [
                                                                      ...data.exam_types,
                                                                      type,
                                                                  ]
                                                                : data.exam_types.filter(
                                                                      (t) =>
                                                                          t !==
                                                                          type,
                                                                  ),
                                                        );
                                                    }}
                                                />
                                                <Label
                                                    htmlFor={`exam_type_${type}`}
                                                    className="cursor-pointer font-normal"
                                                >
                                                    {type}
                                                </Label>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Select which exam types this question should
                                    be available for. You can select multiple.
                                </p>
                                <InputError message={errors.exam_types} />
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
