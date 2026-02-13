# Unilag Department Selection Flow - Implementation Plan

## Overview
Add department selection step to Unilag (DLI) practice flow. Users will select department → subject/course → question count → time → start exam.

## Current Flow
```
Home → Click "Practice Unilag" → /dli/practice
  → Select Subject (all DLI subjects shown)
  → Select Question Count
  → Select Time
  → Start Exam
```

## New Flow
```
Home → Click "Practice Unilag" → /unilag/departments
  → Select Department
  → /unilag/departments/{department}/subjects
  → Select Subject/Course (filtered by department)
  → Select Question Count
  → Select Time
  → Start Exam
```

---

## Database Changes

### 1. Create `departments` Table
**Migration**: `2026_02_10_000002_create_departments_table.php`

```php
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // e.g., "Faculty of Science", "Faculty of Arts"
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Link Subjects to Departments
**Migration**: `2026_02_10_000003_add_department_id_to_subjects_table.php`

```php
Schema::table('subjects', function (Blueprint $table) {
    $table->foreignId('department_id')->nullable()->after('order')
        ->constrained('departments')->onDelete('set null');
    $table->index('department_id');
});
```

**Why nullable?** 
- Existing subjects won't break
- JAMB subjects don't need departments (only Unilag/DLI)
- Gradual migration possible

---

## Backend (API) Changes

### 1. Create Department Model
**File**: `app/Models/Department.php`

```php
class Department extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
```

### 2. Update Subject Model
**File**: `app/Models/Subject.php`

```php
// Add to fillable: 'department_id'
// Add relationship:
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}
```

### 3. New API Endpoints

#### GET `/api/departments`
**Controller**: `ExamController@departments`

**Purpose**: Get list of active departments (for Unilag/DLI)

**Response**:
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Faculty of Science", "slug": "faculty-of-science", "description": "..." },
    { "id": 2, "name": "Faculty of Arts", "slug": "faculty-of-arts", "description": "..." }
  ]
}
```

#### GET `/api/departments/{department}/subjects`
**Controller**: `ExamController@departmentSubjects`

**Purpose**: Get subjects for a specific department (filtered by exam_type)

**Query params**: `exam_type` (required: DLI/UNILAG)

**Response**:
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Mathematics", "slug": "mathematics" },
    { "id": 2, "name": "Physics", "slug": "physics" }
  ]
}
```

### 4. Update Existing Endpoint

#### GET `/api/exams/subjects`
**Update**: `ExamController@subjects`

**Add optional filter**: `department_id` or `department_slug`

**Logic**:
- If `department_id` or `department_slug` provided → filter subjects by department
- Otherwise → return all subjects (backward compatible)

---

## Frontend (Web) Changes

### 1. New Route Structure

**Routes** (`web/src/router/routes.tsx`):
```typescript
{
  path: 'unilag/departments',
  element: <UnilagDepartmentSelection />,
},
{
  path: 'unilag/departments/:departmentSlug/subjects',
  element: <UnilagSubjectSelection />,
},
// Keep old route for backward compatibility (redirect to departments)
{
  path: 'dli/practice',
  element: <Navigate to="/unilag/departments" replace />,
},
```

### 2. New Pages

#### Page 1: Department Selection
**File**: `web/src/pages/unilag/department-selection.tsx`

**Features**:
- Fetch departments from API (`GET /api/departments`)
- Display as cards/list
- On select → navigate to `/unilag/departments/{slug}/subjects`

**UI**: Similar to home page cards (JAMB/Unilag cards)

#### Page 2: Subject Selection (Updated)
**File**: `web/src/pages/unilag/subject-selection.tsx` (new, based on current `dli/practice-selection.tsx`)

**Features**:
- Get `departmentSlug` from route params
- Fetch subjects: `GET /api/departments/{departmentSlug}/subjects?exam_type=DLI`
- Select subject → question count → time → start exam
- Same flow as current `dli/practice-selection.tsx` but filtered by department

### 3. Update Home Page Link

**File**: `web/src/pages/home.tsx`

**Change**:
```typescript
onClick={() => navigate("/unilag/departments")}  // Instead of "/dli/practice"
```

### 4. Update API Client

**File**: `web/src/apis/exam.ts`

**Add**:
```typescript
export const getDepartments = async (): Promise<{
  success: boolean;
  data: Array<{ id: number; name: string; slug: string }>;
}> => {
  const response = await api.get('/departments');
  return response.data;
};

