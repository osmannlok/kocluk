from django.shortcuts import render, redirect
from django.core.mail import send_mail
from django.contrib import messages
from .forms import RegisterForm, ContactForm
import requests
from django.views.decorators.csrf import csrf_exempt

def index(request):
    context = {
        'page_title': 'KUM Academy - Online İngilizce Kursları',
        'meta_description': 'KUM Academy ile İngilizce öğrenin! IT İngilizcesi, Genel İngilizce, IELTS, birebir ve grup konuşma kurslarıyla akıcı İngilizce konuşun.',
        'og_title': 'KUM Academy - Online İngilizce Kursları',
        'og_description': 'KUM Academy’nin IT İngilizcesi, Genel İngilizce, IELTS ve konuşma kurslarıyla İngilizce becerilerinizi geliştirin.',
        'about_content': 'KUM Academy olarak İngilizce eğitimine odaklanıyor, öğrencilerimizin akıcı ve özgüvenli bir şekilde İngilizce konuşmasını sağlıyoruz. IT İngilizcesi, Genel İngilizce, IELTS ve konuşma kurslarımızla her seviyede destek sunuyoruz. Eğitim sürecinde ve sonrasında öğrencilerimizin yanındayız.',
        'courses': [
            
            {'name': 'Genel İngilizce', 'url': 'kurs-genel-ingilizce', 'description': 'Konuşma, yazma, okuma ve dinleme becerilerinizi geliştirin.'},
            
            {'name': 'Çocuklar Birebir Speaking', 'url': 'kurs-birebir-speaking-cocuklar', 'description': 'Kişiselleştirilmiş derslerle akıcı İngilizce konuşun.'},
            {'name': 'Çocuklar Grup Speaking', 'url': 'kurs-grup-speaking-cocuklar', 'description': 'Eğlenceli grup dersleriyle İngilizce pratiği yapın.'},
            {'name': 'Yetişkinler Grup Speaking', 'url': 'kurs-grup-speaking-yetiskinler', 'description': 'İnteraktif grup dersleriyle İngilizce pratiği yapın.'},
            {'name': 'Yetişkinler Birebir Speaking', 'url': 'kurs-birebir-speaking-yetiskinler', 'description': 'Kişiselleştirilmiş derslerle akıcı İngilizce konuşun.'},
            {'name': 'IT İngilizcesi', 'url': 'kurs-it-ingilizcesi', 'description': 'Bilgi teknolojileri alanında İngilizce terimleri öğrenin.'},
            {'name': 'IELTS ve SAT', 'url': 'kurs-ielts-sat', 'description': 'Sınavlara hazırlanın ve hedeflediğiniz puanı alın.'},
            {'name': 'TOEFL', 'url': 'kurs-toefl', 'description': 'TOEFL sınavına hazırlanın ve hedeflediğiniz puanı alın.'},
            {'name': 'Amazon E-ticaret', 'url': 'kurs-amazon-eticaret', 'description': 'Ürün listeleme ve satış stratejileriyle online satıcı olun.'},
            {'name': 'IT Support', 'url': 'kurs-it-support', 'description': 'Ağ yönetimi ve teknik destek becerileri kazanın.'},
            {'name': 'Dijital Pazarlama ve SEO', 'url': 'kurs-dijital-pazarlama', 'description': 'Online görünürlüğünüzü artırın, strateji ve analiz becerileri kazanın.'},
            {'name': 'Genç Yetenekler - Python Programlama', 'url': 'kurs-genc-yetenekler-python', 'description': 'Çocuklarınız eğlenceli projelerle kodlama öğrensin.'},
            {'name': 'Genç Yetenekler - Projelerle Yapay Zeka\'ya Giriş', 'url': 'kurs-genc-yetenekler-yapay-zeka', 'description': 'Çocuklarınızın yapay zeka ile tanışmasını sağlayın.'},
            {'name': 'Genç Yetenekler - Dijital Tasarım ve Oyun Dünyası', 'url': 'kurs-genc-yetenekler-dijital-tasarim', 'description': 'Çocuklarınızın yaratıcılığını geliştirin ve dijital tasarım becerileri kazandırın.'}
        ],
    }
    return render(request, 'main/index.html', context)


def custom_404(request, exception):
    context = {
        'page_title': 'Sayfa Bulunamadı - KUM Academy',
        'meta_description': 'Aradığınız sayfa KUM Academy’de bulunamadı. Online eğitim kurslarımız hakkında bilgi almak için ana sayfamızı ziyaret edin.',
    }
    return render(request, 'main/404.html', context, status=404)


