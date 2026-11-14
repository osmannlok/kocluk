import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(dotenv_path=Path(__file__).parent / ".env", override=True)

# API Anahtarları
GROQ_API_KEY = os.getenv("GROQ_API_KEY")
JINA_API_KEY = os.getenv("JINA_API_KEY")

# Model Ayarları
GROQ_MODEL = os.getenv("GROQ_MODEL", "llama3-8b-8192")
JINA_MODEL = "jina-embeddings-v3"

# Site Bilgileri
SITE_URL  = os.getenv("SITE_URL", "https://example.com")
SITE_NAME = os.getenv("SITE_NAME", "sitemiz")

# Crawler Ayarları
MAX_PAGES    = 1
CRAWL_DELAY  = 0.8
MIN_TEXT_LEN = 120

# RAG Ayarları
TOP_K         = 4
CHUNK_SIZE    = 500
CHUNK_OVERLAP = 50

# Veri Yolları
DATA_PATH  = "data/pages.json"
CHROMA_DIR = "data/chroma"
