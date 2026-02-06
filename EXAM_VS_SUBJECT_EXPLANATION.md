# Exam vs Subject - Explanation

## Current Structure (What We Have Now)

### **Subject** (in `subjects` table)
- **Purpose**: A category/topic (e.g., "Mathematics", "English", "Physics")
- **What it is**: Just a list managed by admin
- **Example**: 
  - Name: "Mathematics"
  - Slug: "mathematics"
  - Is Active: true

### **Exam** (in `exams` table)
- **Purpose**: A specific practice set or past question paper
- **What it is**: A collection of questions with settings
- **Has a `subject` field**: Just a TEXT field (not linked to `subjects` table!)
- **Example**:
  - Title: "JAMB Mathematics Practice Set 1"
  - Subject: "Mathematics" (just text, not a foreign key)
  - Exam Type: JAMB
  - Duration: 60 minutes

### **Question** (in `questions` table)
- **Purpose**: Individual questions
- **Belongs to**: Exam (via `exam_id` foreign key)
- **Does NOT directly belong to**: Subject

## Current Relationship

```
Subject (subjects table)
  └── NOT LINKED TO EXAMS (just a reference list)

Exam (exams table)
  ├── subject: "Mathematics" (just text, not foreign key!)
  └── questions (one exam has many questions)
        └── Question belongs to Exam
```

## The Problem

1. **Subjects table exists** but is NOT being used properly
2. **Exams.subject** is just a text field, not linked to `subjects` table
3. **Questions** belong to Exams, not directly to Subjects
4. No way to get "all Mathematics questions" across all exams

## What You Probably Want

### Option 1: Questions Belong to Subjects Directly
```
Subject (subjects table)
  └── questions (one subject has many questions)
        └── Question belongs to Subject
```

### Option 2: Exams Linked to Subjects Table (Better)
```
Subject (subjects table)
  └── exams (one subject has many exams)
        └── Exam belongs to Subject (via foreign key)
              └── questions (one exam has many questions)
                    └── Question belongs to Exam
```

## Recommended Solution: Link Exams to Subjects Table

### What to Change:

1. **Add foreign key** from `exams` to `subjects`:
   - Change `exams.subject` from TEXT to `subject_id` (foreign key)
   - Or keep both: `subject_id` (foreign key) + `subject` (text for backward compatibility)

2. **Update Exam Model**:
   ```php
   public function subject(): BelongsTo
   {
       return $this->belongsTo(Subject::class);
   }
   ```

3. **Update Question Model** (optional - to get subject directly):
   ```php
   public function subject()
   {
       return $this->exam->subject;
   }
   ```

## Difference Summary

| Aspect | Subject | Exam |
|--------|---------|------|
| **What it is** | Category/Topic | Practice Set/Paper |
| **Examples** | Mathematics, English | "JAMB Math Practice 1", "2023 JAMB Past Questions" |
| **Contains** | Nothing directly | Questions |
| **Purpose** | Organization | Actual test/practice |
| **Current Link** | None (just text in exam) | Has questions |

## Real-World Analogy

- **Subject** = "Mathematics" (the topic)
- **Exam** = "JAMB Mathematics Practice Set 1" (a specific test)
- **Question** = "What is 2 + 2?" (individual question in that test)

You can have:
- Multiple exams for the same subject
- Each exam has different questions
- Questions are organized by exam, not directly by subject

## What You Need to Decide

1. **Keep current structure** (subject is just text in exam)
   - Simple but less organized
   - Can't easily query "all Math questions"

2. **Link exams to subjects table** (recommended)
   - Better organization
   - Can query by subject
   - Questions indirectly belong to subject via exam

3. **Questions directly belong to subjects** (more complex)
   - Would need to change structure significantly
   - Questions would need `subject_id` field
   - Exams might become less important


