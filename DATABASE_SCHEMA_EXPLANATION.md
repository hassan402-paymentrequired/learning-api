# Database Schema Explanation

## Overview

This document explains what each database table does and what it stores.

---

## Core Tables

### 1. **users**

**Purpose**: Stores user accounts (students and admins)

**What it stores**:

- `id` - Unique user ID
- `name` - User's full name
- `email` - Email address (unique, used for login)
- `password` - Hashed password
- `email_verified_at` - When email was verified
- `remember_token` - For "remember me" functionality
- `created_at`, `updated_at` - Timestamps

**What you need**: This is the foundation - every user must have an account to use the app.

---

### 2. **exams**

**Purpose**: Stores exam/practice sets (e.g., "JAMB Mathematics 2023", "DLI Practice Set 1")

**What it stores**:

- `id` - Unique exam ID
- `title` - Exam name (e.g., "JAMB Mathematics Practice")
- `description` - Optional description
- `type` - Either `'practice'` or `'past_question'`
- `exam_type` - `'JAMB'`, `'DLI'`, `'UNILAG'`, or `'GENERAL'`
- `subject` - Subject name (e.g., "Mathematics", "English")
- `duration` - Time limit in minutes
- `total_questions` - Number of questions in this exam
- `year` - Year (for past questions, e.g., 2023)
- `is_active` - Whether exam is available to students
- `created_at`, `updated_at` - Timestamps

**What you need**: Create exams before adding questions. Each exam belongs to one subject.

**Example**:

- Title: "JAMB Mathematics Practice"
- Type: practice
- Exam Type: JAMB
- Subject: Mathematics
- Duration: 60 minutes

---

### 3. **questions**

**Purpose**: Stores individual questions for exams

**What it stores**:

- `id` - Unique question ID
- `exam_id` - Which exam this question belongs to (foreign key)
- `question_text` - The actual question (e.g., "What is 2 + 2?")
- `question_type` - `'multiple_choice'` or `'text_input'` (for future use)
- `explanation` - Why the answer is correct (shown after exam)
- `expected_answer` - For text input questions (comma-separated acceptable answers)
- `points` - How many points this question is worth (usually 1)
- `order` - Question number (1, 2, 3, etc.)
- `created_at`, `updated_at` - Timestamps

**What you need**: Questions must belong to an exam. Each question can have:

- Multiple choice: Multiple answer options (stored in `answers` table)
- Text input: Expected answer(s) stored in `expected_answer` field

**Example**:

- Question Text: "What is the capital of Nigeria?"
- Question Type: multiple_choice
- Points: 1
- Order: 1

---

### 4. **answers**

**Purpose**: Stores answer options for multiple choice questions

**What it stores**:

- `id` - Unique answer ID
- `question_id` - Which question this answer belongs to (foreign key)
- `answer_text` - The answer option text (e.g., "Lagos", "Abuja", "Kano")
- `is_correct` - `true` if this is the correct answer, `false` otherwise
- `order` - Letter order: `'A'`, `'B'`, `'C'`, `'D'`, or `'E'`
- `created_at`, `updated_at` - Timestamps

**What you need**: Only needed for multiple choice questions. Each question should have:

- At least 2 answers (usually 4: A, B, C, D)
- Exactly ONE answer with `is_correct = true`

**Example**:

- Question: "What is 2 + 2?"
- Answer A: "3" (is_correct: false)
- Answer B: "4" (is_correct: true) ← Correct answer
- Answer C: "5" (is_correct: false)
- Answer D: "6" (is_correct: false)

---

### 5. **exam_attempts**

**Purpose**: Tracks when a user starts/completes an exam

**What it stores**:

- `id` - Unique attempt ID
- `user_id` - Which user took this exam (foreign key)
- `exam_id` - Which exam was attempted (foreign key)
- `subjects` - JSON array of subjects for multi-subject exams (e.g., `[{"subject": "Math", "question_count": 50}]`)
- `started_at` - When user started the exam
- `completed_at` - When user finished (null if not completed)
- `time_spent` - Total time in seconds
- `duration_minutes` - Total duration selected by user (for multi-subject)
- `score` - Final score (for JAMB: out of 400, others: number of correct answers)
- `total_questions` - How many questions were in the exam
- `correct_answers` - How many questions user got right
- `status` - `'in_progress'`, `'completed'`, or `'abandoned'`
- `created_at`, `updated_at` - Timestamps

**What you need**: Created automatically when user starts an exam. Tracks:

- Progress (in_progress vs completed)
- Performance (score, correct_answers)
- Time tracking

**Example**:

- User: John Doe
- Exam: JAMB Mathematics Practice
- Started: 2026-01-07 10:00:00
- Completed: 2026-01-07 10:45:00
- Score: 75 (out of 100 questions = 75%)
- Status: completed

---

### 6. **user_answers**

**Purpose**: Stores each individual answer a user selected during an exam

**What it stores**:

- `id` - Unique answer record ID
- `exam_attempt_id` - Which exam attempt this answer belongs to (foreign key)
- `question_id` - Which question was answered (foreign key)
- `answer_id` - Which answer option was selected (foreign key, null for text input)
- `is_correct` - Whether the user got it right
- `time_spent` - How long user spent on this question (in seconds)
- `created_at`, `updated_at` - Timestamps