def hakkimizda(request):
    context = {
        'page_title': 'Hakkımızda - KUM Academy',
        'meta_description': 'KUM Academy, Londra merkezli online eğitim platformudur. Amazon e-ticaret, IT Support, İngilizce ve yapay zeka kurslarıyla öğrencilerin kariyerlerini destekliyoruz.',
        'og_title': 'Hakkımızda - KUM Academy',
        'og_description': 'KUM Academy ile samimi ve güvenilir bir eğitim ortamında kariyerinizi geliştirin. Londra merkezli online kurslarımız hakkında bilgi edinin.',
        'about_content': 'KUM Academy olarak, önceliğimiz öğrenci memnuniyetidir. Eğitimlerimiz sadece ciddi ve yoğun değil, aynı zamanda eğlencelidir. Öğrencilerimizle kurduğumuz güçlü bağlar sayesinde, eğitim süreci boyunca ve sonrasında da onlara destek olmaya devam ederiz. Biz sadece bir eğitim kurumu değil, aynı zamanda bir yol arkadaşıyız.',
    }
    return render(request, 'main/hakkimizda.html', context)


def sss(request):
    context = {
        'page_title': 'Sıkça Sorulan Sorular - KUM Academy',
        'meta_description': 'KUM Academy’nin online kursları, kayıt süreci ve destek hizmetleri hakkında sıkça sorulan sorular ve cevapları.',
        'og_title': 'SSS - KUM Academy',
        'og_description': 'KUM Academy’nin Amazon e-ticaret, IT Support, İngilizce ve diğer kursları hakkında merak ettiklerinizi öğrenin.',
    }
    return render(request, 'main/sss.html', context)


def iletisim(request):
    if request.method == 'POST':
        form = ContactForm(request.POST)
        if form.is_valid():
            data = {
                'adSoyad': form.cleaned_data['adSoyad'],
                'email': form.cleaned_data['email'],
                'telefon': form.cleaned_data['telefon'],
                'konu': form.cleaned_data['konu'],
                'mesaj': form.cleaned_data['mesaj'],
                'api_key': '*x4EPq2h%zWn',  # PHP ile aynı olmalı!
            }

            try:
                response = requests.post('https://raineducation.co.uk/mail.php', data=data, timeout=5)
                if response.text.strip() == "success":
                    messages.success(request, 'Mesajınız başarıyla gönderildi.')
                elif response.text.strip() == "unauthorized":
                    messages.error(request, 'Yetkisiz istek. Lütfen yöneticinize başvurun.')
                else:
                    messages.error(request, 'Mail gönderilemedi. Lütfen tekrar deneyin.')
            except requests.exceptions.RequestException:
                messages.error(request, 'Sunucuya ulaşılamadı.')
            return redirect('iletisim')
    else:
        form = ContactForm()
    
    context = {
        'page_title': 'İletişim - KUM Academy',
        'meta_description': 'KUM Academy ile iletişime geçin! Online eğitimlerimiz hakkında bilgi almak için bizimle WhatsApp, e-posta veya telefon üzerinden iletişime geçebilirsiniz.',
        'og_title': 'İletişim - KUM Academy',
        'og_description': 'KUM Academy’nin online kursları hakkında sorularınız mı var? Bize ulaşın: +44 753 528 30 28 veya info@kumacademy.com.',
        'form': form,
    }
    return render(request, 'main/iletisim.html', context)


def blog(request):
    context = {
        'page_title': 'Blog - KUM Academy',
        'meta_description': 'KUM Academy blogu ile Amazon e-ticaret, IT Support, Dijital Pazarlama, İngilizce ve yapay zeka kursları hakkında en güncel bilgileri edinin.',
        'og_title': 'Blog - KUM Academy',
        'og_description': 'Online eğitim, kariyer ipuçları ve KUM Academy kursları hakkında en yeni içerikleri keşfedin.',
    }
    return render(request, 'main/blog.html', context)


def kurs_amazon_eticaret(request):
    context = {
        'page_title': 'Amazon E-ticaret Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile Amazon e-ticaret eğitimi! Ürün listeleme, satış stratejileri, reklam yönetimi ve lojistik süreçlerini öğrenerek online satıcı olun.',
        'og_title': 'Amazon E-ticaret Kursu - KUM Academy',
        'og_description': 'Amazon’da başarılı bir satıcı olmak için gerekli becerileri KUM Academy ile öğrenin. Ürün listeleme, reklam ve lojistikte uzmanlaşın.',
    }
    return render(request, 'main/kurs-amazon-eticaret.html', context)


