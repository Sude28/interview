# Turkpin Teknik Test

Turkpin sandbox API'sinden oyun ve ürünleri listeleyen, tekli veya çoklu ürün siparişi
oluşturabilen PHP uygulamasıdır.

## Tamamlanan görevler

- Oyunlar epinOyunListesi metoduyla API'den alınır.
- Seçilen oyunun ürünleri epinUrunleri metoduyla tablo hâlinde gösterilir.
- Oyun seçilmediğinde ürün alanı oluşturulmaz.
- Bir veya birden fazla ürün aynı formdan seçilebilir.
- Miktar, karakter/teslim alan, ön sipariş ve barem alanları desteklenir.
- Sipariş sonuçları ürün bazında Bootstrap modal içinde gösterilir.
- Türkçe ve İngilizce arayüz bulunur.

## Gereksinimler

- PHP 8.1 veya üzeri
- Composer
- PHP curl, dom ve simplexml uzantıları
- Turkpin sandbox API için whitelist'e eklenmiş sunucu IP adresi

## Kurulum

~~~bash
git clone <repository-url>
cd interview
composer install
cp .env.example .env
~~~

.env dosyasındaki API ayarlarını [Turkpin API dokümantasyonundaki](https://dev.turkpin.com/)
sandbox bilgileriyle doldurun:

~~~dotenv
TURKPIN_API_URL=https://www.turkpin.net/api.php
TURKPIN_API_USERNAME=
TURKPIN_API_PASSWORD=
TURKPIN_API_TIMEOUT=15
~~~

Sandbox erişimi için sunucu IP adresinin integration@turkpin.com adresine iletilmesi
gerekir. Kimlik bilgileri .env dosyasında tutulur ve repoya eklenmez.

Uygulamayı PHP'nin yerleşik sunucusuyla başlatmak için:

~~~bash
php -S localhost:8000 index.php
~~~

Ardından http://localhost:8000 adresini açın.

## Test

Testler, gerçek API'ye sipariş göndermeden dokümantasyondaki XML sözleşmesini örnek
yanıtlarla doğrular:

~~~bash
composer test
composer analyse
~~~

Kapsanan temel durumlar:

- oyun ve ürün yanıtlarının normalize edilmesi,
- XML özel karakterlerinin güvenli biçimde kaçırılması,
- ön sipariş ve barem parametrelerinin gönderilmesi,
- başarılı sipariş sonucunun ayrıştırılması,
- API hata kodlarının ve geçersiz XML'in yönetilmesi.

## Tasarım kararları

### API katmanı

TurkpinApiClient, XML oluşturma, HTTP iletişimi ve yanıt normalizasyonunu controller'dan
ayırır. API'nin liste metodlarında kullandığı error/error_desc ile sipariş metodunda
kullandığı HATA_NO/HATA_ACIKLAMA alanları tek hata modeline dönüştürülür.

### Çoklu sipariş

API tek istekte bir ürün kabul ettiği için seçilen her ürün için ayrı
epinSiparisYarat çağrısı yapılır. Çağrılar atomik değildir: ürünlerden biri başarısız
olsa bile diğer sonuçlar kaybolmaz ve modal içinde ayrı ayrı gösterilir.

### Doğrulama

Tarayıcı kontrollerine güvenilmez. Siparişten hemen önce ürünler API'den tekrar alınır;
miktar, stok, minimum/maksimum adet ve varsa barem aralığı sunucu tarafında doğrulanır.
API dokümanına göre max_order = 0 sınırsız kabul edilir.

### Güvenlik ve hata yönetimi

- XML değerleri DOMDocument ile oluşturularak özel karakterler güvenli kaçırılır.
- API yalnızca HTTPS adresleri üzerinden çağrılır ve SSL doğrulaması kapatılmaz.
- Sipariş formunda CSRF doğrulaması vardır.
- Dil seçimi izin verilen değerlerle sınırlandırılmıştır.
- Kullanıcı girdileri şablonda HTML escape edilerek gösterilir.
- POST/Redirect/GET uygulanarak sayfa yenilemede siparişin tekrar gönderilmesi önlenir.
- Bağlantı zaman aşımı, HTTP hatası, API hata kodu ve hatalı XML ayrı yönetilir.

## Proje yapısı

~~~text
src/
├── classes/
│   ├── ApiException.php
│   ├── Home.php
│   ├── Main.php
│   └── TurkpinApiClient.php
├── languages/
└── templates/
tests/
├── fixtures/
└── run.php
~~~

## Bilinen çalışma koşulu

Gerçek sandbox çağrılarının çalışması Turkpin tarafındaki IP whitelist işlemine bağlıdır.
Whitelist olmadan uygulama API'nin döndürdüğü erişim hatasını kullanıcıya gösterir;
yerel sözleşme testleri bundan etkilenmez.
