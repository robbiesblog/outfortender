"""Polite HTTP for every source reader.

Public procurement APIs are run by public bodies on public budgets and several
of them rate-limit hard - TED and Contracts Finder both answer 429 under a
normal day's use. A reader that treats 429 as a failure silently returns half a
day's tenders, which looks like "fewer tenders published today" rather than a
bug. So: back off, honour Retry-After, and raise loudly if it really cannot be
done, so the run reports a failed source instead of a short one.
"""
from __future__ import annotations

import time

import requests

USER_AGENT = "OutForTender/0.1 (+https://outfortender.com; open data aggregator)"

RETRY_STATUSES = (429, 500, 502, 503, 504)
MAX_ATTEMPTS = 6
BASE_DELAY = 5.0
MAX_DELAY = 120.0


def session():
    handle = requests.Session()
    handle.headers["User-Agent"] = USER_AGENT
    handle.headers["Accept-Encoding"] = "gzip"
    return handle


def _sleep_for(response, attempt):
    """Retry-After when the server names one, exponential backoff otherwise."""
    header = response.headers.get("Retry-After") if response is not None else None
    if header:
        try:
            return min(float(header), MAX_DELAY)
        except ValueError:
            pass
    return min(BASE_DELAY * (2 ** attempt), MAX_DELAY)


def request(handle, method, url, *, timeout=60, log=None, **kwargs):
    """One request, retried on the statuses that mean "not now" rather than "no"."""
    last = None
    for attempt in range(MAX_ATTEMPTS):
        try:
            response = handle.request(method, url, timeout=timeout, **kwargs)
        except requests.RequestException as error:
            last = error
            time.sleep(min(BASE_DELAY * (2 ** attempt), MAX_DELAY))
            continue

        if response.status_code in RETRY_STATUSES:
            delay = _sleep_for(response, attempt)
            if log:
                log(f"    {response.status_code} from {url.split('/')[2]}, waiting {delay:.0f}s")
            last = response
            time.sleep(delay)
            continue

        response.raise_for_status()
        return response

    if isinstance(last, requests.Response):
        raise RuntimeError(
            f"{url.split('/')[2]} kept answering {last.status_code} after {MAX_ATTEMPTS} attempts"
        )
    raise RuntimeError(f"{url.split('/')[2]} unreachable after {MAX_ATTEMPTS} attempts: {last}")


def get_json(handle, url, **kwargs):
    return request(handle, "GET", url, **kwargs).json()


def post_json(handle, url, payload, **kwargs):
    return request(handle, "POST", url, json=payload, **kwargs).json()
