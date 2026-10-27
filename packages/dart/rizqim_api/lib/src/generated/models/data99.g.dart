// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data99.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data99 _$Data99FromJson(Map<String, dynamic> json) => Data99(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data99ToJson(Data99 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
