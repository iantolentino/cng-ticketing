# Execution Queue

1. FND-01 - schema, setup, authentication, and RBAC - complete (`872ec07`)
2. UI-01 - branded dashboard and local dual-logo header - complete (`12f5fb2`)
3. TKT-01 - ticket creation, details, workflow, comments, activity, and soft deletion - complete (`899743e`)
4. ADM-01 - roles, access management, user activation, and password resets - complete (`ef4958a`)
5. NTF-01 - SMTP notification hooks and protected configuration - complete (`96370ef`)
6. UI-02 - permission-aware action links - complete (`5cb9ce1`)
7. VRF-01 - local verification and deployment handoff - complete
8. DEP-01a - clean cPanel upload archive - complete (local file, excluded from Git)
9. UI-03 - sidebar layout, density, ticket cards, and row arrows - complete (pending user visual approval)
10. F001 - dynamic sidebar active state - complete
11. F002 - ticket detail action-button sizing and spacing - complete (pending user visual approval)
12. QA-01 - role-based local test accounts and authentication checks - complete
13. MIG-002 - leave and calendar foundation migration - complete (applied locally and idempotence-verified)
14. F003 - shared styling for login page - complete
15. EXP-01 - permission-gated CSV and Excel ticket exports - complete
16. UI-04 - dual-logo login header - complete
17. UI-05 - product naming and login copy refinement - complete
18. UI-06 - login eyebrow removal - complete
19. F004 - ticket action feedback and delete confirmation - complete
20. F005 - case-normalized username lookup - complete
21. DEP-01b - cPanel upload, configuration, schema import, additive migrations, and SMTP go-live verification - pending user upload

## Next Tasks

22. TKT-04 - multi-assignees, involved departments, Team Leader ticket scoping, CNG Admin role, and filter layout improvements - complete locally; pending deployment
23. DEP-02 - apply migrations 004, 005, 006, 007, and 008, upload changed PHP/CSS files, and run live role/filter checks - pending
24. QA-02 - verify multi-assignee create/edit/detail/register/export behavior with real role accounts - pending
25. QA-03 - verify Team Leader assigned-ticket-only visibility, including direct ticket URLs - pending
26. QA-04 - verify CNG Admin view/filter-only access with no create/edit/export/admin actions - pending
27. FND-02 - bring base schema into parity with leave/calendar migration 002 for fresh installs - complete locally; pending deployment
28. FND-03 - align attachment metadata naming with requested medical certificate model while preserving compatibility - complete locally; pending deployment
29. LVE-01 - add protected Leave Request placeholder route for Team Members only - complete locally; pending deployment
30. CAL-01 - document Team Calendar data sources and keep sidebar disabled/coming soon - complete locally; pending deployment
31. F009 - refresh cached user state after login/password change before choosing default landing page - complete locally; pending deployment
32. UI-07 - dashboard ticket counts with range selector - complete locally; pending deployment
33. ATT-01 - confidential attachment upload/list/download UI - complete locally; pending deployment
34. LVE-02 - Team Member leave request submission plus Team Leader and Department Head approval queues - complete locally; pending deployment
35. CAL-02 - Team Calendar UI for company holidays and approved leave - complete locally; pending deployment
36. CAL-03 - clickable month-grid Team Calendar, custom events, company holiday entry, and seeded PH/AU/CA public holidays - complete locally; pending deployment
37. CAL-04 - clickable holiday/event/leave details on Team Calendar, including leave reason and attachment links - complete locally; pending deployment
38. ATT-02 - required leave supporting screenshot/photo/PDF upload and private download route - complete locally; pending deployment
39. TKT-05 - ticket aging and SLA warning indicators - complete locally; pending deployment
40. TKT-06 - My Work Queue sidebar page for assigned tickets - complete locally; pending deployment
41. TKT-07 - ticket priority field and urgent dashboard count - complete locally; pending deployment
42. UI-08 - clickable dashboard drill-down cards - complete locally; pending deployment
43. UI-09 - dashboard recent activity panel - complete locally; pending deployment
44. DEP-03 - department workload/logging view for Team Leader department tracking - complete locally; pending deployment
45. LVE-04 - dashboard counts for leave approval queues and approved leave this week - complete locally; pending deployment
46. UI-11 - dedicated Dashboard sidebar page for ticket dashboard and recent activity - complete locally; pending deployment
47. NTF-02 - in-app notification center for assignments, comments, and approvals - complete locally; pending deployment
48. UI-10 - ticket trend bar graph with daily/weekly/monthly and 3/6/9/12-month ranges - complete locally; pending deployment
49. ATTEND-01 - Team Attendance module feeding Team Calendar - complete locally; pending deployment
50. NTF-03 - follow-up button for pending/overdue tickets that emails assignees - complete locally; pending deployment