def kurs_it_support(request):
    context = {
        'page_title': 'IT Support Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile IT Support eğitimi! Bilgisayar sorunlarını çözme, ağ yönetimi ve güvenlik konularında uzmanlaşın.',
        'og_title': 'IT Support Kursu - KUM Academy',
        'og_description': 'IT Support becerilerinizi geliştirin! KUM Academy ile teknik destek, ağ yönetimi ve güvenlikte uzmanlaşın.',
    }
    return render(request, 'main/kurs-it-support.html', context)


def kurs_dijital_pazarlama(request):
    context = {
        'page_title': 'Dijital Pazarlama ve SEO Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile Dijital Pazarlama ve SEO eğitimi! Online görünürlüğünüzü artırın, strateji ve optimizasyon becerileri kazanın.',
        'og_title': 'Dijital Pazarlama ve SEO Kursu - KUM Academy',
        'og_description': 'Dijital Pazarlama ve SEO’da uzmanlaşın! KUM Academy ile online görünürlüğünüzü artıracak stratejiler öğrenin.',
    }
    return render(request, 'main/kurs-dijital-pazarlama.html', context)


def kurs_it_ingilizcesi(request):
    context = {
        'page_title': 'IT İngilizcesi Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile IT İngilizcesi eğitimi! Bilgi Teknolojileri alanında kullanılan İngilizce terimleri öğrenin ve iletişim becerilerinizi geliştirin.',
        'og_title': 'IT İngilizcesi Kursu - KUM Academy',
        'og_description': 'Bilgi Teknolojileri için İngilizce öğrenin! KUM Academy ile IT terimlerinde uzmanlaşın ve iletişim becerilerinizi güçlendirin.',
    }
    return render(request, 'main/kurs-it-ingilizcesi.html', context)


def kurs_genel_ingilizce(request):
    context = {
        'page_title': 'Genel İngilizce Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile Genel İngilizce kursu! Konuşma, yazma, okuma ve dinleme becerilerinizi geliştirin. Hemen başlayın!',
        'og_title': 'Genel İngilizce Kursu - KUM Academy',
        'og_description': 'İngilizce becerilerinizi KUM Academy ile güçlendirin! Konuşma, yazma, okuma ve dinleme alanlarında gelişin.',
    }
    return render(request, 'main/kurs-genel-ingilizce.html', context)

def kurs_genel_ingilizce_a1(request):
    context = {
        'page_title': 'Genel İngilizce A1 Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile A1 seviyesinde Genel İngilizce kursu! Temel dil bilgisi, kelime dağarcığı ve iletişim becerileri kazanın.',
        'og_title': 'Genel İngilizce A1 Kursu - KUM Academy',
        'og_description': 'A1 seviyesinde İngilizce öğrenin! KUM Academy ile temel dil bilgisi ve iletişim becerilerinizi geliştirin.',
    }
    return render(request, 'main/kurs-genel-ingilizce-a1.html', context)
def kurs_genel_ingilizce_a2(request):
    context = {
        'page_title': 'Genel İngilizce A2 Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile A2 seviyesinde Genel İngilizce kursu! Temel dil bilgisi, kelime dağarcığı ve iletişim becerilerinizi geliştirin.',
        'og_title': 'Genel İngilizce A2 Kursu - KUM Academy',
        'og_description': 'A2 seviyesinde İngilizce öğrenin! KUM Academy ile temel dil bilgisi ve iletişim becerilerinizi güçlendirin.',
    }
    return render(request, 'main/kurs-genel-ingilizce-a2.html', context)
def kurs_genel_ingilizce_b1(request):
    context = {
        'page_title': 'Genel İngilizce B1 Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile B1 seviyesinde Genel İngilizce kursu! Orta düzey dil bilgisi, kelime dağarcığı ve iletişim becerilerinizi geliştirin.',
        'og_title': 'Genel İngilizce B1 Kursu - KUM Academy',
        'og_description': 'B1 seviyesinde İngilizce öğrenin! KUM Academy ile orta düzey dil bilgisi ve iletişim becerilerinizi güçlendirin.',
    }
    return render(request, 'main/kurs-genel-ingilizce-b1.html', context)
def kurs_genel_ingilizce_b2(request):
    context = {
        'page_title': 'Genel İngilizce B2 Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile B2 seviyesinde Genel İngilizce kursu! İleri düzey dil bilgisi, kelime dağarcığı ve iletişim becerilerinizi geliştirin.',
        'og_title': 'Genel İngilizce B2 Kursu - KUM Academy',
        'og_description': 'B2 seviyesinde İngilizce öğrenin! KUM Academy ile ileri düzey dil bilgisi ve iletişim becerilerinizi güçlendirin.',
    }
    return render(request, 'main/kurs-genel-ingilizce-b2.html', context)


