// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data152.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data152 _$Data152FromJson(Map<String, dynamic> json) => Data152(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data152ToJson(Data152 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
