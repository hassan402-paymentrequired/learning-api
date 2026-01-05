import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import { Plus, Trash2 } from 'lucide-react';

interface Exam {
    id: number;
    title: string;
}

interface Props {
    exam: Exam;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exams', href: route('admin.exams.index').url },
    { title: 'Create Question', href: '#' },
];

export default function CreateQuestion({ exam }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        question_text: '',
        question_type: 'multiple_choice' as const,
        explanation: '',
        points: 1,
        order: 1,
        answers: [
            { answer_text: '', is_correct: false, order: 'A' },
            { answer_text: '', is_correct: false, order: 'B' },
        ] as Array<{ answer_text: string; is_correct: boolean; order: string }>,
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
        post(route('admin.exams.questions.store', exam.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Question" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Create Question</h1>
                    <p className="text-muted-foreground">Add a new question to {exam.title}</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Question Details</CardTitle>
                        <CardDescription>Fill in the question and answer options</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="question_text">Question Text *</Label>
                                <Textarea
                                    id="question_text"
                                    value={data.question_text}
                                    onChange={(e) => setData('question_text', e.target.value)}
                                    placeholder="Enter the question..."
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.question_text} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="points">Points *</Label>
                                    <Input
                                        id="points"
                                        type="number"
                                        min="1"
                                        value={data.points}
                                        onChange={(e) => setData('points', parseInt(e.target.value) || 1)}
                                        required
                                    />
                                    <InputError message={errors.points} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="order">Question Order *</Label>
                                    <Input
                                        id="order"
                                        type="number"
                                        min="1"
                                        value={data.order}
                                        onChange={(e) => setData('order', parseInt(e.target.value) || 1)}
                                        required
                                    />
                                    <InputError message={errors.order} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="explanation">Explanation</Label>
                                <Textarea
                                    id="explanation"
                                    value={data.explanation}
                                    onChange={(e) => setData('explanation', e.target.value)}
                                    placeholder="Detailed explanation of the correct answer..."
                                    rows={3}
                                />
                                <InputError message={errors.explanation} />
                            </div>

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

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Question'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                >
                                    <a href={route('admin.exams.show', exam.id)}>Cancel</a>
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
