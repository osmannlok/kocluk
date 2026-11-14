"""
Groq LLM entegrasyonu.
Retriever'dan gelen bağlamı kullanarak yalnızca site içeriğine dayalı cevap üretir.
"""

from groq import Groq
from retriever import retrieve, build_context
from config import GROQ_API_KEY, GROQ_MODEL, SITE_NAME


SYSTEM_PROMPT = f"""Sen {SITE_NAME} web sitesinin deneyimli bir müşteri hizmetleri danışmanısın.

## KİŞİLİĞİN
- Sıcak, profesyonel ve çözüm odaklısın.
- Kullanıcıyı bir müşteri gibi karşılar, ihtiyacını anlar ve doğrudan yanıtlarsın.
- "Sayfayı ziyaret edin" demek yerine bilgiyi kendin aktarırsın.
- Gereksiz selamlama ve dolgu cümle kullanmazsın.
- Kısa ve öz konuşursun; gerektiğinde madde madde açıklarsın.

## CEVAP KURALLARI
1. Sana verilen "Site İçeriği" bölümündeki bilgileri DOĞRUDAN kullan.
2. İçerikte kesin rakam veya tarih yoksa "Güncel bilgi için bizimle iletişime geçebilirsiniz" ekle.
3. İçerik tamamen boşsa: "Bu konuda size yardımcı olabilmek için daha fazla bilgiye ihtiyacım var, lütfen bizi arayın veya yazın."
4. Site dışı sorularda: "Bu konu uzmanlık alanımın dışında, ancak {SITE_NAME} hizmetleri hakkında her konuda yardımcı olmaktan memnuniyet duyarım."
5. Cevabın sonuna —mantıklıysa— somut bir yönlendirme ekle. Örnek: "Kayıt için bize ulaşabilirsiniz."

## FORMAT
- Kısa sorular → 2-4 cümle, düz paragraf.
- Karmaşık sorular → maddeler halinde, sade dil.
- Türkçe yaz.
"""


def chat(history: list[dict], user_message: str) -> dict:
    """
    Dönen değer: {"reply": str, "sources": [{"title", "url", "score"}]}
    """
    # 1. İlgili içeriği bul
    chunks  = retrieve(user_message)
    context = build_context(chunks)

    # 2. Context durumuna göre LLM'e mesaj hazırla
    if context:
        context_msg = f"## Site İçeriği\n\n{context}"
    else:
        context_msg = (
            "## Site İçeriği\n\n"
            "Bu soruyla ilgili siteden herhangi bir içerik bulunamadı. "
            "Kullanıcıya dürüstçe söyle ve iletişim yolunu öner."
        )

    # 3. Groq API çağrısı
    client = Groq(api_key=GROQ_API_KEY)

    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "system", "content": context_msg},
        *history[-10:],                               # Son 5 tur geçmiş
        {"role": "user", "content": user_message},
    ]

    try:
        resp  = client.chat.completions.create(
            model       = GROQ_MODEL,
            messages    = messages,
            max_tokens  = 600,
            temperature = 0.3,
        )
        reply = resp.choices[0].message.content.strip()
    except Exception as e:
        print(f"[Groq Hata] {e}")
        reply = "Teknik bir sorun oluştu, lütfen kısa süre sonra tekrar deneyin."

    # 4. Kaynakları döndür (UI'da göstermek için)
    sources = [
        {"title": c["title"], "url": c["url"], "score": c["score"]}
        for c in chunks
        if c["score"] > 0.20
    ]

    return {"reply": reply, "sources": sources}
