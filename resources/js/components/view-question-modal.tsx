import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Edit, Trash2, Power, PowerOff, X } from 'lucide-react';
import { Link } from '@inertiajs/react';
import admin from '@/routes/admin';

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
    answers?: Answer[];
}

interface Props {
    question: Question | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onToggleActive?: (questionId: number) => void;
    onDelete?: (questionId: number) => void;
}

export default function ViewQuestionModal({ question, open, onOpenChange, onToggleActive, onDelete }: Props) {
    if (!question && !open) return null;
    
    // Show loading state if modal is open but question is not loaded yet
    if (open && !question) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent 
                    className="overflow-y-auto p-6"
                    style={{ width: '80vw', height: '80vh', maxWidth: '80vw', maxHeight: '80vh' }}
                >
                    <div className="flex items-center justify-center h-full">
                        <div className="text-center">
                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-solid border-primary border-r-transparent mx-auto mb-4" />
                            <p className="text-muted-foreground">Loading question details...</p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        );
    }
    
    if (!question) return null;

    const getQuestionTypeBadge = (type: string) => {
        const colors = {
            'multiple_choice': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'text_input': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'numeric_input': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            'true_false': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };
        return colors[type as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const imageUrl = question.image 
        ? (question.image.startsWith('http') ? question.image : `/storage/${question.image}`)
        : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent 
                className="overflow-y-auto p-6"
                style={{ width: '80vw', height: '80vh', maxWidth: '80vw', maxHeight: '80vh' }}
            >
                <DialogHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <DialogTitle className="text-2xl">Question Details</DialogTitle>
                            <DialogDescription className="mt-2">
                                View complete information about this question
                            </DialogDescription>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onOpenChange(false)}
                            className="h-8 w-8"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </DialogHeader>

                <div className="space-y-6 mt-4">
                    {/* Question Type and Status */}
                    <div className="flex items-center gap-3">
                        <Badge className={getQuestionTypeBadge(question.question_type)}>
                            {question.question_type.replace('_', ' ')}
                        </Badge>
                        <Badge variant={question.is_active ? 'default' : 'secondary'}>
                            {question.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        {question.exam_types && question.exam_types.length > 0 && (
                            <Badge variant="outline">
                                {question.exam_types.join(', ')}
                            </Badge>
                        )}
                    </div>

                    {/* Question Text */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Question</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-base leading-relaxed whitespace-pre-wrap">
                                {question.question_text}
                            </p>
                            
                            {/* Question Image */}
                            {imageUrl && (
                                <div className="mt-4">
                                    <img
                                        src={imageUrl}
                                        alt="Question diagram"
                                        className="max-w-full h-auto rounded-lg border border-border"
                                        onError={(e) => {
                                            (e.target as HTMLImageElement).style.display = 'none';
                                        }}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Answers Section - Only for Multiple Choice */}
                    {question.question_type === 'multiple_choice' && question.answers && question.answers.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Answer Options</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {question.answers.map((answer) => (
                                        <div
                                            key={answer.id}
                                            className={`flex items-start gap-3 p-3 rounded-lg border ${
                                                answer.is_correct
                                                    ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800'
                                                    : 'bg-muted/50 border-border'
                                            }`}
                                        >
                                            <div className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center font-semibold text-sm ${
                                                answer.is_correct
                                                    ? 'bg-green-500 text-white'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}>
                                                {answer.order}
                                            </div>
                                            <div className="flex-1">
                                                <p className="text-sm">{answer.answer_text}</p>
                                            </div>
                                            {answer.is_correct && (
                                                <Badge variant="default" className="bg-green-500">
                                                    Correct
                                                </Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Expected Answer - For non-multiple choice questions */}
                    {question.question_type !== 'multiple_choice' && question.expected_answer && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Expected Answer</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-base font-medium">{question.expected_answer}</p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Explanation */}
                    {question.explanation && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Explanation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                    {question.explanation}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Subject and Exam Info */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {question.subject && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium">Subject</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">{question.subject.name}</p>
                                </CardContent>
                            </Card>
                        )}
                        {question.exam && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium">Exam</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">{question.exam.title}</p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {question.exam.exam_type}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-wrap gap-2 pt-4 border-t">
                        <Button asChild variant="outline" className="flex-1 sm:flex-none">
                            <Link href={admin.questions.edit({ question: question.id }).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Question
                            </Link>
                        </Button>
                        {onToggleActive && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    onToggleActive(question.id);
                                }}
                                className="flex-1 sm:flex-none"
                            >
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
                            </Button>
                        )}
                        {onDelete && (
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
                                        onDelete(question.id);
                                    }
                                }}
                                className="flex-1 sm:flex-none"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
