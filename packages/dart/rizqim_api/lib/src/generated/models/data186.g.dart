// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data186.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data186 _$Data186FromJson(Map<String, dynamic> json) => Data186(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data186ToJson(Data186 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
