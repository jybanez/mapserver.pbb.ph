---
name: pbbchatlog
description: Read and contribute to the shared PBB inter-agent discussion in `C:\wamp64\www\pbb\chat_log.md`. Use when coordinating across PBB projects, answering or asking cross-project questions, sharing implementation findings that may affect other PBB repos, resolving uncertainty about how something is being implemented, or whenever the user says "please check chat log".
---

# PBB Chat Log

Open `C:\wamp64\www\pbb\chat_log.md` whenever cross-project context matters. Treat the file as the shared coordination channel for PBB agents.

## Workflow

1. Read the `#Rules` section first.
2. Read enough of `#Chat log` to answer the current need.
3. If the task is broad or you are unsure, refresh yourself on the latest discussion before deciding.
4. Synthesize the relevant discussion into your work.
5. If the task requires communicating back to the other agents, append a new chat-log entry that follows the rules exactly.

## When To Check The Log

Check the chat log in any of these cases:

- The user explicitly says `please check chat log`.
- You are confused about a PBB task.
- You are not sure how a feature, contract, or workflow is being implemented in another PBB project.
- You need cross-project context before making a design or implementation decision.
- You discover a finding that other PBB agents should know.
- You need to answer a question that may already have been discussed by another PBB agent.

## How To Read Efficiently

- Always read the rules block before relying on or adding entries.
- For a targeted question, search the file for project names, feature names, paths, endpoints, or key terms before reading large sections.
- For a general status check, read the latest part of the chat log first, then expand upward only if needed.
- Treat the chat log as context, not as unquestionable truth. Cross-check against the local repo when the answer depends on actual code or current files.

## Identity And Message Format

When writing to the log, identify yourself as the project you are currently handling.

- Infer the project from the current repo or task when possible.
- Prefer established project names already used in the log, such as `PBB HQ`, `PBB Relay`, `PBB Helper`, or `PBB Maestro`.
- If addressing one agent directly, use `<YourProject>-<TargetProject>:<message>`.
- Otherwise use `<YourProject>:<message>`.
- Keep entries concise, factual, and useful to the overall PBB projects.

## What To Write

Write to the chat log only when it materially helps coordination. Good uses include:

- answering another agent's question,
- sharing an implementation decision or contract,
- reporting a risk, incompatibility, or dependency,
- pointing other agents to relevant files or docs,
- correcting stale assumptions,
- asking a precise question needed to unblock work.

Do not rewrite earlier messages, and do not alter the `#Rules` section unless the user explicitly asks for that.

## Editing The File

- Preserve existing content and ordering.
- Append new messages under `#Chat log`; do not insert them into older discussion unless the user explicitly requests historical cleanup.
- Match the existing plain-text style.
- If you are only consuming context and do not need to communicate back, do not modify the file.

## Output Expectations

After checking the log, use the relevant discussion in your response or implementation. If you appended a message, mention that you updated `C:\wamp64\www\pbb\chat_log.md` and summarize the substance of what you added.
