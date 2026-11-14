"""
Verilen SITE_URL'den başlayarak aynı domain'deki sayfaları tarar.
sitemap.xml varsa önce oradan sayfa listesi alır.
Çıktı: data/pages.json
Kullanım: python crawler.py
"""

import time
import json
import os
import re
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

from config import SITE_URL, MAX_PAGES, CRAWL_DELAY, MIN_TEXT_LEN, DATA_PATH


HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/124.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "tr-TR,tr;q=0.9",
}

SKIP_EXTENSIONS = {
    ".pdf", ".jpg", ".jpeg", ".png", ".gif", ".svg",
    ".zip", ".rar", ".mp4", ".mp3", ".webp", ".ico",
}

NOISE_TAGS = [
    "nav", "footer", "header", "aside",
    "script", "style", "noscript",
    '[class*="menu"]', '[class*="cookie"]',
    '[class*="banner"]', '[class*="ads"]',
    '[id*="nav"]',  '[id*="footer"]',
]


def _is_same_domain(url: str) -> bool:
    base = urlparse(SITE_URL).netloc
    return urlparse(url).netloc == base


def _is_crawlable(url: str) -> bool:
    path = urlparse(url).path.lower()
    return not any(path.endswith(ext) for ext in SKIP_EXTENSIONS)


def _clean_html(soup: BeautifulSoup) -> str:
    """Gürültülü elementleri kaldırır, ana metni döner."""
    for selector in NOISE_TAGS:
        for tag in soup.select(selector):
            tag.decompose()

    main = (
        soup.find("main")
        or soup.find(id=re.compile(r"content|main|body", re.I))
        or soup.find(class_=re.compile(r"content|main|post|article", re.I))
        or soup.body
    )

    if main is None:
        return ""

    text = main.get_text(separator="\n", strip=True)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def _get_sitemap_urls() -> list[str]:
    """sitemap.xml varsa URL listesini döner."""
    urls = []
    for candidate in ["/sitemap.xml", "/sitemap_index.xml"]:
        try:
            r = requests.get(
                urljoin(SITE_URL, candidate),
                headers=HEADERS, timeout=10
            )
            if r.status_code != 200:
                continue
            soup = BeautifulSoup(r.content, "xml")
            for loc in soup.find_all("loc"):
                href = loc.text.strip()
                if href.endswith(".xml"):
                    try:
                        r2 = requests.get(href, headers=HEADERS, timeout=10)
                        s2 = BeautifulSoup(r2.content, "xml")
                        urls += [l.text.strip() for l in s2.find_all("loc")]
                    except Exception:
                        pass
                else:
                    urls.append(href)
            if urls:
                print(f"[Sitemap] {len(urls)} URL bulundu.")
                return urls
        except Exception:
            pass
    return []


def crawl() -> list[dict]:
    """
    Siteyi tarar, temiz metinleri döner.
    Dönen format: [{"url": ..., "title": ..., "text": ...}]
    """
    visited   = set()
    pages     = []

    sitemap_urls = _get_sitemap_urls()
    queue = [u for u in sitemap_urls if _is_same_domain(u)] if sitemap_urls else [SITE_URL]
    queue_set = set(queue)

    while queue and len(pages) < MAX_PAGES:
        url = queue.pop(0)

        if url in visited or not _is_crawlable(url):
            continue
        visited.add(url)

        try:
            r = requests.get(url, headers=HEADERS, timeout=12)
            if r.status_code != 200:
                continue
            if "text/html" not in r.headers.get("Content-Type", ""):
                continue

            soup  = BeautifulSoup(r.text, "lxml")
            title = soup.title.string.strip() if soup.title else url
            text  = _clean_html(soup)

            if len(text) >= MIN_TEXT_LEN:
                pages.append({"url": url, "title": title, "text": text})
                print(f"[✓] ({len(pages)}) {title[:70]}")

            for a in soup.find_all("a", href=True):
                full = urljoin(url, a["href"]).split("#")[0].split("?")[0]
                if (
                    full not in visited
                    and full not in queue_set
                    and _is_same_domain(full)
                    and _is_crawlable(full)
                ):
                    queue.append(full)
                    queue_set.add(full)

            time.sleep(CRAWL_DELAY)

        except Exception as e:
            print(f"[!] Atlandı: {url} — {e}")

    print(f"\n[Crawler] Toplam {len(pages)} sayfa işlendi.")
    return pages


def save_pages(pages: list[dict]) -> None:
    os.makedirs("data", exist_ok=True)
    with open(DATA_PATH, "w", encoding="utf-8") as f:
        json.dump(pages, f, ensure_ascii=False, indent=2)
    print(f"[Kayıt] {DATA_PATH} dosyasına yazıldı.")


if __name__ == "__main__":
    pages = crawl()
    save_pages(pages)
