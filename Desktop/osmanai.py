import ollama
import os
import json

# ─── AYARLAR ───────────────────────────────────────────────────────────────────
AI_MODEL       = "qwen3:1.7b"
HISTORY_FILE   = "sohbet_gecmisi.json"   # kalıcı kayıt dosyası
MAX_TUR        = 15    # modele gönderilecek maks. tur sayısı
OZET_HER_N_TUR = 10    # her N turda bir otomatik özet al

SYSTEM_MESAJI = {
    "role": "system",
    "content": "Sen yardımsever bir AI asistansın. Kısa, net ve anlaşılır cevaplar ver."
}
# ───────────────────────────────────────────────────────────────────────────────


def ekrani_temizle():
    os.system("cls" if os.name == "nt" else "clear")


# ── Kalıcı geçmiş: yükle / kaydet ────────────────────────────────────────────

def gecmisi_yukle() -> list:
    """JSON dosyasından geçmişi yükle. Yoksa sadece system mesajıyla başla."""
    if os.path.exists(HISTORY_FILE):
        try:
            with open(HISTORY_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
            print(f"📂 Önceki sohbet yüklendi ({len(data) - 1} mesaj).\n")
            return data
        except Exception as e:
            print(f"⚠️  Geçmiş yüklenemedi, sıfırdan başlanıyor. ({e})\n")
    return [SYSTEM_MESAJI.copy()]


def gecmisi_kaydet(history: list):
    """Geçmişi JSON dosyasına kaydet."""
    try:
        with open(HISTORY_FILE, "w", encoding="utf-8") as f:
            json.dump(history, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"⚠️  Geçmiş kaydedilemedi: {e}")


# ── Otomatik özetleme ─────────────────────────────────────────────────────────

def ozet_al(history: list) -> str:
    """Mevcut sohbeti modele özetle."""
    ozet_prompt = (
        "Aşağıdaki sohbeti 3-5 cümleyle özetle. "
        "Sadece özeti yaz, başka bir şey ekleme:\n\n"
    )
    for msg in history[1:]:  # system hariç
        rol = "Kullanıcı" if msg["role"] == "user" else "Bot"
        ozet_prompt += f"{rol}: {msg['content']}\n"

    print("\n🔄 Sohbet özetleniyor...", end="", flush=True)
    try:
        response = ollama.chat(
            model=AI_MODEL,
            messages=[
                SYSTEM_MESAJI,
                {"role": "user", "content": ozet_prompt}
            ]
        )
        ozet = response.message.content.strip()
        print(" ✅\n")
        return ozet
    except Exception as e:
        print(f" ❌ ({e})\n")
        return ""


def gecmisi_ozetle_ve_sifirla(history: list) -> list:
    """
    Geçmişi özetleyip sıkıştır:
    [system] + [özet system mesajı] → yeni temiz history
    """
    ozet = ozet_al(history)
    if not ozet:
        return history  # özetleme başarısızsa dokunma

    yeni_history = [
        SYSTEM_MESAJI.copy(),
        {
            "role": "system",
            "content": f"[ÖNCEKİ SOHBET ÖZETİ]\n{ozet}"
        }
    ]
    print(f"📝 Özet:\n{ozet}\n")
    return yeni_history


# ── Geçmiş ekrana yazdır ──────────────────────────────────────────────────────

def gecmisi_yazdir(history: list):
    print("=" * 60)
    print("  KONUŞMA GEÇMİŞİ")
    print("=" * 60)

    gosterim = [m for m in history if m["role"] != "system"]
    if not gosterim:
        print("  (Henüz konuşma yok)\n")
    else:
        for i, msg in enumerate(gosterim, 1):
            if msg["role"] == "user":
                print(f"  {i}. 👤 Sen   : {msg['content'].strip()}")
            else:
                print(f"  {i}. 🤖 Yiğit : {msg['content'].strip()}")
    print(f"\n  Toplam tur: {len(gosterim) // 2}")
    print("=" * 60 + "\n")


# ── Ana döngü ─────────────────────────────────────────────────────────────────

def main():
    ekrani_temizle()
    print("🤖 Yerel AI Sohbet Botu  |  çıkış için 'exit'\n")

    history = gecmisi_yukle()

    while True:
        ekrani_temizle()
        gecmisi_yazdir(history)

        prompt = input("Sen: ").strip()

        if prompt.lower() in ("exit", "quit", "çıkış"):
            gecmisi_kaydet(history)
            print("\n💾 Sohbet kaydedildi. İyi günler!")
            break

        if not prompt:
            continue

        # Kullanıcı mesajını ekle
        history.append({"role": "user", "content": prompt})

        # ── Modele gönderilecek bağlamı sınırla ──
        # Başındaki system(ler) + son MAX_TUR*2 mesaj
        sistem_mesajlari = [m for m in history if m["role"] == "system"]
        diger_mesajlar   = [m for m in history if m["role"] != "system"]
        messages_for_model = sistem_mesajlari + diger_mesajlar[-(MAX_TUR * 2):]

        # ── Yanıt al (streaming) ──
        print("\n🤖 Yiğit: ", end="", flush=True)
        bot_cevap = ""

        try:
            response = ollama.chat(
                stream=True,
                model=AI_MODEL,
                messages=messages_for_model,
            )
            for chunk in response:
                parca = chunk.message.content
                bot_cevap += parca
                print(parca, end="", flush=True)
            print("\n")

            # Bot cevabını ekle
            history.append({"role": "assistant", "content": bot_cevap})

            # ── Otomatik özetleme kontrolü ──
            tur_sayisi = sum(1 for m in history if m["role"] == "user")
            if tur_sayisi > 0 and tur_sayisi % OZET_HER_N_TUR == 0:
                history = gecmisi_ozetle_ve_sifirla(history)

            # ── Kalıcı kaydet ──
            gecmisi_kaydet(history)

        except Exception as e:
            print(f"\n❌ Hata: {e}")
            print("Ollama çalışmıyor olabilir veya model adı yanlış.\n")
            history.pop()  # başarısız kullanıcı mesajını geri al

        input("Devam etmek için Enter'a bas...")


if __name__ == "__main__":
    main()