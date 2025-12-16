# BW Article Housekeeping

Joomla 5/6 scheduled task plugin for automated article management operations.

## Task Types

| Task | Description |
|------|-------------|
| **Move Articles to Category** | Move articles older than X days to a target category |
| **Archive Articles** | Archive articles older than X days |
| **Unpublish Articles** | Unpublish articles older than X days |
| **Change Article Access** | Change access level of articles older than X days |

## Configuration

Each task type supports the following options:

- **Source Category** - Category to process articles from
- **Include Subcategories** - Process articles in child categories
- **Age Threshold (Days)** - Articles older than this will be affected
- **Date Field** - Which date to check: Created, Modified, or Publish Start
- **Article State** - Filter by Published only or All states
- **Dry Run** - Preview affected articles without making changes (default: on)

### Task-Specific Options

- **Move**: Target Category selector
- **Access**: Target Access Level selector

## Installation

1. Install via Joomla installer (Extensions > Manage > Install)
2. Navigate to System > Scheduled Tasks
3. Click "New" and select the desired task type
4. Configure the task parameters
5. Set the execution schedule

## Dry Run Mode

Dry run is enabled by default. When active, the task logs which articles would be affected without making changes. Disable dry run to execute the actual operation.

## Requirements

- Joomla 5.0+ or 6.0+
- PHP 8.1+

## Licence

GNU General Public License version 2 or later
