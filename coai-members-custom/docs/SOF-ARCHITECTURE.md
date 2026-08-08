SOF Architecture

1. Architectural Principles

2. System Layers

3. Frameworks

4. Execution Flow

5. Core Components

6. Data Flow

7. Extension Points

8. Future Frameworks
9. 
Presentation Layer

    Admin UI
    Member UI
    Shortcodes

            ↓

Configuration Layer

    Region Configuration
    Export Configuration
    Future Frameworks

            ↓

Component Layer

    Region Selector
    Validators
    Builders

            ↓

Service Layer

    Distribution Service
    Reporting Service

            ↓

Repository Layer

    Member Repository
    Export Repository

            ↓

External Services

    Google Drive
    Email
    Future APIs
    
A-001
Unified Distribution Engine

Status:
Approved

Reason:
Single, Multiple and All Regions all normalize to arrays before execution.

Result:
One execution path.

ADR	Decision
A-001	Unified Distribution Execution Engine
A-002	Configuration-driven Region Resolution
A-003	Repository Layer Isolation
A-004	Framework-based Architecture
A-005	Reporting Pipeline Standardization

Decision:
All distribution requests normalize to an array of region keys and are processed by a single Distribution Execution Engine.

Presentation Layer no longer performs distribution execution directly.

## A-001 – Unified Distribution Execution Engine

Principle:
One process, multiple ways.

Meaning:
The Distribution Framework has one execution process.
Different tools may select regions in different ways, but all selected regions are normalized into the same array structure before execution.

Decision:
Create one Distribution Service execution function that accepts an array of COAI Region identifiers.

Single Region exports pass an array containing one region.

All Region exports pass an array containing all configured regions.

The Distribution Service loops through the array and delegates each single-region export to the existing Google Service function:
coai_google_export_region($region)

Reason:
The existing Google export function is already a stable single-region execution unit.
The missing All Regions handler can be implemented by reusing the same engine rather than creating another separate execution path.
