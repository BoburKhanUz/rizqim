// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data159.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data159 _$Data159FromJson(Map<String, dynamic> json) => Data159(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data159ToJson(Data159 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
