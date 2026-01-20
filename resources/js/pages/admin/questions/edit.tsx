import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import { Plus, Trash2 } from 'lucide-react';
import admin from '@/routes/admin';

interface Answer {
    id?: number;
    answer_text: string;
    is_correct: boolean;
    order: string;
}

interface Question {
    id: number;
    question_text: string;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    explanation: string | null;
    expected_answer: string | null;
    exam_types: string[] | null;
    subject_id: number | null;
    answers: Answer[];
    subject: {
        id: number;
        name: string;
    } | null;
}

interface Subject {
    id: number;
    name: string;
}

interface Props {
    question: Question;
    subjects: Subject[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Questions', href: admin.questions.index().url },
    { title: 'Edit Question', href: '#' },
];

export default function EditQuestion({ question, subjects }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        subject_id: question.subject_id?.toString() || '',
        question_text: question.question_text,
        question_type: question.question_type,
        explanation: question.explanation || '',
        expected_answer: question.expected_answer || '',
        exam_types: (question.exam_types && Array.isArray(question.exam_types)) 
            ? question.exam_types 
            : (question.exam_types ? [question.exam_types] : []),
        answers: question.answers.map(a => ({
            id: a.id,
            answer_text: a.answer_text,
            is_correct: a.is_correct,
            order: a.order,
        })),
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
        put(admin.questions.update({ question: question.id }).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Question" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Edit Question</h1>
                    <p className="text-muted-foreground">Update question in the question bank</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Question Details</CardTitle>
                        <CardDescription>Update the question and answer options</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="subject_id">Subject *</Label>
                                <Select
                                    value={data.subject_id}
                                    onValueChange={(value) => setData('subject_id', value)}
                                    required
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a subject" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {subjects.map((subject) => (
                                            <SelectItem key={subject.id} value={subject.id.toString()}>
                                                {subject.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.subject_id} />
                            </div>

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
                                        <div key={index} className="flex items-start gap-3 p-3 border rounded-lg">
                                            <Checkbox
                                                id={`answer-${index}`}
                                                checked={answer.is_correct}
                                                onCheckedChange={() => setCorrectAnswer(index)}
                                                className="mt-1"
                                            />
                                            <div className="flex-1 space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Label htmlFor={`answer-${index}`} className="font-medium">
                                                        {answer.order}.
                                                    </Label>
                                                    <Input
                                                        value={answer.answer_text}
                                                        onChange={(e) => updateAnswer(index, 'answer_text', e.target.value)}
                                                        placeholder={`Answer option ${answer.order}`}
                                                        required
                                                        className="flex-1"
                                                    />
                                                    {data.answers.length > 2 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => removeAnswer(index)}
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
                                    <p className="text-sm text-muted-foreground">
                                        Select the correct answer for this true/false question.
                                    </p>
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
                                        For text/numeric input questions. Use comma to separate multiple acceptable answers.
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

                            <div className="space-y-2">
                                <Label>Available for Exam Types *</Label>
                                <div className="flex gap-4">
                                    {['JAMB', 'DLI', 'UNILAG', 'GENERAL'].map((type) => (
                                        <div key={type} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`exam_type_${type}`}
                                                checked={data.exam_types.includes(type)}
                                                onCheckedChange={(checked) => {
                                                    setData('exam_types', checked
                                                        ? [...data.exam_types, type]
                                                        : data.exam_types.filter((t) => t !== type)
                                                    );
                                                }}
                                            />
                                            <Label htmlFor={`exam_type_${type}`} className="font-normal cursor-pointer">
                                                {type}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Select which exam types this question should be available for. You can select multiple.
                                </p>
                                <InputError message={errors.exam_types} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Updating...' : 'Update Question'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                >
                                    <a href={admin.questions.index().url}>Cancel</a>
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
