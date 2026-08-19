# Turkpin Teknik Test

Bu repo iş başvuru sürecinde bir ön eleme aracı olarak kullanılmaktadır.


## Görevler

- API'den alınan oyunlar, oyun listesindeki seçim alanında (select) listelenmelidir.
- Bir oyun seçildiğinde, oyuna ait ürünler API’den alınarak tablo hâlinde gösterilmelidir.
- Oyun seçilmediğinde ürün listesi gizlenmelidir.
- Sipariş oluşturma işlevi eklenmelidir.
  - Tekil veya çoğul sipariş oluşturulabilmelidir.
  - API'nin gerektirdiği bilgiler toplanmalıdır.
  - Sipariş sonucu modal/popup aracılığıyla gösterilmelidir.


## Teknolojiler

- [PHP](https://www.php.net/)
- [Composer](https://getcomposer.org/)
  - [PHP dotenv](https://github.com/vlucas/phpdotenv)
  - [bramus/router](https://github.com/bramus/router)
  - [Smarty](https://smarty-php.github.io/smarty/)
- [Bootstrap](https://getbootstrap.com/)


## Notlar

- API dokümantasyonuna [dev.turkpin.com](https://dev.turkpin.com) adresinden ulaşabilirsiniz.
- API’yi kullanabilmeniz için IP adresinizin whitelist’e eklenmesi gerekmektedir. IP adresinizi `integration@turkpin.com` adresine e-posta ile iletmelisiniz.
- Projede bazı teknik eksiklikler ve iyileştirme alanları bulunabilir. Bunların tespit edilmesi ve doğru şekilde çözülmesi değerlendirmeye olumlu katkı sağlar.
- Yapay zekâ kullanımı:
  - Vibe coding: **YASAK** (birinin responsibility/accountability üstlenmesi gerekir).
  - AI-assisted coding / pair programming: **İYİ** (elbette faydalanabilirsiniz).
  - AI slop: **KABUL EDİLEMEZ** (özensiz ve doğrulanmamış yapay zeka çıktıları).
- Commit kullanımı:
  - [Turkpin Commit Formatı](git.md)
  - [Conventional Commits](https://www.conventionalcommits.org/)
  - [How to Write a Git Commit Message](https://cbea.ms/git-commit/)
- Sorularınızı `interview@turkpin.com` adresine iletebilirsiniz.


## Değerlendirme

Değerlendirme sırasında başlıca aşağıdaki konular dikkate alınacaktır:

- Projeye hakimiyet (analiz, kapsam belirleme, yapıyı anlama ve sürdürme)
- API entegrasyonu
- Temiz kod yazımı (okunabilir, anlaşılabilir ve sürdürülebilir)
- Git kullanımı (mesaj formatı, içerik ve bölümleme)
- Görev analizi (kapsam, bölümleme ve alt görevler)

Ek olarak, mülakata çağrılmanız durumunda, görevleri çözdüğünüz çalışma üzerinden birlikte geçilecektir.


## Süreç ve Teslim

- Teknik görev, iş başvurusunun ardından görev e-postasının alınmasından itibaren en geç 5 iş günü içinde teslim edilmelidir.
- Teslim edilen görevler 2–3 iş günü içinde değerlendirilecek ve sonuç bildirilecektir.
- Teslim için görevlerin tamamen bitirilmiş olması şart değildir. Tamamlanmamış çalışmalar da mevcut hâliyle değerlendirmeye alınacaktır.
