// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'template.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Template _$TemplateFromJson(Map<String, dynamic> json) => Template(
  profile: json['profile'] as String,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations2.fromJson(e as Map<String, dynamic>))
      .toList(),
  dishes: (json['dishes'] as List<dynamic>)
      .map((e) => Dishes.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$TemplateToJson(Template instance) => <String, dynamic>{
  'profile': instance.profile,
  'stations': instance.stations,
  'dishes': instance.dishes,
};
