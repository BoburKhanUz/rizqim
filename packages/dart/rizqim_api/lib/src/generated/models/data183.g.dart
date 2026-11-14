// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data183.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data183 _$Data183FromJson(Map<String, dynamic> json) => Data183(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data183ToJson(Data183 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
