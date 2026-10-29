// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'profiles.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Profiles _$ProfilesFromJson(Map<String, dynamic> json) => Profiles(
  key: json['key'] as String,
  label: json['label'] as String,
  description: json['description'] as String,
  mode: json['mode'] as String,
  firstScreen: json['first_screen'] as String,
  modules: (json['modules'] as List<dynamic>).map((e) => e as String).toList(),
  locksServiceCharge: json['locks_service_charge'] as bool,
  recommendedPlan: json['recommended_plan'] as String,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  dishes: (json['dishes'] as num).toInt(),
);

Map<String, dynamic> _$ProfilesToJson(Profiles instance) => <String, dynamic>{
  'key': instance.key,
  'label': instance.label,
  'description': instance.description,
  'mode': instance.mode,
  'first_screen': instance.firstScreen,
  'modules': instance.modules,
  'locks_service_charge': instance.locksServiceCharge,
  'recommended_plan': instance.recommendedPlan,
  'stations': instance.stations,
  'dishes': instance.dishes,
};
