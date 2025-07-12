# PR Review Agent System

## Agent Coordination for Conduit Ecosystem

This document defines the automated PR review agent system for the jordanpartridge/conduit ecosystem.

## Agent Pipeline

### 1. PR Triage Agent ✅
**Mission**: Monitor and categorize incoming PRs across jordanpartridge repos
**Triggers**: PR opened, updated, reopened
**Actions**:
- Analyze changed files and PR content
- Apply appropriate labels (component-system, architecture, documentation, etc.)
- Set priority level (low, medium, high)
- Generate classification report
- Trigger subsequent specialized agents

**Output**: Triage report with category, priority, and next steps

### 2. Quality Gate Agent ⏳
**Mission**: Automated quality checks for all PRs
**Triggers**: After triage completion
**Checks**:
- Tests pass
- Code style (PSR-12)
- Type hints present
- Documentation updated
- Security scan clean

**Output**: Quality compliance report

### 3. Architecture Agent ⏳
**Mission**: Review PRs for Conduit ecosystem architectural consistency
**Triggers**: PRs labeled with 'architecture' or 'component-system'
**Focus**:
- Component interface compliance
- Microkernel principles maintained
- No core bloat introduced
- Proper abstraction layers

**Output**: Architecture compliance assessment

### 4. Component Integration Agent ⏳
**Mission**: Validate component PR compatibility across ecosystem
**Triggers**: PRs labeled with 'component-system' or 'conduit-component'
**Actions**:
- Test component installation process
- Verify base class conformance
- Check for breaking changes
- Test with live Conduit installation

**Output**: Integration compatibility report

## Agent Communication Protocol

### Comment Structure
```markdown
## 🤖 [Agent Name] Report
**Mission**: [Agent's specific mission]
**Analysis Date**: [ISO timestamp]

### [Agent-specific sections]

### 🎯 Recommendations
- [Specific actionable items]

### 🤖 Agent Pipeline Status
- ✅ **Triage Agent** - Complete
- ⏳ **Quality Gate Agent** - [Status]
- ⏳ **Architecture Agent** - [Status/Skipped]
- ⏳ **Component Integration Agent** - [Status/Skipped]
```

### Label Standards
- `priority-low|medium|high`: Overall priority
- `component-system`: Component-related changes
- `architecture`: Architectural changes
- `breaking-change`: Breaking changes
- `conduit-component`: Changes to Conduit components
- `conduit-integration`: Conduit integration changes
- `documentation`: Documentation updates
- `tests`: Test changes
- `bug-fix`: Bug fixes
- `enhancement`: New features

### Agent Coordination
1. **Sequential Execution**: Agents run in order (triage → quality → architecture → integration)
2. **Conditional Triggering**: Specialized agents only run for relevant PRs
3. **Status Tracking**: Each agent updates the pipeline status
4. **Final Synthesis**: Last agent provides overall recommendation

## Repository Coverage

### Conduit Core (`jordanpartridge/conduit`)
- Focus: Component system integrity
- Special attention: ComponentManager changes
- High priority: Core architecture modifications

### GitHub Zero (`jordanpartridge/github-zero`)
- Focus: Component compliance
- Special attention: ConduitExtension changes
- Integration testing with Conduit

### GitHub Client (`jordanpartridge/github-client`)
- Focus: API compatibility
- Integration impact on dependent packages

### Future Components
- Automatic detection via `conduit-component` topic
- Standard component interface validation
- Cross-component compatibility testing

## Agent Development Guidelines

### Creating New Agents
1. Define clear mission and scope
2. Specify trigger conditions
3. Implement GitHub Actions workflow
4. Follow comment structure standards
5. Update agent pipeline status
6. Test with sample PRs

### Agent Responsibilities
- **Single Focus**: Each agent has one specific responsibility
- **Clear Output**: Structured, actionable reports
- **Pipeline Awareness**: Update overall status
- **Non-Blocking**: Don't prevent merges, provide information

### Quality Standards
- Agents should be fast (< 2 minutes)
- Clear, actionable feedback
- No false positives
- Graceful failure handling
- Comprehensive logging
