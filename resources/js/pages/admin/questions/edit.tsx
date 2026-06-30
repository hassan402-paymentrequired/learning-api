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
    image?: string | null;
    question_type: 'multiple_choice' | 'text_input' | 'numeric_input' | 'true_false';
    explanation: string | null;
    expected_answer: string | null;
    exam_types: string[] | null;
    subject_id: number | null;
    answers: Answer[];
    subject: {
        id: number;
        name: string;
        department_id?: number | null;
    } | null;
    subject_tests?: { id: number }[];
}

interface Subject {
    id: number;
    name: string;
    department_id: number | null;
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

interface Props {
    question: Question;
    subjects: Subject[];
    departments: Department[];
    subjectTests: SubjectTest[];
    examCategories: ExamCategory[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Questions', href: admin.questions.index().url },
    { title: 'Edit Question', href: '#' },
];

export default function EditQuestion({ question, subjects, departments, subjectTests, examCategories }: Props) {
    const initialDepartmentId =
        question.subject?.department_id ??
        subjects.find((s) => s.id === question.subject_id)?.department_id ??
        null;
    
    const { data, setData, patch, processing, errors } = useForm({
        department_id: initialDepartmentId,
        subject_id: question.subject_id?.toString() || '',
        question_text: question.question_text,
        image: null as File | null,
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
        test_ids: ((question as any).subject_tests ?? (question as any).subjectTests ?? []).map((t: { id: number }) => t.id),
    });

    const selectedCategories = examCategories.filter(cat => data.exam_types.includes(cat.slug));
    const hasDepartmentalFlow = selectedCategories.some(cat => cat.flow_type === 'departmental');

    const filteredSubjects = hasDepartmentalFlow && data.department_id
        ? subjects.filter(subject => subject.department_id === data.department_id)
        : subjects;

    const testsForSubject = hasDepartmentalFlow && data.subject_id
        ? subjectTests.filter(t => t.subject_id === parseInt(data.subject_id, 10))
        : [];

    const handleExamTypesChange = (newExamTypes: string[]) => {
        setData('exam_types', newExamTypes);

        const stillHasDepartmental = examCategories
            .filter(cat => newExamTypes.includes(cat.slug))
            .some(cat => cat.flow_type === 'departmental');

        if (!stillHasDepartmental) {
            setData('department_id', null);
            setData('subject_id', '');
            setData('test_ids', []);
        }
    };

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
        patch(admin.questions.update({ question: question.id }).url, {
            forceFormData: !!data.image,
            transform: (formData) => {
                // Remove department_id as it's not stored on questions (only on subjects)
                const { department_id, ...rest } = formData;
                return rest;
            },
        });
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
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="exam_types">Available for Exam Types *</Label>
                                <MultiSelect
                                    id="exam_types"
                                    options={examCategories.map((category) => ({
                                        value: category.slug,
                                        label: category.name,
                                    }))}
                                    value={data.exam_types}
                                    onChange={handleExamTypesChange}
                                    placeholder="Select exam types"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Choose one or more exam categories this question belongs to.
                                </p>
                                <InputError message={errors.exam_types} />
                            </div>

                            {/* Department Selection for Departmental Flows (DLI/UNILAG/etc) */}
                            {hasDepartmentalFlow && (
                                <div className="grid gap-2">
                                    <Label htmlFor="department_id">Department *</Label>
                                    <Select
                                        value={data.department_id?.toString() || ''}
                                        onValueChange={(value) => {
                                            setData('department_id', value ? parseInt(value) : null);
                                            setData('subject_id', '');
                                            setData('test_ids', []);
                                        }}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a department" />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                        Department is required for departmental exam types.
                                    </p>
                                    <InputError message={(errors as any).department_id} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="subject_id">Subject *</Label>
                                <Select
                                    value={data.subject_id}
                                    onValueChange={(value) => {
                                        setData('subject_id', value);
                                        setData('test_ids', []);
                                    }}
                                    required
                                    disabled={filteredSubjects.length === 0}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={
                                            hasDepartmentalFlow && !data.department_id
                                                ? "Select a department first"
                                                : filteredSubjects.length === 0
                                                ? "No subjects available"
                                                : "Select a subject"
                                        } />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredSubjects.map((subject) => (
                                            <SelectItem key={subject.id} value={subject.id.toString()}>
                                                {subject.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {hasDepartmentalFlow && !data.department_id && (
                                    <p className="text-xs text-muted-foreground">
                                        Please select a department first to see available subjects.
                                    </p>
                                )}
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
                                    Upload an image or diagram for this question (e.g., math diagrams, charts)
                                </p>
                                {data.image ? (
                                    <div className="mt-2">
                                        <img
                                            src={URL.createObjectURL(data.image)}
                                            alt="Preview"
                                            className="max-w-xs h-auto rounded-lg border border-border"
                                        />
                                    </div>
                                ) : question.image ? (
                                    <div className="mt-2">
                                        <p className="text-sm text-muted-foreground mb-2">Current image:</p>
                                        <img
                                            src={question.image.startsWith('http') ? question.image : `/storage/${question.image}`}
                                            alt="Current question image"
                                            className="max-w-xs h-auto rounded-lg border border-border"
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
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
