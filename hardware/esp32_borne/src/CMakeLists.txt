# Composant "main" (§hardware) — reconnaissance faciale embarquée (ESP-WHO,
# voir hardware/README.md) au sein d'un projet ESP-IDF exposant les API
# Arduino (WiFi.h, HTTPClient.h, SD_MMC.h, setup()/loop()) via le composant
# managé `arduino-esp32` déclaré dans idf_component.yml.
idf_component_register(
    SRCS "main.cpp" "face_engine.cpp" "face_cache.cpp"
    INCLUDE_DIRS "." "../include"
    REQUIRES arduino-esp32 human_face_detect human_face_recognition esp32-camera
)
