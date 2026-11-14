from django.db import models

class Service(models.Model):
    title = models.CharField(max_length=100)  # Hizmetin adı (örn. "Sosyal Medya Yönetimi")
    description = models.TextField()          # Hizmetin açıklaması
    created_at = models.DateTimeField(auto_now_add=True)  # Oluşturulma tarihi

    def __str__(self):
        return self.title