**What you need**: Created automatically as user answers questions. Used for:

- Showing corrections after exam
- Calculating score
- Analytics (which questions are hardest)

**Example**:

- Attempt: Exam attempt #123
- Question: "What is 2 + 2?"
- Answer Selected: Answer B ("4")
- Is Correct: true
- Time Spent: 15 seconds

---

## Feature Tables

### 7. **subjects**

**Purpose**: Manages subjects that can be used in exams (admin-managed)

**What it stores**:

- `id` - Unique subject ID
- `name` - Subject name (e.g., "Mathematics", "English")
- `slug` - URL-friendly version (e.g., "mathematics")
- `description` - Optional description
- `is_active` - Whether subject is available
- `order` - Display order (for sorting)
- `created_at`, `updated_at` - Timestamps

**What you need**: Admin creates subjects, then uses them when creating exams. Helps organize exams by subject.

**Example**:

- Name: Mathematics
- Slug: mathematics
- Is Active: true
- Order: 1

---

### 8. **user_streaks**

**Purpose**: Tracks daily practice streaks to encourage consistent usage

**What it stores**:

- `id` - Unique streak record ID
- `user_id` - Which user (foreign key)
- `date` - The date of the streak (one record per day)
- `created_at`, `updated_at` - Timestamps

**What you need**: Created automatically when user completes an exam. Used to:

- Show "5 day streak" on home screen
- Encourage daily practice
- Track user engagement

**Example**:

- User: John Doe
- Date: 2026-01-07
- (Recorded when user completes an exam on this date)

---

### 9. **announcements**

**Purpose**: Stores announcements/banners to show on home screen

**What it stores**:

- `id` - Unique announcement ID
- `title` - Announcement title
- `message` - Announcement content
- `type` - `'info'`, `'warning'`, `'success'`, or `'error'` (for styling)
- `is_active` - Whether to show this announcement
- `priority` - Higher numbers show first
- `link` - Optional URL to link to
- `link_text` - Text for the link button
- `start_date` - When to start showing (optional)
- `end_date` - When to stop showing (optional)
- `created_at`, `updated_at` - Timestamps

**What you need**: Admin creates announcements to notify users about:

- New features
- Maintenance
- Special offers
- Important updates

**Example**:

- Title: "New JAMB Questions Added!"
- Message: "We've added 500 new JAMB practice questions"
- Type: success
- Is Active: true

---

## System Tables (Laravel Default)

### 10. **password_reset_tokens**

**Purpose**: Stores password reset tokens (Laravel default)

**What you need**: Nothing - handled automatically by Laravel.

---

### 11. **sessions**

**Purpose**: Stores user sessions (Laravel default)

**What you need**: Nothing - handled automatically by Laravel.

---

### 12. **cache** & **jobs**

**Purpose**: Laravel system tables for caching and background jobs

**What you need**: Nothing - handled automatically by Laravel.

---

## Relationships Summary

```
users
  └── exam_attempts (one user can have many attempts)
        └── user_answers (one attempt has many answers)
              └── questions (each answer is for a question)
                    └── answers (each question has multiple answer options)
                    └── exams (each question belongs to one exam)

users
  └── user_streaks (one user can have many streak records)

exams
  └── questions (one exam has many questions)
  └── exam_attempts (one exam can be attempted many times)
```

---

## What You Need to Do

### For Basic Setup:

1. ✅ **Users** - Already handled (registration/login)
2. ✅ **Subjects** - Admin creates via admin panel
3. ✅ **Exams** - Admin creates via admin panel
4. ✅ **Questions** - Admin creates via admin panel (or bulk upload)
5. ✅ **Answers** - Created automatically when creating questions

### For User Activity:

- **exam_attempts** - Created automatically when user starts exam
- **user_answers** - Created automatically as user answers questions
- **user_streaks** - Created automatically when user completes exam

### For Features:

- **announcements** - Admin creates via admin panel (optional)

---

## Common Workflows

### Creating an Exam:

1. Admin creates/selects a **Subject**
2. Admin creates an **Exam** (links to subject)
3. Admin adds **Questions** to the exam
4. For each question, admin adds **Answers** (if multiple choice)

### User Taking Exam:

1. User starts exam → **exam_attempt** created (status: in_progress)
2. User answers question → **user_answer** created
3. User submits exam → **exam_attempt** updated (status: completed, score calculated)
4. If completed today → **user_streak** created/updated

---

## Important Notes

1. **Cascade Deletes**:
    - Deleting an exam deletes all its questions
    - Deleting a question deletes all its answers
    - Deleting a user deletes all their attempts and streaks

2. **Multi-Subject Exams**:
    - `exam_attempts.subjects` stores JSON: `[{"subject": "Math", "question_count": 50}]`
    - Allows users to practice multiple subjects in one exam session

3. **Question Types**:
    - **Multiple Choice**: Uses `answers` table
    - **Text Input**: Uses `expected_answer` field (comma-separated acceptable answers)

4. **Scoring**:
    - JAMB exams: Score calculated out of 400
    - Other exams: Score = number of correct answers
    - Percentage = (correct_answers / total_questions) \* 100







so this now open a new features. you know currently when user select either jamb or DLI they get to choose either past questions or practice. so now whenever user click on either jamb or DLI by default they will be practicing practices Question which will be coming from the questions table and it will be in random order 
