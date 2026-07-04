import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import { Plus, Trash2 } from 'lucide-react';
import admin from '@/routes/admin';
import { toast } from 'sonner';

interface Answer {
    id?: number;
    answer_text: string;
    is_correct: boolean;
    order: string;
}

interface Question {
    id: number;
    question_text: string;
    image?: string | null;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    explanation: string | null;
    expected_answer: string | null;
    answers: Answer[];
}

interface Exam {
    id: number;
    title: string;
    exam_type: string;
    subject: string | null;
}

interface Subject {
    id: number;
    name: string;
}

interface RelatedExam {
    id: number;
    title: string;
    subject: string | null;
    year: number | null;
}

interface Props {
    exam: Exam;
    question: Question;
    subjects: Subject[];
    relatedExams?: RelatedExam[];
    linkedExamIds?: number[];
}

export default function EditExamQuestion({ exam, question, subjects, relatedExams = [], linkedExamIds = [] }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Exams', href: admin.exams.index().url },
        { title: exam.title, href: admin.exams.show(exam.id).url },
        { title: 'Edit Question', href: '#' },
    ];

    const { data, setData, patch, processing, errors } = useForm({
        question_text: question.question_text,
        image: null as File | null,
        question_type: question.question_type,
        explanation: question.explanation || '',
        expected_answer: question.expected_answer || '',
        answers: question.answers.map(a => ({
            id: a.id,
            answer_text: a.answer_text,
            is_correct: a.is_correct,
            order: a.order,
        })),
        exam_ids: linkedExamIds.filter((id) => id !== exam.id),
    });

    const addAnswer = () => {
        const orders = ['A', 'B', 'C', 'D', 'E'];
        const usedOrders = data.answers.map(a => a.order);
        const nextOrder = orders.find(o => !usedOrders.includes(o));
        if (nextOrder && data.answers.length < 5) {
            setData('answers', [...data.answers, { answer_text: '', is_correct: false, order: nextOrder }]);
        }
    };

    const removeAnswer = (index: number) => {
        if (data.answers.length > 2) {
            setData('answers', data.answers.filter((_, i) => i !== index));
        }
    };

    const updateAnswer = (index: number, field: string, value: string | boolean) => {
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
        const updateUrl = `/admin/exams/${exam.id}/questions/${question.id}`;
        patch(updateUrl, {
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
                toast.success('Question updated successfully!');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Question - ${exam.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 overflow-x-hidden">
                <div className="min-w-0">
                    <h1 className="text-xl sm:text-2xl font-bold break-words">Edit Question</h1>
                    <p className="text-sm sm:text-base text-muted-foreground">
                        Update question in <strong>{exam.title}</strong>
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
                        <CardDescription>Update the question and answer options</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {relatedExams.length > 0 && (
                                <div className="grid gap-2">
                                    <Label htmlFor="exam_ids">Also appear in these years</Label>
                                    <MultiSelect
                                        id="exam_ids"
                                        options={relatedExams
                                            .filter((relatedExam) => relatedExam.id !== exam.id)
                                            .map((relatedExam) => ({
                                                value: String(relatedExam.id),
                                                label: `${relatedExam.title}${relatedExam.year ? ` (${relatedExam.year})` : ''}`,
                                            }))}
                                        value={data.exam_ids.map(String)}
                                        onChange={(values) =>
                                            setData(
                                                'exam_ids',
                                                values.map((value) => parseInt(value, 10)),
                                            )
                                        }
                                        placeholder="Select additional past question years"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        This paper ({exam.year ?? exam.title}) is always included.
                                    </p>
                                    <InputError message={(errors as any).exam_ids} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="question_text">Question Text *</Label>
                                <Textarea
                                    id="question_text"
                                    value={data.question_text}
                                    onChange={(e) => setData('question_text', e.target.value)}
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
                                {data.image ? (
                                    <div className="mt-2 max-w-full overflow-hidden">
                                        <img
                                            src={URL.createObjectURL(data.image)}
                                            alt="Preview"
                                            className="max-w-full max-h-48 sm:max-h-64 object-contain rounded-lg border border-border"
                                        />
                                    </div>
                                ) : question.image ? (
                                    <div className="mt-2 max-w-full overflow-hidden">
                                        <p className="text-sm text-muted-foreground mb-2">Current image:</p>
                                        <img
                                            src={question.image.startsWith('http') ? question.image : `/storage/${question.image}`}
                                            alt="Current question image"
                                            className="max-w-full max-h-48 sm:max-h-64 object-contain h-auto rounded-lg border border-border"
                                            onError={(e) => {
                                                (e.target as HTMLImageElement).style.display = 'none';
                                            }}
                                        />
                                    </div>
                                ) : null}
                                <InputError message={errors.image} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="question_type">Question Type *</Label>
                                <Select
                                    value={data.question_type}
                                    onValueChange={(value) => setData('question_type', value as any)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select question type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="multiple_choice">Multiple Choice</SelectItem>
                                        <SelectItem value="text_input">Text Input</SelectItem>
                                        <SelectItem value="numeric_input">Numeric Input</SelectItem>
                                        <SelectItem value="true_false">True/False</SelectItem>
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
                                        <div key={index} className="flex items-start gap-2 sm:gap-3 p-3 border rounded-lg min-w-0">
                                            <Checkbox
                                                id={`answer-${index}`}
                                                checked={answer.is_correct}
                                                onCheckedChange={() => setCorrectAnswer(index)}
                                                className="mt-1 shrink-0"
                                            />
                                            <div className="flex-1 min-w-0 space-y-2">
                                                <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                                                    <Label htmlFor={`answer-${index}`} className="font-medium shrink-0">
                                                        {answer.order}.
                                                    </Label>
                                                    <div className="flex gap-2 flex-1 min-w-0">
                                                        <Input
                                                            value={answer.answer_text}
                                                            onChange={(e) => updateAnswer(index, 'answer_text', e.target.value)}
                                                            placeholder={`Answer option ${answer.order}`}
                                                            required
                                                            className="flex-1 min-w-0"
                                                        />
                                                        {data.answers.length > 2 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => removeAnswer(index)}
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
                                    <Label htmlFor="expected_answer">Expected Answer *</Label>
                                    <Select
                                        value={data.expected_answer}
                                        onValueChange={(value) => setData('expected_answer', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select true or false" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">True</SelectItem>
                                            <SelectItem value="false">False</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.expected_answer} />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="expected_answer">Expected Answer *</Label>
                                    <Input
                                        id="expected_answer"
                                        value={data.expected_answer}
                                        onChange={(e) => setData('expected_answer', e.target.value)}
                                        placeholder="Enter the expected answer (comma-separated for alternatives)"
                                        required
                                    />
                                    <InputError message={errors.expected_answer} />
                                    <p className="text-sm text-muted-foreground">
                                        Use comma to separate multiple acceptable answers.
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="explanation">Explanation</Label>
                                <Textarea
                                    id="explanation"
                                    value={data.explanation}
                                    onChange={(e) => setData('explanation', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.explanation} />
                            </div>

                            <div className="flex flex-col-reverse sm:flex-row flex-wrap gap-2">
                                <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                    {processing ? 'Updating...' : 'Update Question'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                    className="w-full sm:w-auto"
                                >
                                    <a href={admin.exams.show(exam.id).url}>Cancel</a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