def kurs_ielts_sat(request):
    context = {
        'page_title': 'IELTS ve SAT Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile IELTS ve SAT sınavlarına hazırlanın! Uzman eğitim ve etkili stratejilerle hedeflerinize ulaşın.',
        'og_title': 'IELTS ve SAT Kursu - KUM Academy',
        'og_description': 'IELTS ve SAT sınavlarına KUM Academy ile hazırlanın! Uzman eğitmenler ve etkili stratejilerle başarıyı yakalayın.',
    }
    return render(request, 'main/kurs-ielts-sat.html', context)

def kurs_toefl(request):
    context = {
        'page_title': 'TOEFL Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile TOEFL sınavına hazırlanın! Uzman eğitim ve etkili stratejilerle hedeflerinize ulaşın.',
        'og_title': 'TOEFL Kursu - KUM Academy',
        'og_description': 'TOEFL sınavına KUM Academy ile hazırlanın! Uzman eğitmenler ve etkili stratejilerle başarıyı yakalayın.',
    }
    return render(request, 'main/kurs-toefl.html', context)

def kurs_speaking_dersleri(request):
    context = {
        'page_title': 'Speaking Dersleri - KUM Academy',
        'meta_description': 'KUM Academy ile Speaking dersleri alın! Etkili iletişim ve akıcı konuşma becerileri kazanın.',
        'og_title': 'Speaking Dersleri - KUM Academy',
        'og_description': 'Speaking dersleri ile İngilizce konuşma becerilerinizi geliştirin! KUM Academy’de etkili iletişim için gerekli tüm ipuçlarını öğrenin.',
    }
    return render(request, 'main/kurs-speaking-dersleri.html', context)

def kurs_birebir_speaking_yetiskinler(request):
    context = {
        'page_title': 'Birebir Konuşma Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile yetişkinler için birebir İngilizce konuşma kursu! Özgüvenle konuşun, dil becerilerinizi geliştirin.',
        'og_title': 'Birebir Konuşma Kursu - KUM Academy',
        'og_description': 'İngilizce konuşma becerilerinizi KUM Academy ile geliştirin! Birebir derslerle özgüven kazanın ve sosyalleşin.',
    }
    return render(request, 'main/kurs-birebir-speaking-yetiskinler.html', context)

def kurs_birebir_speaking_cocuklar(request):
    context = {
        'page_title': 'Çocuklar için İngilizce Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile 10-14 yaş çocuklar için İngilizce konuşma kursu! Oyunlar ve aktivitelerle akıcı konuşma becerisi kazanın.',
        'og_title': 'Çocuklar için İngilizce Kursu - KUM Academy',
        'og_description': 'Çocuklarınız KUM Academy ile İngilizce öğrensin! Eğlenceli aktivitelerle akıcı konuşma becerileri kazanın.',
    }
    return render(request, 'main/kurs-birebir-speaking-cocuklar.html', context)



def kurs_grup_speaking_yetiskinler(request):
    context = {
        'page_title': 'Grup Konuşma Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile grup konuşma kursu! Dinamik ve interaktif ortamda İngilizce pratiği yaparak iletişim becerilerinizi güçlendirin.',
        'og_title': 'Grup Konuşma Kursu - KUM Academy',
        'og_description': 'İngilizce pratiğinizi KUM Academy’nin grup konuşma kursuyla geliştirin! Eğlenceli ve interaktif derslerle öğrenin.',
    }
    return render(request, 'main/kurs-grup-speaking-yetiskinler.html', context)

def kurs_grup_speaking_cocuklar(request):
    context = {
        'page_title': 'Grup Konuşma Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile çocuklar için grup konuşma kursu! Eğlenceli aktivitelerle akıcı konuşma becerisi kazanın.',
        'og_title': 'Grup Konuşma Kursu - KUM Academy',
        'og_description': 'Çocuklarınız KUM Academy ile İngilizce konuşma pratiği yapsın! Eğlenceli grup dersleriyle öğrenin.',
    }
    return render(request, 'main/kurs-grup-speaking-cocuklar.html', context)

def kurs_genc_yetenekler_python(request):
    context = {
        'page_title': 'Genç Yetenekler - Python Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile gençler için Python kursu! Eğlenceli projelerle kodlama öğrenin, geleceğin mesleklerine hazırlanın.',
        'og_title': 'Genç Yetenekler - Python Kursu - KUM Academy',
        'og_description': 'Gençleriniz KUM Academy ile Python öğrensin! Eğlenceli projelerle kodlama becerileri kazanın.',
    }
    return render(request, 'main/kurs-genc-yetenekler-python.html', context)




