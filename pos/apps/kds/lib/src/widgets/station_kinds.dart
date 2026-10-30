import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Sex turining ikonkasi (sex tanlash va yig'ish ekrani). Noma'lum tur va sexsiz qatorlar ("Umumiy") uchun umumiy ikonka.
IconData stationKindIcon(String? kind) => switch (kind) {
  StationKind.osh => Icons.rice_bowl_outlined,
  StationKind.mangal => Icons.local_fire_department_outlined,
  StationKind.saladBar => Icons.eco_outlined,
  StationKind.tea => Icons.emoji_food_beverage_outlined,
  StationKind.kitchen => Icons.soup_kitchen_outlined,
  // P6-33
  StationKind.grill => Icons.outdoor_grill_outlined,
  StationKind.fryer => Icons.fastfood_outlined,
  StationKind.drinks => Icons.local_drink_outlined,
  StationKind.barista => Icons.coffee_outlined,
  StationKind.expo => Icons.takeout_dining_outlined,
  null => Icons.layers_outlined,
  _ => Icons.restaurant_outlined,
};

/// Sex turining o'zbekcha nomi: server `kind_label` bermasa (eski server) ekran o'zi yozadi.
String stationKindLabel(String? kind) => switch (kind) {
  StationKind.osh => 'Osh va qozon',
  StationKind.mangal => 'Mangal',
  StationKind.saladBar => 'Salat va bar',
  StationKind.tea => 'Choy',
  StationKind.kitchen => 'Oshxona',
  StationKind.grill => 'Grill',
  StationKind.fryer => 'Fritur',
  StationKind.drinks => 'Ichimliklar',
  StationKind.barista => 'Barista',
  StationKind.expo => "Yig'ish",
  _ => 'Boshqa',
};
