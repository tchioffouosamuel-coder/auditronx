# Composant "main" au sein d'un projet ESP-IDF exposant les API Arduino
# (WiFi.h, HTTPClient.h, SD_MMC.h, setup()/loop()).
idf_component_register(
    SRCS "main.cpp"
    INCLUDE_DIRS "."
    REQUIRES arduino-esp32 esp32-camera
)
