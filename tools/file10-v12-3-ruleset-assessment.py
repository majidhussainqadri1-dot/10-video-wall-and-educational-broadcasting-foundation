#!/usr/bin/env python3
"""Read-only File10 ruleset audit. Snapshot is not effective branch protection."""
from __future__ import annotations
import argparse
import fnmatch
import json
import pathlib
import re
import sys

def read(path):
    try:
        obj=json.loads(path.read_text(encoding="utf-8"))
    except (OSError,ValueError) as exc:
        print("FAIL: Cannot read ruleset snapshot: "+type(exc).__name__,file=sys.stderr)
        raise SystemExit(2)
    if obj.get("kind")!="File10V12.3GithubRulesetSnapshot.v1" or not obj.get("snapshot_only"):
        print("FAIL: Unexpected or falsely accepted source snapshot",file=sys.stderr)
        raise SystemExit(2)
    return obj

def selected(branch, conditions):
    full="refs/heads/"+branch
    include=conditions.get("include") or []
    exclude=conditions.get("exclude") or []
    # ~DEFAULT_BRANCH is a GitHub-resolved sentinel, never matches a named
    # development head. Caller must supply separate default-branch proof.
    valid=lambda expr: expr not in ("~DEFAULT_BRANCH","~ALL") and fnmatch.fnmatchcase(full,expr)
    return any(valid(v) for v in include) and not any(valid(v) for v in exclude)

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("snapshot",nargs="?",type=pathlib.Path,
        default=pathlib.Path(__file__).resolve().parent.parent/
                "docs/V12.3-FILE10-GITHUB-RULESET-EVIDENCE-20261114.json")
    parser.add_argument("--branch",help="the named branch whose effective governance is being reviewed")
    args=parser.parse_args()
    data=read(args.snapshot)
    branch=args.branch or data.get("candidate_branch")
    if not isinstance(branch,str) or not re.fullmatch(r"[A-Za-z0-9/_-]{3,180}",branch):
        print("FAIL: unsafe or empty branch reference",file=sys.stderr)
        raise SystemExit(2)
    inc=data.get("ref_conditions")
    checks=data.get("required_status_checks") or []
    actual=data.get("exact_sha_jobs",{}).get("check_contexts") or []
    if not isinstance(inc,dict) or not isinstance(checks,list) or not isinstance(actual,list) or len(actual)<2:
        print("FAIL: incomplete ruleset/check evidence",file=sys.stderr)
        raise SystemExit(2)
    desired={x.get("context") for x in checks if isinstance(x,dict)}
    got=set(actual)
    covered=selected(branch,inc)
    exact=bool(desired) and desired==got and len(desired)==len(checks)
    active=data.get("enforcement")=="active"
    jobs_ok=all(x=="success" for x in data.get("exact_sha_jobs",{}).get("conclusions") or [])
    result={
        "kind":"File10V12.3SourceRulesetAssessment.v1",
        "source_snapshot_only":True,
        "ruleset_id":data.get("ruleset_id"),
        "candidate_branch":branch,
        "is_selected_by_snapshot":covered,
        "required_contexts_match_observed_jobs":exact,
        "ruleset_active_in_snapshot":active,
        "exact_sha_jobs_green_in_snapshot":jobs_ok,
        "observed_contexts":sorted(got),
        "required_contexts":sorted(desired),
        "source_snapshot_preflight_ready":bool(covered and exact and active and jobs_ok),
        "effective_protection_verified_live":False,
        "issue22_closure_authorized":False,
        "action":"GitHub admin must verify current effective ruleset on this exact ref; update covered branch and status contexts; no auto-merge."
    }
    print(json.dumps(result,ensure_ascii=False,indent=2,sort_keys=True))
    # Exit 3 intentionally distinguishes known governance block from parser
    # error (2). A hypothetical synthetic match remains snapshot-only.
    if not result["source_snapshot_preflight_ready"]:
        raise SystemExit(3)

if __name__=="__main__":
    main()
