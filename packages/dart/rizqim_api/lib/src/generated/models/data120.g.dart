// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data120.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data120 _$Data120FromJson(Map<String, dynamic> json) => Data120(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data120ToJson(Data120 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
