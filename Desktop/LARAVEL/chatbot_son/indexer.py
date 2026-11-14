"""
pages.json → chunk'lara böl → Jina API ile embedding al → ChromaDB'ye kaydet.
Lokal model yok, RAM sorunu yok.
Kullanım: python indexer.py
"""

import json
import os
import time

import requests
import chromadb

from config import (
    DATA_PATH, CHROMA_DIR,
    CHUNK_SIZE, CHUNK_OVERLAP,
    JINA_API_KEY, JINA_MODEL,
)

JINA_URL    = "https://api.jina.ai/v1/embeddings"
EMBED_BATCH = 50   # Kaç chunk'ı tek API isteğiyle gönder


# ── Embedding ────────────────────────────────────────────────────────────────

def get_embeddings(texts: list[str]) -> list[list[float]]:
    """Jina API'den embedding listesi alır."""
    headers = {
        "Authorization": f"Bearer {JINA_API_KEY}",
        "Content-Type": "application/json",
    }
    payload = {
        "model": JINA_MODEL,
        "task": "retrieval.passage",
        "input": texts,
    }
    r = requests.post(JINA_URL, headers=headers, json=payload, timeout=30)
    r.raise_for_status()
    return [item["embedding"] for item in r.json()["data"]]


# ── Chunk ────────────────────────────────────────────────────────────────────

def split_chunks(text: str, url: str, title: str) -> list[dict]:
    """Metni örtüşen parçalara böler. Cümle ortasında kesmez."""
    chunks = []
    start  = 0
    while start < len(text):
        end   = start + CHUNK_SIZE
        chunk = text[start:end]
        last_break = max(
            chunk.rfind("."), chunk.rfind("!"),
            chunk.rfind("?"), chunk.rfind("\n"),
        )
        if last_break > CHUNK_SIZE // 2:
            chunk = chunk[: last_break + 1]
        if len(chunk.strip()) > 60:
            chunks.append({"text": chunk.strip(), "url": url, "title": title})
        start += len(chunk) - CHUNK_OVERLAP
    return chunks


# ── Ana Fonksiyon ─────────────────────────────────────────────────────────────

def build_index() -> None:
    if not JINA_API_KEY:
        raise ValueError("JINA_API_KEY .env dosyasında tanımlı değil!")

    # pages.json oku
    if not os.path.exists(DATA_PATH):
        print("[!] data/pages.json bulunamadı. Önce crawler.py çalıştır.")
        return

    with open(DATA_PATH, encoding="utf-8") as f:
        pages = json.load(f)

    if not pages:
        print("[!] pages.json boş. Önce crawler.py çalıştır.")
        return

    # Chunk üret
    all_chunks = []
    for page in pages:
        all_chunks.extend(split_chunks(page["text"], page["url"], page["title"]))

    print(f"[Index] {len(pages)} sayfa → {len(all_chunks)} chunk oluşturuldu.")
    print("[Index] Jina API ile embedding alınıyor...")

    # ChromaDB — eski koleksiyonu sil, temizden başla
    os.makedirs(CHROMA_DIR, exist_ok=True)
    client = chromadb.PersistentClient(path=CHROMA_DIR)
    try:
        client.delete_collection("site_content")
        print("[Index] Eski koleksiyon silindi.")
    except Exception:
        pass

    collection = client.create_collection(
        name="site_content",
        metadata={"hnsw:space": "cosine"},
    )

    # Batch batch işle
    total = len(all_chunks)
    for batch_start in range(0, total, EMBED_BATCH):
        batch = all_chunks[batch_start: batch_start + EMBED_BATCH]
        texts = [c["text"] for c in batch]

        try:
            embeddings = get_embeddings(texts)
        except Exception as e:
            print(f"[!] Embedding hatası (batch {batch_start}): {e}")
            continue

        collection.add(
            ids        = [f"chunk_{batch_start + j}" for j in range(len(batch))],
            documents  = texts,
            embeddings = embeddings,
            metadatas  = [{"url": c["url"], "title": c["title"]} for c in batch],
        )

        done = min(batch_start + EMBED_BATCH, total)
        print(f"[✓] {done}/{total} chunk kaydedildi.")
        time.sleep(0.3)   # API rate limit

    print(f"\n[Index] Tamamlandı. {total} chunk ChromaDB'ye kaydedildi.")


if __name__ == "__main__":
    build_index()
