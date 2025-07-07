# Git Subprocess Agent Prompt

Use this prompt to deploy a Claude agent to work on a specific subtask in a separate git branch/worktree.

## Agent Deployment Prompt

```
I need you to work on a specific focused task in a separate git branch. Here are the details:

**PROJECT CONTEXT:**
- Repository: [REPO_NAME]
- Current working directory: [CURRENT_DIR] 
- Main branch we're working from: [CURRENT_BRANCH]
- Base commit/state: [COMMIT_HASH or "current HEAD"]

**TASK ASSIGNMENT:**
[SPECIFIC_TASK_DESCRIPTION]

**SETUP INSTRUCTIONS:**
1. Create a new git worktree: `git worktree add ../[TASK_NAME]-worktree [BASE_BRANCH]`
2. Navigate to the new worktree: `cd ../[TASK_NAME]-worktree`
3. Create and checkout a new branch: `git checkout -b feature/[TASK_NAME]`
4. Work exclusively in this isolated environment

**SPECIFIC TASK:**
[DETAILED_TASK_REQUIREMENTS]

**SUCCESS CRITERIA:**
- [ ] [CRITERIA_1]
- [ ] [CRITERIA_2] 
- [ ] [CRITERIA_3]
- [ ] All tests pass
- [ ] Code follows project conventions
- [ ] Ready for PR/merge back to main branch

**CONSTRAINTS:**
- Work only on the assigned task
- Don't modify files outside the task scope
- Commit frequently with clear messages
- Use project's coding standards and patterns
- Test your changes thoroughly

**DELIVERABLES:**
1. Completed implementation
2. Tests passing
3. Clean commit history
4. PR-ready branch
5. Summary of changes made

**RETURN TO ME:**
- Branch name: feature/[TASK_NAME]
- Commit hash of final state
- Summary of what was accomplished
- Any issues or blockers encountered
- Instructions for merging/reviewing

Work independently and focus solely on this task. Don't wait for further instructions - execute the complete task end-to-end.
```

## Example Usage Templates

### For Conduit SecurityTest Fix:
```
**TASK ASSIGNMENT:** Fix SecurityTest.php loop abortion issue

**SPECIFIC TASK:**
Fix the SecurityTest.php where expectException() stops after first exception, leaving other malicious inputs untested. The test needs to validate ALL malicious inputs, not just the first one.

**SUCCESS CRITERIA:**
- [ ] All malicious input payloads are tested
- [ ] Test doesn't abort after first exception
- [ ] Each malicious input throws expected InvalidArgumentException
- [ ] Test coverage is complete
- [ ] All existing tests still pass
```

### For GitHub Client Enhancement:
```
**TASK ASSIGNMENT:** Add comprehensive error handling to RepoResource

**SPECIFIC TASK:**
Enhance error handling in RepoResource.php methods as suggested by CodeRabbit review. Add try-catch blocks, proper exception messages, and validation for API calls.

**SUCCESS CRITERIA:**
- [ ] All RepoResource methods have proper error handling
- [ ] Meaningful exception messages with context
- [ ] Input validation for repository names and parameters
- [ ] Tests cover error scenarios
- [ ] CodeRabbit suggestions addressed
```

### For Architecture Improvements:
```
**TASK ASSIGNMENT:** Implement ComponentPersistenceInterface

**SPECIFIC TASK:**
Create ComponentPersistenceInterface for architectural consistency with other services in the Conduit project. Follow the same patterns as ComponentManagerInterface and ComponentStorageInterface.

**SUCCESS CRITERIA:**
- [ ] Interface defined with proper method signatures
- [ ] ComponentPersistence class implements the interface
- [ ] AppServiceProvider updated with interface binding
- [ ] Follows existing interface patterns
- [ ] All tests pass
```

## Quick Deploy Commands

For common scenarios, use these quick commands:

### Deploy agent for SecurityTest fix:
```bash
# In current terminal, stay on main work
echo "Deploying agent for SecurityTest fix..."

# Agent command (run in separate Claude Code session):
cd /path/to/project && git worktree add ../securitytest-fix feature/current-branch && cd ../securitytest-fix && git checkout -b fix/security-test-loop
```

### Deploy agent for specific file fix:
```bash
# Template
git worktree add ../{task-name}-fix {base-branch} && cd ../{task-name}-fix && git checkout -b fix/{task-name}
```

## Benefits

- ✅ **Parallel Development**: Work on multiple tasks simultaneously
- ✅ **Isolated Environment**: No conflicts with main work
- ✅ **Clean History**: Each task gets its own branch
- ✅ **Easy Merging**: Simple to review and merge when ready
- ✅ **Risk Reduction**: Main work continues uninterrupted
- ✅ **Focused Execution**: Agent works on single, specific task

## Agent Best Practices

1. **Start with git status** to understand current state
2. **Create focused commits** with clear messages
3. **Test thoroughly** before reporting completion
4. **Follow project conventions** exactly
5. **Document any deviations** or issues encountered
6. **Provide clear summary** of work completed