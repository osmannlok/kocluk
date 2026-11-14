"""
Kullanıcı sorusunu Jina API ile vektöre çevirir,
ChromaDB'den en alakalı chunk'ları döner.
"""

import os

import requests
import chromadb

from config import CHROMA_DIR, TOP_K, JINA_API_KEY, JINA_MODEL

JINA_URL = "https://api.jina.ai/v1/embeddings"

_collection = None


# ── ChromaDB bağlantısı ───────────────────────────────────────────────────────

def _get_collection():
    global _collection
    if _collection is None:
        client      = chromadb.PersistentClient(path=CHROMA_DIR)
        _collection = client.get_collection("site_content")
    return _collection


# ── Embedding ─────────────────────────────────────────────────────────────────

def _embed_query(text: str) -> list[float]:
    """Soruyu Jina API ile vektöre çevirir. task='retrieval.query' önemli."""
    headers = {
        "Authorization": f"Bearer {JINA_API_KEY}",
        "Content-Type": "application/json",
    }
    payload = {
        "model": JINA_MODEL,
        "task": "retrieval.query",   # passage değil query — fark önemli
        "input": [text],
    }
    r = requests.post(JINA_URL, headers=headers, json=payload, timeout=15)
    r.raise_for_status()
    return r.json()["data"][0]["embedding"]


# ── Ana Fonksiyonlar ───────────────────────────────────────────────────────────

def retrieve(query: str) -> list[dict]:
    """
    Soruya en yakın TOP_K chunk'ı döner.
    [{"text": ..., "url": ..., "title": ..., "score": ...}]
    """
    collection      = _get_collection()
    query_embedding = _embed_query(query)

    results = collection.query(
        query_embeddings=[query_embedding],
        n_results=TOP_K,
        include=["documents", "metadatas", "distances"],
    )

    chunks = []
    for doc, meta, dist in zip(
        results["documents"][0],
        results["metadatas"][0],
        results["distances"][0],
    ):
        score = round(1 - dist, 3)
        print(f"[Retrieve] skor={score:.2f} | {meta.get('title', '')[:50]}")
        chunks.append({
            "text" : doc,
            "url"  : meta.get("url", ""),
            "title": meta.get("title", ""),
            "score": score,
        })

    return chunks


def build_context(chunks: list[dict]) -> str:
    """Chunk listesini LLM'e gönderilecek bağlam metnine çevirir."""
    filtered = [c for c in chunks if c["score"] > 0.20]
    if not filtered:
        return ""
    parts = []
    for i, c in enumerate(filtered, 1):
        parts.append(f"[Kaynak {i} — {c['title']}]\n{c['text']}")
    return "\n\n---\n\n".join(parts)
