# Question Management Enhancement Plan

## Overview
Enhance the admin question management system to support:
1. **Image uploads** for questions (diagrams, charts, photos)
2. **Multiple question types** (multiple choice, text input, numeric input)
3. **Better UI/UX** for question creation and editing

## Current State
- ✅ Basic CRUD for questions
- ✅ Multiple choice questions with answers
- ✅ CSV bulk upload
- ✅ Question bank view
- ❌ No image support
- ❌ Only multiple choice questions
- ❌ No text/numeric input questions

## Database Changes

### 1. Migration: Add Image Support
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_image_support_to_questions.php`

```php
Schema::table('questions', function (Blueprint $table) {
    $table->string('image_path')->nullable()->after('question_text');
    $table->string('image_url')->nullable()->after('image_path');
});
```

### 2. Migration: Extend Question Types
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_extend_question_types.php`

```php
Schema::table('questions', function (Blueprint $table) {
    // Change enum to support more types
    $table->string('question_type')->default('multiple_choice')->change();
    // Add: 'multiple_choice', 'text_input', 'numeric_input'
    
    // For text/numeric input questions, store expected answer
    $table->text('expected_answer')->nullable()->after('explanation');
    $table->text('answer_hints')->nullable()->after('expected_answer'); // For partial credit
});
```

**Note**: We'll need to handle the enum change carefully. Options:
- Option A: Drop and recreate column (loses data if not careful)
- Option B: Add new column, migrate data, drop old (safer)

### 3. Update Answers Table (if needed)
For text/numeric input, we might not need the answers table. We can:
- Keep answers table for multiple choice
- Use `expected_answer` field for text/numeric questions
- Or create a separate structure

## Backend Changes

### 1. Update Question Model
- Add `image_path` and `image_url` to fillable
- Add `expected_answer` and `answer_hints` to fillable
- Add accessor for full image URL
- Add method to check if question has image

### 2. Update QuestionController
- **Store/Update methods**: Handle image upload
  - Validate image file (jpg, png, gif, webp)
  - Store in `storage/app/public/questions`
  - Generate public URL
  - Handle image deletion on update/delete
- **Validation**: Different rules based on question type
  - Multiple choice: require answers array
  - Text input: require expected_answer
  - Numeric input: require expected_answer (numeric)
- **Bulk Upload**: Support image column (optional, URL or path)

### 3. File Storage Setup
- Configure Laravel storage: `php artisan storage:link`
- Create `storage/app/public/questions` directory
- Set up image optimization (optional, using Intervention Image or similar)

## Frontend Changes

### 1. Question Type Selection
Add dropdown/radio to select question type:
- Multiple Choice (default)
- Text Input
- Numeric Input

### 2. Conditional Form Fields
Based on question type, show/hide:
- **Multiple Choice**: Show answers array (current implementation)
- **Text Input**: Show expected answer field, case sensitivity option
- **Numeric Input**: Show expected answer (numeric), tolerance/range option

### 3. Image Upload Component
- File input with preview
- Drag & drop support (optional)
- Image preview before upload
- Remove/replace image option
- Show existing image on edit

### 4. Enhanced Create/Edit Forms
**File**: `resources/js/pages/admin/questions/create.tsx`
**File**: `resources/js/pages/admin/questions/edit.tsx`

Changes:
- Add question type selector
- Add image upload section
- Conditionally render answer fields or expected answer field
- Better validation messages

## Question Types Implementation

### 1. Multiple Choice (Current)
- Keep existing implementation
- Answers array with is_correct flag
- Works with existing mobile app

### 2. Text Input
- Single expected answer field
- Optional: case sensitivity toggle
- Optional: multiple acceptable answers (comma-separated)
- Optional: partial credit hints

### 3. Numeric Input
- Expected numeric answer
- Optional: tolerance/range (e.g., ±0.1)
- Optional: unit specification

## Mobile App Updates (Future)

### 1. Display Question Images
- Show image above question text
- Handle image loading/errors
- Responsive sizing

### 2. Handle Text/Numeric Input
- Show text input field instead of multiple choice
- Validate input format
- Show feedback on submission

## Implementation Order

### Phase 1: Database & Backend Foundation
1. ✅ Create migration for image support
2. ✅ Create migration for question types
3. ✅ Update Question model
4. ✅ Update QuestionController (image upload)
5. ✅ Test image upload functionality

### Phase 2: Frontend - Image Support
6. ✅ Add image upload to create form
7. ✅ Add image upload to edit form
8. ✅ Add image preview component
9. ✅ Test image upload/display

### Phase 3: Frontend - Question Types
10. ✅ Add question type selector
11. ✅ Add conditional form fields
12. ✅ Update validation
13. ✅ Test all question types

### Phase 4: Mobile App (Later)
14. Display images in questions
15. Handle text/numeric input questions

## File Structure

```
learning-api/
├── database/migrations/
│   ├── YYYY_MM_DD_HHMMSS_add_image_support_to_questions.php
│   └── YYYY_MM_DD_HHMMSS_extend_question_types.php
├── app/Models/
│   └── Question.php (updated)
├── app/Http/Controllers/Admin/
│   └── QuestionController.php (updated)
└── resources/js/
    ├── pages/admin/questions/
    │   ├── create.tsx (updated)
    │   └── edit.tsx (updated)
    └── components/
        └── ImageUpload.tsx (new)
```

## UI/UX Considerations

### Image Upload
- Max file size: 5MB
- Supported formats: JPG, PNG, GIF, WebP
- Auto-resize large images (optional)
- Show file size and dimensions
- Loading state during upload

### Question Type Selection
- Clear labels and descriptions
- Show example for each type
- Disable/enable relevant fields dynamically

### Form Validation
- Real-time validation
- Clear error messages
- Required field indicators

## Testing Checklist

- [ ] Create multiple choice question with image
- [ ] Create multiple choice question without image
- [ ] Create text input question
- [ ] Create numeric input question
- [ ] Edit question and change type
- [ ] Edit question and change image
- [ ] Delete question with image (verify image deleted)
- [ ] Bulk upload with images (if supported)
- [ ] Validate all question types work in mobile app

## Future Enhancements

1. **Question Templates**: Pre-defined question structures
2. **Rich Text Editor**: For question text formatting
3. **Question Categories/Tags**: Better organization
4. **Question Difficulty Levels**: Easy, Medium, Hard
5. **Question Analytics**: Track which questions are most missed
6. **Image Library**: Reuse uploaded images across questions
7. **Question Import from PDF**: OCR or manual extraction
8. **Question Versioning**: Track changes over time