def kurs_dijital_tasarim(request):
    context = {
        'page_title': 'Dijital Tasarım ve Video Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile Canva ve CapCut kullanarak dijital tasarım ve video düzenleme öğrenin! Profesyonel içerikler üretin.',
        'og_title': 'Dijital Tasarım ve Video Kursu - KUM Academy',
        'og_description': 'Canva ve CapCut ile yaratıcılığınızı geliştirin! KUM Academy ile sosyal medya ve reklam için profesyonel içerikler üretin.',
    }
    return render(request, 'main/kurs-dijital-tasarim.html', context)


def kurs_genc_yetenekler_yapay_zeka(request):
    context = {
        'page_title': 'Genç Yetenekler - Yapay Zeka Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile gençler için yapay zeka ve makine öğrenmesi kursu! mBlock ile görsel programlama ve AI projeleri geliştirin.',
        'og_title': 'Genç Yetenekler - Yapay Zeka Kursu - KUM Academy',
        'og_description': 'Gençleriniz KUM Academy ile yapay zeka öğrensin! mBlock ile eğlenceli AI projeleri geliştirin.',
    }
    return render(request, 'main/kurs-genc-yetenekler-yapay-zeka.html', context)

def kurs_genc_yetenekler_dijital_tasarim(request):
    context = {
        'page_title': 'Genç Yetenekler - Dijital Tasarım Kursu - KUM Academy',
        'meta_description': 'KUM Academy ile gençler için dijital tasarım ve video düzenleme kursu! Canva ve CapCut ile yaratıcılığınızı geliştirin.',
        'og_title': 'Genç Yetenekler - Dijital Tasarım Kursu - KUM Academy',
        'og_description': 'Gençleriniz KUM Academy ile dijital tasarım öğrensin! Canva ve CapCut ile eğlenceli projeler geliştirin.',
    }
    return render(request, 'main/kurs-genc-yetenekler-dijital-tasarim.html', context)

def kayit_ol(request):
    if request.method == 'POST':
        form = RegisterForm(request.POST)
        if form.is_valid():
            name = form.cleaned_data['name']
            email = form.cleaned_data['email']
            ulke = form.cleaned_data['ulke']
            
            # E-posta içeriği
            email_content = f"""
            Yeni Kayıt Başvurusu:
            Ad Soyad: {name}
            E-posta: {email}
            Tercih Edilen Ülke: {ulke}
            """
            try:
                send_mail(
                    subject='Yeni Kayıt Başvurusu - KUM Academy',
                    message=email_content,
                    from_email=email,
                    recipient_list=['info@kumacademy.com'],
                    fail_silently=False,
                )
                messages.success(request, 'Kayıt başvurunuz başarıyla gönderildi!')
                return redirect('index')
            except Exception as e:
                messages.error(request, f'Başvuru gönderilirken hata oluştu: {str(e)}')
    else:
        form = RegisterForm()
    
    context = {
        'page_title': 'Kayıt Ol - KUM Academy',
        'meta_description': 'KUM Academy ile online eğitim programlarına kaydolun. Amazon e-ticaret, IT Support, İngilizce ve daha fazla kurs için hemen başvurun.',
        'og_title': 'Kayıt Ol - KUM Academy',
        'og_description': 'KUM Academy’nin online kurslarına katılın! Amazon e-ticaret, IT Support, İngilizce ve yapay zeka kursları için hemen kaydolun.',
        'form': form,
    }
    return render(request, 'main/kayit-ol.html', context)

def blog_genel_ingilizce(request):
    return render(request, 'main/blog-genel-ingilizce.html', {
        'page_title': 'Genel İngilizce Kursları - KUM Academy',
        'meta_description': 'KUM Academy’nin Genel İngilizce kurslarıyla dil becerilerinizi geliştirin. Esnek, online ve etkili eğitim programlarımızla İngilizce öğrenin!',
        'og_title': 'Genel İngilizce Kursları - KUM Academy',
        'og_description': 'KUM Academy ile İngilizce konuşma, yazma ve dinleme becerilerinizi güçlendirin. Her seviyeye uygun online Genel İngilizce kurslarımıza katılın!'
    })
def blog_seo_nedir(request):
    return render(request, 'main/blog-seo-nedir.html', {
        'page_title': 'SEO Nedir? - KUM Academy Blog',
        'meta_description': 'SEO nedir? KUM Academy’nin blog yazısında arama motoru optimizasyonunun temellerini ve dijital başarıdaki rolünü öğrenin!',
        'og_title': 'SEO Nedir? - KUM Academy Blog',
        'og_description': 'SEO’nun ne olduğunu ve neden önemli olduğunu keşfedin. KUM Academy ile dijital pazarlama becerilerinizi geliştirin!'
    })

