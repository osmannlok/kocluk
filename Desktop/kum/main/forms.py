from django import forms

class RegisterForm(forms.Form):
    name = forms.CharField(max_length=100, label="Ad Soyad")
    email = forms.EmailField(label="E-posta")
    ulke = forms.ChoiceField(choices=[
        ('', 'Seçiniz'),
        ('ingiltere', 'İngiltere'),
        ('amerika', 'Amerika'),
        ('kanada', 'Kanada'),
        ('malta', 'Malta'),
        ('irlanda', 'İrlanda'),
        ('dubai', 'Dubai'),
    ], label="Tercih Ettiğiniz Ülke")

class ContactForm(forms.Form):

    adSoyad = forms.CharField(max_length=100, label="Ad Soyad", widget=forms.TextInput(attrs={'required': True, 'class':'form-control'}))
    email = forms.EmailField(label="E-posta Adresi", widget=forms.EmailInput(attrs={'required': True, 'class':'form-control'}))
    telefon = forms.CharField(max_length=15, label="Telefon Numarası", widget=forms.TextInput(attrs={'required': True, 'class':'form-control'}))
    konu = forms.CharField(max_length=200, label="Konu", widget=forms.TextInput(attrs={'required': True, 'class':'form-control'}))
    mesaj = forms.CharField(widget=forms.Textarea(attrs={'rows': 5, 'required': True, 'class':'form-control'}), label="Mesajınız")