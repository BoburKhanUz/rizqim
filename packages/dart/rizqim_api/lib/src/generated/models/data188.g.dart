// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data188.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data188 _$Data188FromJson(Map<String, dynamic> json) => Data188(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data188ToJson(Data188 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