def blog_it_support_destegi_nedir(request):
    return render(request, 'main/blog-it-support-destegi-nedir.html', {
        'page_title': 'IT Destek (BT Desteği) Nedir? - KUM Academy Blog',
        'meta_description': 'IT desteği nedir? KUM Academy’nin blog yazısında işletmeler için BT desteğinin önemini ve temel unsurlarını öğrenin!',
        'og_title': 'IT Destek (BT Desteği) Nedir? - KUM Academy Blog',
        'og_description': 'IT desteği, işletmelerin teknoloji altyapısını nasıl güçlendirir? KUM Academy ile BT becerilerinizi geliştirin!'
    })

def blog_seonun_onemi(request):
    return render(request, 'main/blog-seonun-onemi.html', {
        'page_title': 'SEO’nun Önemi, Dijital Başarının Anahtarı - KUM Academy Blog',
        'meta_description': 'SEO’nun dijital başarıdaki rolünü öğrenin. KUM Academy’nin blog yazısında SEO’nun işletmeler için neden kritik olduğunu keşfedin!',
        'og_title': 'SEO’nun Önemi, Dijital Başarının Anahtarı - KUM Academy Blog',
        'og_description': 'SEO, dijital dünyada nasıl fark yaratır? KUM Academy ile SEO stratejilerini öğrenerek işletmenizi büyüt!'
    })

def blog_it_ingilizcesinin_onemi(request):
    return render(request, 'main/blog-it-ingilizcesinin-onemi.html', {
        'page_title': 'IT İngilizcesi: Teknoloji Dünyasında Başarı İçin Anahtar Beceriler - KUM Academy Blog',
        'meta_description': 'IT İngilizcesi ile teknoloji dünyasında fark yaratın! KUM Academy’nin blog yazısında IT İngilizcesinin önemini ve avantajlarını öğrenin.',
        'og_title': 'IT İngilizcesi: Teknoloji Dünyasında Başarı İçin Anahtar Beceriler - KUM Academy Blog',
        'og_description': 'IT İngilizcesi neden önemli? KUM Academy ile teknik terimleri öğrenerek teknoloji kariyerinizi güçlendirin!'
    })

def blog_ingilizce_konusma_becerilerini_gelistirin(request):
    return render(request, 'main/blog-ingilizce-konusma-becerilerini-gelistirin.html', {
        'page_title': 'Çocuklar İçin Speaking Kursları: İngilizce Konuşma Becerilerini Geliştirin - KUM Academy Blog',
        'meta_description': 'Çocuklar için İngilizce konuşma kursları! KUM Academy’nin blog yazısında çocukların speaking becerilerini nasıl geliştirebileceğini öğrenin.',
        'og_title': 'Çocuklar İçin Speaking Kursları: İngilizce Konuşma Becerilerini Geliştirin - KUM Academy Blog',
        'og_description': 'Çocuklarınızın İngilizce konuşma becerilerini geliştirin! KUM Academy’nin eğlenceli ve etkili speaking kurslarını keşfedin.'
    })


import requests
from django.shortcuts import render, redirect
from django.contrib import messages
from django.views.decorators.csrf import csrf_exempt  # Test için (üretimde kaldırın)

@csrf_exempt  # Test için CSRF devre dışı (üretimde güvenli hale getirin)
def test_young(request):
    if request.method == 'POST':
        # Form verilerini al
        name = request.POST.get('name', '')
        email = request.POST.get('email', '')
        whatsapp = request.POST.get('whatsapp', '')
        answers = {f"q{i}": request.POST.get(f"q{i}", '') for i in range(1, 26)}  # 1-25 arası sorular

        # Hata ayıklaması için verileri yazdır (geliştirme ortamında kontrol edin)
        print("Gönderilen Veriler:", {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers
        })

        # API isteği için veri hazırlama (test tipi ekleniyor)
        data = {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers,  # Soru cevaplarını ekle
            'api_key': '*x4EPq2h%zWn',  # PHP ile aynı olmalı!
            'test': 'young',  # Test tipini ekle
        }

        try:
            response = requests.post('https://raineducation.co.uk/test.php', data=data, timeout=5)
            response_data = response.text.strip()
            print("PHP Yanıtı:", response_data)  # Yanıtı kontrol edin

            if response_data.startswith("success:"):
                # Başarı durumunda, sonuçları ayıkla
                parts = response_data.split(':')[1].split(',')
                correct_count = int(parts[0].split('=')[1])
                incorrect_count = int(parts[1].split('=')[1])
                total_points = int(parts[2].split('=')[1])

                # Sonuçları test_young_results view'ına yönlendir
                return redirect('test_young_results', name=name, correct_count=correct_count, incorrect_count=incorrect_count, total_points=total_points)
            elif response_data == "unauthorized":
                messages.error(request, 'Yetkisiz istek. Lütfen yöneticinize başvurun.')
            elif response_data == "fail":
                messages.error(request, 'Mail gönderilemedi. Lütfen sunucu yöneticinize başvurun.')
            elif response_data == "invalid_test_type":
                messages.error(request, 'Geçersiz test türü.')
            else:
                messages.error(request, 'Test gönderilemedi. Lütfen tekrar deneyin.')
        except requests.exceptions.RequestException as e:
            messages.error(request, 'Sunucuya ulaşılamadı.')
            print("Hata:", str(e))

        return redirect('test-young')
    else:
        form = {
            'name': {'label': 'İsim Soyisim', 'required': True},
            'email': {'label': 'Mail Adresi', 'required': True},
            'whatsapp': {'label': 'Whatsapp İletişim Hattı', 'required': True},
        }
    
    context = {
        'page_title': 'Young Learners (6-12 age) Placement Test - KUM Academy',
        'meta_description': '6-12 yaş arası öğrenciler için İngilizce seviye belirleme testi. Dinleme, okuma ve dilbilgisi ile seviyenizi keşfedin!',
        'og_title': 'Young Learners (6-12 age) Placement Test - KUM Academy',
        'og_description': 'KUM Academy’nin 6-12 yaş grubu için hazırladığı İngilizce seviye belirleme testine katılın ve uygun öğrenme grubunuzu bulun.',
        'form': form,
    }
    return render(request, 'main/test-young.html', context)

