#!/bin/sh
# =====================================================================
#  RiskOps - konteyner giriş betiği
#
#  Üç iş yapar:
#    1. Veritabanı hazır olana kadar bekler
#    2. İLK açılışta örnek veriyi kurar
#    3. Apache'yi başlatır
#
#  Şema ve ayarlar BURADA kurulmaz: database/schema.sql ve seed.sql
#  MariaDB konteynerinin /docker-entrypoint-initdb.d dizinine bağlanır
#  ve gerçek mysql istemcisi tarafından çalıştırılır. Böylece SQL'i
#  noktalı virgülden bölmeye çalışmak gerekmez.
# =====================================================================

set -e

APP_DIR=/var/www/html
cd "$APP_DIR"

log() { echo "[riskops] $*"; }

as_app() { su -s /bin/sh www-data -c "cd $APP_DIR && $1"; }

# ---------------------------------------------------------------------
#  1. Veritabanını bekle
#
#  depends_on: service_healthy kullanılsa bile MariaDB'nin ilk açılışta
#  initdb betiklerini bitirmesi sürüyor. Bağlantı kurulana kadar
#  deneriz; 60 saniyede kurulamazsa hata ile çıkarız — sessizce bozuk
#  çalışan bir konteynerden iyidir.
# ---------------------------------------------------------------------
log "Veritabani bekleniyor..."

i=0
until php docker/db_ping.php >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        log "HATA: veritabanina 60 saniyede baglanilamadi."
        log "      'docker compose logs db' ciktisina bakin."
        exit 1
    fi
    sleep 2
done

log "Veritabani hazir."

# ---------------------------------------------------------------------
#  2. İlk açılışta örnek veri
#
#  YALNIZCA risks tablosu boşken. Konteyner yeniden başlatıldığında
#  mevcut veri korunur; aksi hâlde her restart veriyi silerdi.
# ---------------------------------------------------------------------
RISK_COUNT=$(php docker/db_ping.php --count 2>/dev/null || echo "-1")

if [ "$RISK_COUNT" = "0" ]; then
    log "Veritabani bos - ornek veri kuruluyor..."

    if as_app "php tools/seed_demo.php" >/dev/null 2>&1; then
        log "Ornek veri kuruldu (18 risk, 22 aksiyon)."
    else
        log "UYARI: ornek veri kurulamadi; uygulama bos baslayacak."
    fi

    if [ "$RISKOPS_DEMO" = "1" ]; then
        as_app "php docker/bootstrap_demo_user.php" || \
            log "UYARI: demo hesabi olusturulamadi."
    fi
else
    log "Mevcut veri korunuyor ($RISK_COUNT risk)."
fi

# ---------------------------------------------------------------------
#  3. Apache
# ---------------------------------------------------------------------
log "Ortam: ${RISKOPS_ENV:-development}  |  Demo kipi: ${RISKOPS_DEMO:-0}"
log "Apache basliyor -> http://localhost:8080"

exec "$@"
