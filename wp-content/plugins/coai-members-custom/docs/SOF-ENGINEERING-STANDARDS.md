Services own business logic.
Repositories own database access.
Drivers own external systems.
Presentation never talks directly to repositories.
One recovery point per milestone.
One change, one test.
One source of truth for business data.
Design interfaces before implementations.
Architecture should emerge from the codebase—not be forced onto it.
Every refactor must leave the system better than it was yesterday.
Every framework should orchestrate work rather than perform work.

If a function cannot be understood in about one minute, it probably has more than one responsibility.

"Build software that is simple to understand, safe to extend, and pleasant to maintain."

# SOF Engineering Standards

## Change Implementation Standard

Effective with SOF v4.2.2, every implementation step shall follow the same engineering format.

This standard applies to all framework development, bug fixes, enhancements, refactoring, and architectural changes.

---

## 1. Step Number

Each change shall be assigned a sequential implementation step.

Example:

```
SOF v4.2.2 – Step 08
```

---

## 2. Purpose

Briefly describe the objective of the change.

Example:

```
Introduce reusable COAI Region configuration metadata.
```

---

## 3. Files Modified

Every implementation step shall explicitly list every file expected to change.

Example:

```
Modified

includes/config/coai-regions.php
```

---

## 4. Files Not Modified

List nearby or related files that are intentionally not part of the change.

Example:

```
Not Modified

includes/components/region-selector.php

includes/distribution/distribution-service.php

includes/admin/admin-members.php
```

This establishes the expected scope of the implementation.

---

## 5. Code Changes

Describe the exact additions, removals, or modifications before implementation begins.

Example:

```
Add function:

coai_get_coai_region_config()
```

Do not combine unrelated modifications into a single implementation step.

---

## 6. Expected Result

Describe what the application should do after the change.

Example:

```
No visible UI changes.

No change in Member Directory behavior.

Configuration metadata available for future frameworks.
```

---

## 7. Test Procedure

Every implementation step shall include a verification checklist.

Example:

```
Refresh Member Directory.

Select a COAI Region.

Click Apply.

Verify member list.

Verify counts.

Verify no PHP errors.
```

Implementation is not considered complete until testing succeeds.

---

## 8. Architectural Impact

Document which SOF architectural layers are affected.

Example:

```
Configuration Layer
    Enhanced

Components
    No Change

Distribution Framework
    No Change

Communications Framework
    No Change

Reporting Framework
    No Change
```

This provides an architectural audit trail for future development.

---

## 9. Backward Compatibility

Every implementation shall preserve existing production functionality unless an intentional breaking change has been approved.

Compatibility should be maintained whenever practical.

---

## 10. Recovery Discipline

Before any milestone or high-risk implementation:

• Create Recovery Point

• Update Engineering documentation

• Commit to Git

• Verify repository integrity

• Begin implementation

No implementation should begin without a valid recovery point.

---

## Guiding Principle

SOF development follows an Architecture First methodology.

The sequence is:

```
Architecture

↓

Framework

↓

Component

↓

Service

↓

Repository

↓

Implementation

↓

Testing

↓

Documentation

↓

Recovery Point
```

This ensures every enhancement strengthens the overall architecture while maintaining stability, maintainability, and future extensibility.

==================================================
REFACTOR STANDARD
==================================================

Every architectural refactor shall begin with an
inventory before code changes are made.

The inventory shall identify:

1. Files using the feature

2. Public interfaces

3. Internal dependencies

4. Downstream consumers

5. Backward compatibility concerns

6. Proposed migration path

7. Testing strategy

No implementation shall begin until the inventory
has been completed.

==================================================
IMPLEMENTATION PHASES
==================================================

Phase 1
Discovery

↓

Phase 2
Architecture

↓

Phase 3
Implementation

↓

Phase 4
Validation

↓

Phase 5
Documentation

↓

Phase 6
Recovery Point

RECORD POINTS

D-### = Discovery Finding
A-### = Architectural Decision
I-### = Implementation Milestone (optional if we need it later)
V-### = Validation Result (optional)
RP## = Recovery Point

That gives SOF a consistent engineering record that will scale well as the project grows.

