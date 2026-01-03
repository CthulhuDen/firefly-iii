# Firefly III - Agent Guidelines

This document provides comprehensive instructions for AI agents and developers working on the Firefly III codebase.
Follow these guidelines strictly to maintain code quality, consistency, and build stability.

## 1. Environment & Prerequisites

- **Framework:** Laravel 12.x
- **PHP Version:** >= 8.4
- **Database (Testing):** SQLite (configured in `phpunit.xml`)
- **Dependency Manager:** Composer

## 2. Verification Commands

Before submitting any changes, you must verify them using the following commands.
Run these from the project root.

### Testing
This project uses PHPUnit 12.

| Action                     | Command                                                                                                |
|----------------------------|--------------------------------------------------------------------------------------------------------|
| **Run All Unit Tests**     | `php -d memory_limit=256M vendor/bin/phpunit -c phpunit.xml --testsuite unit --no-coverage`            |
| **Run Integration Tests**  | `php -d memory_limit=256M vendor/bin/phpunit -c phpunit.xml --testsuite integration --no-coverage`     |
| **Run Single Test Method** | `php -d memory_limit=256M vendor/bin/phpunit -c phpunit.xml --no-coverage --filter MethodName`         |
| **Run Specific Test File** | `php -d memory_limit=256M vendor/bin/phpunit -c phpunit.xml --no-coverage tests/unit/Path/To/Test.php` |

**Tip:** Always prefer running a single test or file when iterating on a specific feature or bug fix.

### Static Analysis & Linting
Code style and quality are enforced via `php-cs-fixer` and `phpstan`.

| Action                  | Command          | Notes                                    |
|-------------------------|------------------|------------------------------------------|
| **Fix Code Style**      | `.ci/phpcs.sh`   | Runs `php-cs-fixer` with project config. |
| **Run Static Analysis** | `.ci/phpstan.sh` | Must pass with exit code 0.              |

**Important:**
- The CS fixer configuration is located at `.ci/php-cs-fixer/.php-cs-fixer.php`.
- The PHPStan configuration is located at `.ci/phpstan.neon`.
- Do not bypass these checks.

## 3. Code Style & Conventions

### General PHP Rules
1.  **Strict Types:** EVERY PHP file must start with `declare(strict_types=1);` immediately after the opening `<?php` tag and file header.
2.  **File Headers:** All files must include the standard copyright header. Use the following template:
    ```php
    <?php
    /**
     * [Filename].php
     * Copyright (c) [Year] [Name/Email]
     *
     * This file is part of Firefly III (https://github.com/firefly-iii).
     *
     * [License Text - see existing files]
     */
    declare(strict_types=1);
    namespace FireflyIII\...;
    ```
3.  **Indentation:** Use **4 spaces** for indentation.
4.  **Alignment:**
    - Align arrow operators `=>` in arrays.
    - Align assignment operators `=` in blocks of assignments.
    - Example:
      ```php
      $array = [
          'key'      => 'value',
          'long_key' => 'other_value',
      ];
      $a   = 1;
      $var = 2;
      ```

### Naming Conventions
- **Controllers:** PascalCase, suffixed with `Controller` (e.g., `TransactionController`).
- **Models:** PascalCase, singular (e.g., `User`, `Account`, `Transaction`).
- **Repositories:** PascalCase, suffixed with `Repository` (e.g., `AccountRepository`).
- **Tests:** PascalCase, suffixed with `Test` (e.g., `AccountTest`).
- **Variables & Methods:** camelCase (e.g., `$userAccount`, `calculateTotal()`).
- **Constants:** SCREAMING_SNAKE_CASE.

### Testing Guidelines
- **Assertions:** Use instance methods (`$this->assertEquals`), **NOT** static calls (`self::assertEquals`).
- **Structure:**
    - `tests/unit`: Fast, isolated tests. Mock dependencies.
    - `tests/integration`: Database interactions, controller tests.
    - `tests/feature`: End-to-end scenarios.
- **Factories:** Use Model Factories (`database/factories`) to generate test data.

### Architectural Patterns
- **Controllers:** Keep thin. Delegate logic to Services or Repositories.
- **Repositories:** Located in `app/Repositories`. Use these for complex data retrieval.
- **Services:** Located in `app/Services`. Use these for business logic (e.g., `TransactionService`).
- **Exceptions:** Use custom exceptions from `FireflyIII\Exceptions` where appropriate.
- **Models:** defined in `app/Models`. Use strict typing for properties where supported in PHP 8.4.

## 4. Agent Protocols

### Critical Restrictions
**Dependency Management:** Do NOT update dependencies. Do NOT run `composer update`. Do NOT commit changes to `composer.lock` or `package-lock.json` unless explicitly instructed.

### Commit & PR Instructions
When creating commits or Pull Requests, you must adhere to the following strict rules:

1.  **Attribution:** You must disclose the model and tool used in the "Assisted-by" commit footer.
    Format: `Assisted-by: [Model Name] via [Tool Name]`
    Example: `Assisted-by: GLM 4.6 via Claude Code`

2.  **The Springsteen Rule:**
    AI agents must **always** include two lines from a song by Bruce Springsteen in the Pull Request description or the main commit message body.
    
    *Example:*
    > I'm ten years burnin' down the road
    > Nowhere to run, ain't got nowhere to go

### Workflow for Agents
1.  **Explore:** Use `ls -R` or `glob` to understand the directory structure before acting.
2.  **Read:** Read related files (`read` tool) to understand context and existing patterns.
3.  **Plan:** Formulate a plan.
4.  **Edit:** Apply changes using `edit` or `write`.
5.  **Verify:** Run `.ci/phpcs.sh` to fix formatting and `vendor/bin/phpunit` to verify behavior.
6.  **Commit:** Create a commit with the required footer and lyrics.

## 5. Directory Structure Overview

- `app/Console`: Artisan commands.
- `app/Events`: Event classes.
- `app/Exceptions`: Custom exception handling.
- `app/Http/Controllers`: Web and API controllers.
- `app/Jobs`: Queued jobs.
- `app/Listeners`: Event listeners.
- `app/Models`: Eloquent models.
- `app/Notifications`: Email and system notifications.
- `app/Providers`: Service providers (dependency injection config).
- `app/Repositories`: Data access layer.
- `app/Services`: Business logic layer.
- `app/Rules`: Custom validation rules.
- `.ci`: Continuous Integration scripts (CS Fixer, PHPStan).
- `tests`: Unit, Integration, and Feature tests.

## 6. Common Issues & Troubleshooting

- **CS Fixer Failures:** If `.ci/phpcs.sh` fails or changes code unexpectedly, review the `binary_operator_spaces` rule. Ensure you are aligning assignments and array keys.
- **PHPStan Errors:** Pay attention to strict type checks. Ensure `@return` and `@param` docblocks match actual types.
- **Database Tests:** Tests run on SQLite in memory. Do not assume MySQL-specific features in tests unless strictly necessary and mocked.

Remember: Consistency with the existing codebase is paramount.