@csrf_exempt  # Test için CSRF devre dışı (üretimde güvenli hale getirin)
def test_adult(request):
    if request.method == 'POST':
        # Form verilerini al
        name = request.POST.get('name', '')
        email = request.POST.get('email', '')
        whatsapp = request.POST.get('whatsapp', '')
        answers = {f"q{i}": request.POST.get(f"q{i}", '') for i in range(1, 26)}  # 1-25 arası sorular

        # Hata ayıklaması için verileri yazdır (geliştirme ortamında kontrol edin)
        print("Gönderilen Veriler:", {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers
        })

        # API isteği için veri hazırlama (test tipi ekleniyor)
        data = {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers,  # Soru cevaplarını ekle
            'api_key': '*x4EPq2h%zWn',  # PHP ile aynı olmalı!
            'test': 'adult',  # Test tipini ekle
        }

        try:
            response = requests.post('https://raineducation.co.uk/test.php', data=data, timeout=5)
            response_data = response.text.strip()
            print("PHP Yanıtı:", response_data)  # Yanıtı kontrol edin

            if response_data.startswith("success:"):
                # Başarı durumunda, sonuçları ayıkla
                parts = response_data.split(':')[1].split(',')
                correct_count = int(parts[0].split('=')[1])
                incorrect_count = int(parts[1].split('=')[1])
                total_points = int(parts[2].split('=')[1])

                # Sonuçları test_adult_results view'ına yönlendir
                return redirect('test_adult_results', name=name, correct_count=correct_count, incorrect_count=incorrect_count, total_points=total_points)
            elif response_data == "unauthorized":
                messages.error(request, 'Yetkisiz istek. Lütfen yöneticinize başvurun.')
            elif response_data == "fail":
                messages.error(request, 'Mail gönderilemedi. Lütfen sunucu yöneticinize başvurun.')
            elif response_data == "invalid_test_type":
                messages.error(request, 'Geçersiz test türü.')
            else:
                messages.error(request, 'Test gönderilemedi. Lütfen tekrar deneyin.')
        except requests.exceptions.RequestException as e:
            messages.error(request, 'Sunucuya ulaşılamadı.')
            print("Hata:", str(e))

        return redirect('test-adult')
    else:
        form = {
            'name': {'label': 'İsim Soyisim', 'required': True},
            'email': {'label': 'Mail Adresi', 'required': True},
            'whatsapp': {'label': 'Whatsapp İletişim Hattı', 'required': True},
        }
    
    context = {
        'page_title': 'Adult Learners Placement Test - KUM Academy',
        'meta_description': 'Yetişkinler için İngilizce seviye belirleme testi. Gelişmiş dilbilgisi, okuma ve dinleme ile seviyenizi keşfedin!',
        'og_title': 'Adult Learners Placement Test - KUM Academy',
        'og_description': 'KUM Academy’nin yetişkinler için hazırladığı İngilizce seviye belirleme testine katılın ve uygun öğrenme grubunuzu bulun.',
        'form': form,
    }
    return render(request, 'main/test-adult.html', context)

