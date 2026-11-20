// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data185.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data185 _$Data185FromJson(Map<String, dynamic> json) => Data185(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data185ToJson(Data185 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
