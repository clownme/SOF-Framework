# SOF Configuration Framework

## Purpose

The SOF Configuration Framework provides centralized configuration for reusable framework data.

Its purpose is to eliminate duplicated hard-coded values across SOF and provide a single source of truth for shared definitions.

## Current Configuration Areas

```text
COAI Regions

COAI Region configuration is store in:
includes/config/coai-regions.php

This file provides:
coai_get_regions()
coai_get_coai_region_config()
coai_get_coai_region()
coai_get_coai_region_name()
coai_get_coai_region_code()

Design Principles

• Configuration is separate from logic
• Frameworks consume configuration through public functions
• Stable internal keys are separate from user-facing names
• Existing production behavior must remain backward compatible
• Framework-specific behavior should not be mixed into base configuration too early

Current Status

• Configuration is separate from logic
• Frameworks consume configuration through public functions
• Stable internal keys are separate from user-facing names
• Existing production behavior must remain backward compatible
• Framework-specific behavior should not be mixed into base configuration too early

Future Direction

Future configuration may include:

• Region storage destinations
• Region communication routing
• Report metadata
• Permission definitions
• Membership level configuration
• Usergroup configuration