export const getDepartmentSubjects = async (
  departmentSlug: string,
  examType: string
): Promise<{ success: boolean; data: string[] }> => {
  const response = await api.get(`/departments/${departmentSlug}/subjects`, {
    params: { exam_type: examType },
  });
  return response.data;
};
```

---

## Implementation Steps

### Phase 1: Database & Backend Foundation
1. ✅ Create `departments` migration
2. ✅ Create `Department` model
3. ✅ Add `department_id` to `subjects` migration
4. ✅ Update `Subject` model (add `department()` relationship)
5. ✅ Create seeder for departments (sample Unilag departments)
6. ✅ Add API endpoints: `GET /api/departments`, `GET /api/departments/{slug}/subjects`
7. ✅ Update `GET /api/exams/subjects` to support `department_id` filter

### Phase 2: Frontend - Department Selection
8. ✅ Create `unilag/department-selection.tsx` page
9. ✅ Add route `/unilag/departments`
10. ✅ Update home page link to `/unilag/departments`
11. ✅ Add `getDepartments()` to API client

### Phase 3: Frontend - Subject Selection (Filtered)
12. ✅ Create `unilag/subject-selection.tsx` (copy from `dli/practice-selection.tsx`)
13. ✅ Update to fetch subjects by department slug
14. ✅ Add route `/unilag/departments/:departmentSlug/subjects`
15. ✅ Add `getDepartmentSubjects()` to API client
16. ✅ Update breadcrumbs in `app-layout.tsx`

### Phase 4: Migration & Testing
17. ✅ Run migrations
18. ✅ Seed departments
19. ✅ Link existing DLI subjects to departments (manual or seeder)
20. ✅ Test full flow: Home → Department → Subject → Questions → Time → Exam

---

## Example Department Structure (Unilag)

**Departments**:
- Faculty of Science
- Faculty of Arts
- Faculty of Social Sciences
- Faculty of Education
- Faculty of Engineering
- Faculty of Law
- Faculty of Business Administration
- Faculty of Environmental Sciences

**Example Subject-Department Mapping**:
- **Faculty of Science**: Mathematics, Physics, Chemistry, Biology
- **Faculty of Arts**: English Language, Literature in English
- **Faculty of Social Sciences**: Economics, Government, Geography
- **Faculty of Business Administration**: Commerce, Accounting

---

## Backward Compatibility

### Option A: Keep Old Route (Redirect)
- `/dli/practice` → redirects to `/unilag/departments`
- Old bookmarks/links still work

### Option B: Keep Old Route (Show All Subjects)
- `/dli/practice` → shows all DLI subjects (no department filter)
- `/unilag/departments` → new flow with department selection

**Recommendation**: Option A (redirect) for cleaner UX, but Option B is safer for existing users.

---

## Admin Panel Updates ✅

**Admin can now**:
- ✅ Create/edit departments (through `DepartmentController`)
- ✅ Assign subjects to departments (required when creating/editing DLI/UNILAG subjects)
- ✅ View subjects by department (through department-subject relationship)

**Files created/updated**:
- ✅ `app/Http/Controllers/Admin/DepartmentController.php` (created)
- ✅ `routes/web.php` (added admin department routes)
- ✅ `app/Http/Controllers/Admin/SubjectController.php` (updated to require department for DLI/UNILAG)
- ⏳ `resources/js/pages/admin/departments/` (admin pages to be created in frontend)
- ⏳ `resources/js/pages/admin/subjects/create.tsx` (needs department dropdown)
- ⏳ `resources/js/pages/admin/subjects/edit.tsx` (needs department dropdown)

---

## Questions to Clarify

1. **Should JAMB subjects also have departments?** 
   - Probably not - departments are Unilag-specific
   - Keep JAMB flow as-is (no department selection)

2. **What if a subject belongs to multiple departments?**
   - Current plan: one subject = one department
   - Alternative: many-to-many (more complex)

3. **What about existing subjects without departments?**
   - Migration sets `department_id` to `nullable`
   - Can show "Unassigned" or assign manually
   - Or: create "General" department for unassigned subjects

4. **Should department selection be required?**
   - Yes for new flow (`/unilag/departments`)
   - No for old flow (`/dli/practice` - if we keep it)

---

## File Structure After Implementation

```
api/
├── app/
│   ├── Models/
│   │   ├── Department.php (new)
│   │   └── Subject.php (updated)
│   └── Http/Controllers/Api/
│       ├── ExamController.php (updated)
│       └── DepartmentController.php (new, optional)
├── database/
│   ├── migrations/
│   │   ├── 2026_02_10_000002_create_departments_table.php (new)
│   │   └── 2026_02_10_000003_add_department_id_to_subjects_table.php (new)
│   └── seeders/
│       └── DepartmentSeeder.php (new)

web/
├── src/
│   ├── pages/
│   │   ├── unilag/
│   │   │   ├── department-selection.tsx (new)
│   │   │   └── subject-selection.tsx (new)
│   │   └── dli/
│   │       └── practice-selection.tsx (keep for backward compat or remove)
│   ├── apis/
│   │   └── exam.ts (updated - add getDepartments, getDepartmentSubjects)
│   └── router/
│       └── routes.tsx (updated - add new routes)
```

---

## Next Steps

1. **Confirm department list** with client (exact names, structure)
2. **Decide on backward compatibility** (redirect vs keep old route)
3. **Start with Phase 1** (database + backend)
4. **Then Phase 2-3** (frontend pages)
5. **Test thoroughly** before deploying
