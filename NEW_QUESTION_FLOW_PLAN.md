# New Question Flow Implementation Plan

## Current Flow vs New Flow

### Current Flow (What We Have)
1. User selects JAMB/DLI
2. User selects Practice/Past Questions
3. System finds an exam matching criteria
4. System gets questions from that exam

### New Flow (What We Need)

#### **Practice Questions Flow:**
1. User selects JAMB or DLI
2. User selects "Practice"
3. User selects subject(s) and number of questions
4. **System randomly selects questions from questions table** (not from specific exam)
5. Questions filtered by:
   - `exam_type` (JAMB or DLI) - from question's exam_types field
   - `subject` - from question's exam.subject
   - `type='practice'` - from question's exam.type

#### **Past Questions Flow:**
1. User selects JAMB or DLI
2. User selects "Past Questions"
3. User selects subject(s) and number of questions
4. **User selects YEAR** (from available years in exams table)
5. System gets questions from specific exam matching:
   - `exam_type` (JAMB or DLI)
   - `subject`
   - `type='past_question'`
   - `year` = selected year

## Key Changes Needed

### 1. Database Changes

#### Add `exam_types` to Questions Table
**Migration**: `add_exam_types_to_questions_table.php`

```php
Schema::table('questions', function (Blueprint $table) {
    // JSON array: ['JAMB'], ['DLI'], or ['JAMB', 'DLI']
    $table->json('exam_types')->nullable()->after('exam_id');
});
```

**Purpose**: 
- Question can be for JAMB only: `['JAMB']`
- Question can be for DLI only: `['DLI']`
- Question can be for both: `['JAMB', 'DLI']`

### 2. API Changes

#### New Endpoint: Get Random Practice Questions
**Route**: `GET /api/questions/practice`

**Parameters**:
- `exam_type`: JAMB or DLI
- `subject`: Subject name
- `count`: Number of questions needed
- `question_mode`: 'practice' (to filter by exam.type)

**Response**: Random questions matching criteria

#### New Endpoint: Get Available Years
**Route**: `GET /api/exams/years`

**Parameters**:
- `exam_type`: JAMB or DLI
- `subject`: Subject name (optional)
- `type`: 'past_question'

**Response**: List of available years

#### Update: Get Questions for Past Questions
**Route**: `GET /api/exams/{exam}/questions` (existing, but need to ensure it works with year)

**Parameters**: Same as before, but exam must match year

### 3. Mobile App Changes

#### Update TimeSelection Screen
- For **Practice**: Fetch random questions directly (new endpoint)
- For **Past Questions**: 
  - Add year selection step
  - Fetch exam by year
  - Get questions from that exam

#### New Screen: YearSelection
- Show available years for selected exam_type and subject
- User selects year
- Navigate to TimeSelection

### 4. Admin Changes

#### Update Question Create/Edit Form
- Add "Exam Types" selector (checkboxes):
  - ☐ JAMB
  - ☐ DLI
- At least one must be selected
- If both selected, question is available for both exam types

## Implementation Steps

### Step 1: Database Migration
1. Create migration to add `exam_types` to questions
2. Update Question model to cast `exam_types` as array
3. Run migration

### Step 2: Update Question Model
```php
protected $fillable = [
    // ... existing fields
    'exam_types',
];

protected $casts = [
    // ... existing casts
    'exam_types' => 'array',
];
```

### Step 3: Create New API Endpoints

#### Practice Questions Endpoint
```php
public function getPracticeQuestions(Request $request)
{
    $examType = $request->input('exam_type'); // JAMB or DLI
    $subject = $request->input('subject');
    $count = $request->input('count', 10);
    
    $questions = Question::whereHas('exam', function ($query) use ($subject) {
        $query->where('type', 'practice')
              ->where('subject', $subject)
              ->where('is_active', true);
    })
    ->where(function ($query) use ($examType) {
        $query->whereJsonContains('exam_types', $examType);
    })
    ->inRandomOrder()
    ->limit($count)
    ->with(['answers' => function ($query) {
        $query->select('id', 'question_id', 'answer_text', 'order')
              ->orderBy('order');
    }])
    ->get();
    
    return response()->json([
        'success' => true,
        'data' => $questions,
    ]);
}
```

#### Available Years Endpoint
```php
public function getAvailableYears(Request $request)
{
    $query = Exam::where('is_active', true)
        ->where('type', 'past_question');
    
    if ($request->has('exam_type')) {
        $query->where('exam_type', $request->exam_type);
    }
    
    if ($request->has('subject')) {
        $query->where('subject', $request->subject);
    }
    
    $years = $query->distinct()
        ->pluck('year')
        ->filter()
        ->sortDesc()
        ->values();
    
    return response()->json([
        'success' => true,
        'data' => $years,
    ]);
}
```

### Step 4: Update Mobile App Flow

#### For Practice:
```
ExamTypeSelection → QuestionModeSelection → SubjectSelection → TimeSelection
                                                                    ↓
                                                    Fetch random questions (new endpoint)
                                                                    ↓
                                                              ExamScreen
```

#### For Past Questions:
```
ExamTypeSelection → QuestionModeSelection → SubjectSelection → YearSelection (NEW)
                                                                        ↓
                                                              TimeSelection
                                                                        ↓
                                                    Fetch exam by year, then questions
                                                                        ↓
                                                              ExamScreen
```

### Step 5: Update Admin Question Forms
- Add exam_types checkboxes
- Validate at least one selected
- Save as JSON array

## Database Structure After Changes

```
questions
  ├── id
  ├── exam_id (still needed for organization)
  ├── exam_types (JSON: ['JAMB'] or ['DLI'] or ['JAMB', 'DLI'])
  ├── question_text
  ├── question_type
  └── ...

exams
  ├── id
  ├── exam_type (JAMB, DLI, etc.)
  ├── type (practice, past_question)
  ├── subject
  ├── year (for past_question)
  └── ...
```

## Questions to Consider

1. **Should questions still belong to an exam?**
   - Yes, for organization and admin management
   - But for practice, we query directly from questions table

2. **How to handle existing questions?**
   - Migration: Set exam_types based on exam.exam_type
   - Or: Allow null initially, require when editing

3. **What if not enough questions?**
   - Return available questions (less than requested)
   - Show warning to user
   - Admin should add more questions

4. **For multi-subject practice:**
   - Fetch random questions per subject
   - Combine into one set
   - Same as current flow
