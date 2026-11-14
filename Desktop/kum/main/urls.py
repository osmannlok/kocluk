from django.urls import path
from . import views

urlpatterns = [
    path('', views.index, name='index'),

    path('kurs-it-ingilizcesi/', views.kurs_it_ingilizcesi, name='kurs-it-ingilizcesi'),
    path('kurs-genel-ingilizce/', views.kurs_genel_ingilizce, name='kurs-genel-ingilizce'),
    path('kurs-genel-ingilizce-a1/', views.kurs_genel_ingilizce_a1, name='kurs-genel-ingilizce-a1'),
    path('kurs-genel-ingilizce-a2/', views.kurs_genel_ingilizce_a2, name='kurs-genel-ingilizce-a2'),
    path('kurs-genel-ingilizce-b1/', views.kurs_genel_ingilizce_b1, name='kurs-genel-ingilizce-b1'),
    path('kurs-genel-ingilizce-b2/', views.kurs_genel_ingilizce_b2, name='kurs-genel-ingilizce-b2'),
    path('kurs-ielts-sat/', views.kurs_ielts_sat, name='kurs-ielts-sat'),
    path('kurs-toefl/', views.kurs_toefl, name='kurs-toefl'),
    path('kurs-speaking-dersleri/', views.kurs_speaking_dersleri, name='kurs-speaking-dersleri'),
    path('kurs-birebir-speaking-cocuklar/', views.kurs_birebir_speaking_cocuklar, name='kurs-birebir-speaking-cocuklar'),
    path('kurs-birebir-speaking-yetiskinler/', views.kurs_birebir_speaking_yetiskinler, name='kurs-birebir-speaking-yetiskinler'),
    path('kurs-grup-speaking-yetiskinler/', views.kurs_grup_speaking_yetiskinler, name='kurs-grup-speaking-yetiskinler'),
    path('kurs-grup-speaking-cocuklar/', views.kurs_grup_speaking_cocuklar, name='kurs-grup-speaking-cocuklar'),
    path('kurs-amazon-eticaret/', views.kurs_amazon_eticaret, name='kurs-amazon-eticaret'),
    path('kurs-it-support/', views.kurs_it_support, name='kurs-it-support'),
    path('kurs-dijital-pazarlama/', views.kurs_dijital_pazarlama, name='kurs-dijital-pazarlama'),
    path('kurs-genc-yetenekler-python/', views.kurs_genc_yetenekler_python, name='kurs-genc-yetenekler-python'),
    path('kurs-genc-yetenekler-yapay-zeka/', views.kurs_genc_yetenekler_yapay_zeka, name='kurs-genc-yetenekler-yapay-zeka'),
    path('kurs-genc-yetenekler-dijital-tasarim/', views.kurs_genc_yetenekler_dijital_tasarim, name='kurs-genc-yetenekler-dijital-tasarim'),

    path('test-young/', views.test_young, name='test-young'),
    path('test-young-results/<str:name>/<int:correct_count>/<int:incorrect_count>/<int:total_points>/', views.test_young_results, name='test_young_results'),
    path('test-adult/', views.test_adult, name='test-adult'),
    path('test-adult-results/<str:name>/<int:correct_count>/<int:incorrect_count>/<int:total_points>/', views.test_adult_results, name='test_adult_results'),
    path('test-13/', views.test_13, name='test-13'),
    path('test-13-results/<str:name>/<int:correct_count>/<int:incorrect_count>/<int:total_points>/', views.test_13_results, name='test_13_results'),

    path('blog-genel-ingilizce/', views.blog_genel_ingilizce, name='blog-genel-ingilizce'),
    path('blog-seo-nedir/', views.blog_seo_nedir, name='blog-seo-nedir'),
    path('blog-it-support-destegi-nedir/', views.blog_it_support_destegi_nedir, name='blog-it-support-destegi-nedir'),
    path('blog-seonun-onemi/', views.blog_seonun_onemi, name='blog-seonun-onemi'),
    path('blog-it-ingilizcesinin-onemi/', views.blog_it_ingilizcesinin_onemi, name='blog-it-ingilizcesinin-onemi'),
    path('blog-ingilizce-konusma-becerilerini-gelistirin/', views.blog_ingilizce_konusma_becerilerini_gelistirin, name='blog-ingilizce-konusma-becerilerini-gelistirin'),
    path('kurs-dijital-tasarim/', views.kurs_dijital_tasarim, name='kurs-dijital-tasarim'),
    path('hakkimizda/', views.hakkimizda, name='hakkimizda'),
    path('iletisim/', views.iletisim, name='iletisim'),
    path('blog/', views.blog, name='blog'),
    path('sss/', views.sss, name='sss'),
    
]