@csrf_exempt  # Test için CSRF devre dışı (üretimde güvenli hale getirin)
def test_13(request):
    if request.method == 'POST':
        # Form verilerini al
        name = request.POST.get('name', '')
        email = request.POST.get('email', '')
        whatsapp = request.POST.get('whatsapp', '')
        answers = {f"q{i}": request.POST.get(f"q{i}", '') for i in range(1, 26)}  # 1-25 arası sorular

        # Hata ayıklaması için verileri yazdır (geliştirme ortamında kontrol edin)
        print("Gönderilen Veriler:", {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers
        })

        # API isteği için veri hazırlama (test tipi ekleniyor)
        data = {
            'name': name,
            'email': email,
            'whatsapp': whatsapp,
            **answers,  # Soru cevaplarını ekle
            'api_key': '*x4EPq2h%zWn',  # PHP ile aynı olmalı!
            'test': 'onuc',  # Test tipini ekle
        }

        try:
            response = requests.post('https://raineducation.co.uk/test.php', data=data, timeout=5)
            response_data = response.text.strip()
            print("PHP Yanıtı:", response_data)  # Yanıtı kontrol edin

            if response_data.startswith("success:"):
                # Başarı durumunda, sonuçları ayıkla
                parts = response_data.split(':')[1].split(',')
                correct_count = int(parts[0].split('=')[1])
                incorrect_count = int(parts[1].split('=')[1])
                total_points = int(parts[2].split('=')[1])

                # Sonuçları test_13_results view'ına yönlendir
                return redirect('test_13_results', name=name, correct_count=correct_count, incorrect_count=incorrect_count, total_points=total_points)
            elif response_data == "unauthorized":
                messages.error(request, 'Yetkisiz istek. Lütfen yöneticinize başvurun.')
            elif response_data == "fail":
                messages.error(request, 'Mail gönderilemedi. Lütfen sunucu yöneticinize başvurun.')
            elif response_data == "invalid_test_type":
                messages.error(request, 'Geçersiz test türü.')
            else:
                messages.error(request, 'Test gönderilemedi. Lütfen tekrar deneyin.')
        except requests.exceptions.RequestException as e:
            messages.error(request, 'Sunucuya ulaşılamadı.')
            print("Hata:", str(e))

        return redirect('test-13')
    else:
        form = {
            'name': {'label': 'İsim Soyisim', 'required': True},
            'email': {'label': 'Mail Adresi', 'required': True},
            'whatsapp': {'label': 'Whatsapp İletişim Hattı', 'required': True},
        }
    
    context = {
        'page_title': 'Test-13 Placement Test - KUM Academy',
        'meta_description': 'Test-13 ile İngilizce seviyenizi değerlendirin. Dilbilgisi, okuma ve dinleme becerilerinizi test edin!',
        'og_title': 'Test-13 Placement Test - KUM Academy',
        'og_description': 'KUM Academy’nin Test-13 ile İngilizce seviye belirleme testine katılın ve uygun öğrenme grubunuzu bulun.',
        'form': form,
    }
    return render(request, 'main/test-13.html', context)

def test_young_results(request, name, correct_count, incorrect_count, total_points):
    context = {
        'page_title': 'Test Sonuçları - KUM Academy',
        'meta_description': 'Young Learners Placement Test sonuçlarınızı görüntüleyin.',
        'og_title': 'Test Sonuçları - KUM Academy',
        'og_description': 'Test sonuçlarınızı burada görebilirsiniz.',
        'name': name,
        'correct_count': correct_count,
        'incorrect_count': incorrect_count,
        'total_points': total_points,
        'max_points': 60,
    }
    return render(request, 'main/test-young-results.html', context)

def test_adult_results(request, name, correct_count, incorrect_count, total_points):
    context = {
        'page_title': 'Test Sonuçları - KUM Academy',
        'meta_description': 'Adult Learners Placement Test sonuçlarınızı görüntüleyin.',
        'og_title': 'Test Sonuçları - KUM Academy',
        'og_description': 'Test sonuçlarınızı burada görebilirsiniz.',
        'name': name,
        'correct_count': correct_count,
        'incorrect_count': incorrect_count,
        'total_points': total_points,
        'max_points': 60,
    }
    return render(request, 'main/test-adult-results.html', context)

def test_13_results(request, name, correct_count, incorrect_count, total_points):
    context = {
        'page_title': 'Test Sonuçları - KUM Academy',
        'meta_description': 'Test-13 Placement Test sonuçlarınızı görüntüleyin.',
        'og_title': 'Test Sonuçları - KUM Academy',
        'og_description': 'Test sonuçlarınızı burada görebilirsiniz.',
        'name': name,
        'correct_count': correct_count,
        'incorrect_count': incorrect_count,
        'total_points': total_points,
        'max_points': 60,
    }
    return render(request, 'main/test-13-results.html', context)